<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use FFI;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Exception\ConnectionException;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\Param;
use LPhenom\Db\Param\ParamBinder;

/**
 * KPHP FFI MySQL driver.
 *
 * Uses libmysqlclient via PHP FFI extension to execute raw SQL queries.
 * This driver is designed for compiled KPHP mode where PDO is unavailable.
 *
 * In standard PHP mode this driver works as well, provided:
 *   - the `ffi` PHP extension is enabled
 *   - libmysqlclient.so (or libmysqlclient.dylib) is installed on the system
 *
 * FFI and KPHP:
 *   KPHP supports FFI calls natively — compiled binaries can link against
 *   shared C libraries directly. The same PHP source code with FFI calls
 *   compiles to fast native code when processed by `kphp --mode=server`.
 *   There is no PDO in KPHP runtime; FFI is the primary way to call
 *   native libraries (MySQL client, Redis client, etc.).
 *
 * How KPHP FFI works:
 *   1. You define a C header string with `FFI::cdef(...)` — this declares
 *      the C functions and types you want to call.
 *   2. KPHP reads these declarations at compile time and generates direct
 *      native calls in the output binary.
 *   3. At runtime the shared library is loaded (dlopen) once per process.
 *
 * Parameter escaping:
 *   MySQL C API does not have named prepared statements in the same style
 *   as PDO. We use `mysql_real_escape_string` to safely escape each value
 *   and substitute it inline. This is safe because:
 *     - NULL becomes SQL NULL
 *     - integers/floats are formatted directly (no escape needed)
 *     - strings are escaped and quoted
 *     - booleans become 1 or 0
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiMySqlConnection implements ConnectionInterface
{
    private const C_HEADER = <<<'C'
        typedef unsigned long long my_ulonglong;
        typedef struct MYSQL MYSQL;
        typedef struct MYSQL_RES MYSQL_RES;
        typedef struct { char **row; } MYSQL_ROW_STRUCT;
        typedef char** MYSQL_ROW;
        typedef unsigned int MYSQL_FIELD_OFFSET;

        typedef struct {
            char *name;
            char *org_name;
            char *table;
            char *org_table;
            char *db;
            char *catalog;
            char *def;
            unsigned long length;
            unsigned long max_length;
            unsigned int name_length;
            unsigned int org_name_length;
            unsigned int table_length;
            unsigned int org_table_length;
            unsigned int db_length;
            unsigned int catalog_length;
            unsigned int def_length;
            unsigned int flags;
            unsigned int decimals;
            unsigned int charsetnr;
            unsigned int type;
            void *extension;
        } MYSQL_FIELD;

        MYSQL *mysql_init(MYSQL *mysql);
        MYSQL *mysql_real_connect(
            MYSQL *mysql,
            const char *host,
            const char *user,
            const char *passwd,
            const char *db,
            unsigned int port,
            const char *unix_socket,
            unsigned long client_flag
        );
        void   mysql_close(MYSQL *mysql);
        int    mysql_query(MYSQL *mysql, const char *query);
        int    mysql_real_query(MYSQL *mysql, const char *query, unsigned long length);
        const char *mysql_error(MYSQL *mysql);
        unsigned int mysql_errno(MYSQL *mysql);
        MYSQL_RES *mysql_store_result(MYSQL *mysql);
        MYSQL_RES *mysql_use_result(MYSQL *mysql);
        MYSQL_ROW  mysql_fetch_row(MYSQL_RES *result);
        MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
        unsigned int mysql_num_fields(MYSQL_RES *result);
        my_ulonglong mysql_num_rows(MYSQL_RES *result);
        my_ulonglong mysql_affected_rows(MYSQL *mysql);
        void         mysql_free_result(MYSQL_RES *result);
        unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
        int  mysql_set_character_set(MYSQL *mysql, const char *csname);
        unsigned long mysql_real_escape_string(
            MYSQL *mysql,
            char *to,
            const char *from,
            unsigned long length
        );
        int mysql_autocommit(MYSQL *mysql, char auto_mode);
        int mysql_commit(MYSQL *mysql);
        int mysql_rollback(MYSQL *mysql);
        C;

    /** @var FFI */
    private FFI $ffi;

    /** @var FFI\CData MySQL handle */
    private FFI\CData $mysql;

    /**
     * @throws ConnectionException
     */
    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $password,
        private readonly string $database,
        private readonly int    $port = 3306,
        string $libPath = 'libmysqlclient.so.21',
    ) {
        try {
            $this->ffi = FFI::cdef(self::C_HEADER, $libPath);
        } catch (\Exception $e) {
            throw new ConnectionException(
                'Failed to load libmysqlclient via FFI: ' . $e->getMessage(),
                0,
                $e,
            );
        } catch (\Error $e) {
            // FFI\Exception extends \Error (not \Exception) on PHP 8.2+
            throw new ConnectionException(
                'Failed to load libmysqlclient via FFI: ' . $e->getMessage(),
            );
        }

        $this->connect();
    }

    /**
     * @throws ConnectionException
     */
    private function connect(): void
    {
        $handle = $this->ffi->mysql_init(null);
        if ($handle === null) {
            throw new ConnectionException('mysql_init() returned null — out of memory?');
        }

        $connected = $this->ffi->mysql_real_connect(
            $handle,
            $this->host,
            $this->user,
            $this->password,
            $this->database,
            $this->port,
            null,
            0,
        );

        if ($connected === null) {
            $error = FFI::string($this->ffi->mysql_error($handle));
            $this->ffi->mysql_close($handle);
            throw new ConnectionException('MySQL FFI connect failed: ' . $error);
        }

        $this->mysql = $handle;
        $this->ffi->mysql_set_character_set($this->mysql, 'utf8mb4');
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        $finalSql = $this->buildSql($sql, $params);
        $ret = $this->ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            throw new QueryException(
                'MySQL FFI query failed: ' . FFI::string($this->ffi->mysql_error($this->mysql)),
                (int) $this->ffi->mysql_errno($this->mysql),
            );
        }

        $res = $this->ffi->mysql_store_result($this->mysql);
        if ($res === null) {
            throw new QueryException(
                'mysql_store_result() failed: ' . FFI::string($this->ffi->mysql_error($this->mysql)),
            );
        }

        return new FfiMySqlResult($this->ffi, $res);
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function execute(string $sql, array $params = []): int
    {
        $finalSql = $this->buildSql($sql, $params);
        $ret = $this->ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            throw new QueryException(
                'MySQL FFI execute failed: ' . FFI::string($this->ffi->mysql_error($this->mysql)),
                (int) $this->ffi->mysql_errno($this->mysql),
            );
        }

        return (int) $this->ffi->mysql_affected_rows($this->mysql);
    }

    /**
     * @throws \Exception
     */
    public function transaction(callable $callback): int|string|bool|float|null
    {
        $this->ffi->mysql_autocommit($this->mysql, 0);

        try {
            $result = $callback($this);
            $this->ffi->mysql_commit($this->mysql);

            /** @var int|string|bool|float|null $result */
            return $result;
        } catch (\Exception $e) {
            $this->ffi->mysql_rollback($this->mysql);

            throw $e;
        } finally {
            $this->ffi->mysql_autocommit($this->mysql, 1);
        }
    }

    public function close(): void
    {
        $this->ffi->mysql_close($this->mysql);
    }

    /**
     * Substitute named :param placeholders with safely escaped values.
     *
     * Replacement strategy per type (PDO::PARAM_*):
     *   PDO::PARAM_INT  (1) — formatted as integer literal
     *   PDO::PARAM_STR  (2) — escaped with mysql_real_escape_string and quoted
     *   PDO::PARAM_NULL (0) — becomes NULL literal
     *   PDO::PARAM_BOOL (5) — becomes 1 or 0 literal
     *
     * @param array<string, Param> $params
     */
    private function buildSql(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }

        foreach ($params as $placeholder => $param) {
            $escaped = $this->escapeSingleParam($param);
            $sql = str_replace($placeholder, $escaped, $sql);
        }

        return $sql;
    }

    private function escapeSingleParam(Param $param): string
    {
        if ($param->type === ParamBinder::PARAM_NULL || $param->value === null) {
            return 'NULL';
        }

        if ($param->type === ParamBinder::PARAM_INT) {
            return (string) (int) $param->value;
        }

        if ($param->type === ParamBinder::PARAM_BOOL) {
            return $param->value ? '1' : '0';
        }

        // PARAM_STR = 2 (also float stored as string)
        $raw = (string) $param->value;
        $maxLen = strlen($raw) * 2 + 1;

        /** @var FFI\CData $buf */
        $buf = FFI::new("char[$maxLen]");
        $this->ffi->mysql_real_escape_string($this->mysql, $buf, $raw, strlen($raw));

        return "'" . FFI::string($buf) . "'";
    }
}
