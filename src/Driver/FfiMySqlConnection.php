<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use FFI;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;
use LPhenom\Db\Exception\ConnectionException;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\Param;
use LPhenom\Db\Param\ParamBinder;

/**
 * @lphenom-build shared,kphp
 *
 * KPHP FFI MySQL driver.
 *
 * KPHP FFI architecture notes:
 *   - KPHP resolves FFI method types ONLY when FFI::cdef() is called with
 *     STRING LITERALS directly inside the method body (no function wrappers,
 *     no class/global constants as FFI::cdef() arguments).
 *   - Each method that calls MySQL C functions opens with:
 *       $ffi = FFI::cdef(FULL_MYSQL_HEADER_LITERAL, 'libmysqlclient.so.21');
 *     KPHP traces the FFI scope from this local assignment to all mysql_*()
 *     calls within the same method body.
 *   - All methods use the SAME literal header string so PHP caches one FFI
 *     instance — CData pointers are compatible across method calls.
 *   - No trailing comma in function call argument lists.
 *   - Param::$value is string (not mixed).
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiMySqlConnection implements ConnectionInterface
{
    /**
     * Active MySQL connection handle (MYSQL* C pointer).
     * @var FFI\CData
     */
    private FFI\CData $mysql;

    /** @var string */
    private string $host;
    /** @var string */
    private string $user;
    /** @var string */
    private string $password;
    /** @var string */
    private string $database;
    /** @var int */
    private int $port;

    /**
     * @throws ConnectionException
     */
    public function __construct(
        string $host,
        string $user,
        string $password,
        string $database,
        int    $port = 3306
    ) {
        $this->host     = $host;
        $this->user     = $user;
        $this->password = $password;
        $this->database = $database;
        $this->port     = $port;

        $this->connect();
    }

    /**
     * @throws ConnectionException
     */
    private function connect(): void
    {
        // KPHP: FFI::cdef() with literal string in method body — types resolved at compile time.
        // PHP: returns cached FFI instance for identical arguments.
        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        $handle = $ffi->mysql_init(null);
        if ($handle === null) {
            throw new ConnectionException('mysql_init() returned null — out of memory?');
        }

        $connected = $ffi->mysql_real_connect(
            $handle,
            $this->host,
            $this->user,
            $this->password,
            $this->database,
            $this->port,
            null,
            0
        );

        if ($connected === null) {
            $rawErr = $ffi->mysql_error($handle);
            $error = is_string($rawErr) ? $rawErr : \FFI::string($rawErr);
            $ffi->mysql_close($handle);
            throw new ConnectionException('MySQL FFI connect failed: ' . $error);
        }

        $this->mysql = $handle;
        $ffi->mysql_set_character_set($this->mysql, 'utf8mb4');
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        $finalSql = $this->buildSql($sql, $params);

        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        $ret = $ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            $rawErr = $ffi->mysql_error($this->mysql);
            throw new QueryException(
                'MySQL FFI query failed: ' . (is_string($rawErr) ? $rawErr : \FFI::string($rawErr)),
                (int) $ffi->mysql_errno($this->mysql)
            );
        }

        $res = $ffi->mysql_store_result($this->mysql);
        if ($res === null) {
            $rawErr = $ffi->mysql_error($this->mysql);
            $storeErr = is_string($rawErr) ? $rawErr : \FFI::string($rawErr);
            throw new QueryException('mysql_store_result() failed: ' . $storeErr);
        }

        return new FfiMySqlResult($res);
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function execute(string $sql, array $params = []): int
    {
        $finalSql = $this->buildSql($sql, $params);

        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        $ret = $ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            $rawErr = $ffi->mysql_error($this->mysql);
            throw new QueryException(
                'MySQL FFI execute failed: ' . (is_string($rawErr) ? $rawErr : \FFI::string($rawErr)),
                (int) $ffi->mysql_errno($this->mysql)
            );
        }

        return (int) $ffi->mysql_affected_rows($this->mysql);
    }

    /**
     * Run callback inside a transaction.
     *
     * KPHP note: callable is forbidden — TransactionCallbackInterface is used.
     * KPHP note: try/finally without catch is forbidden — exception stored in variable.
     *
     * @throws \Throwable
     */
    public function transaction(TransactionCallbackInterface $callback): mixed
    {
        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        $ffi->mysql_autocommit($this->mysql, 0);

        $exception = null;
        $result    = null;
        try {
            $result = $callback->execute($this);
            $ffi->mysql_commit($this->mysql);
        } catch (\Throwable $e) {
            $exception = $e;
            $ffi->mysql_rollback($this->mysql);
        }

        $ffi->mysql_autocommit($this->mysql, 1);

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    public function close(): void
    {
        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        $ffi->mysql_close($this->mysql);
    }

    /**
     * Substitute named :param placeholders with safely escaped values.
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
            $sql     = str_replace($placeholder, $escaped, $sql);
        }

        return $sql;
    }

    private function escapeSingleParam(Param $param): string
    {
        if ($param->isNull) {
            return 'NULL';
        }

        if ($param->type === ParamBinder::PARAM_INT || $param->type === ParamBinder::PARAM_BOOL) {
            return $param->value;
        }

        $raw    = $param->value;
        $maxLen = strlen($raw) * 2 + 1;

        $ffi = \FFI::cdef(
            'typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;
typedef struct {
    char *name; char *org_name; char *table; char *org_table;
    char *db; char *catalog; char *def;
    unsigned long length; unsigned long max_length;
    unsigned int name_length; unsigned int org_name_length;
    unsigned int table_length; unsigned int org_table_length;
    unsigned int db_length; unsigned int catalog_length;
    unsigned int def_length; unsigned int flags;
    unsigned int decimals; unsigned int charsetnr;
    unsigned int type; void *extension;
} MYSQL_FIELD;
MYSQL *mysql_init(MYSQL *mysql);
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void mysql_close(MYSQL *mysql);
int mysql_query(MYSQL *mysql, const char *query);
const char *mysql_error(MYSQL *mysql);
unsigned int mysql_errno(MYSQL *mysql);
MYSQL_RES *mysql_store_result(MYSQL *mysql);
MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int mysql_num_fields(MYSQL_RES *result);
my_ulonglong mysql_num_rows(MYSQL_RES *result);
my_ulonglong mysql_affected_rows(MYSQL *mysql);
void mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);',
            'libmysqlclient.so.21'
        );

        /** @var FFI\CData $buf */
        $buf = FFI::new("char[$maxLen]");
        $ffi->mysql_real_escape_string($this->mysql, $buf, $raw, strlen($raw));

        return "'" . FFI::string($buf) . "'";
    }
}
