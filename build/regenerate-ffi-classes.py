#!/usr/bin/env python3
"""
Regenerates FfiMySqlConnection.php and FfiMySqlResult.php with a single
canonical MySQL C header string in every FFI::cdef() call.
"""

import hashlib
import os

BASE = '/home/dalmar/Projects/lphenom/db/src/Driver'

# Canonical MySQL C header — exactly one occurrence of each function.
# All FFI::cdef() calls in both files use this IDENTICAL string so that:
#   1. KPHP creates one FFI scope with all method types resolved.
#   2. PHP's FFI::cdef() cache returns the same instance across methods —
#      CData pointers (MYSQL*, MYSQL_RES*) are compatible across method calls.
H = (
    "typedef unsigned long long my_ulonglong;\n"
    "typedef struct MYSQL MYSQL;\n"
    "typedef struct MYSQL_RES MYSQL_RES;\n"
    "typedef char** MYSQL_ROW;\n"
    "typedef unsigned int MYSQL_FIELD_OFFSET;\n"
    "typedef struct {\n"
    "    char *name; char *org_name; char *table; char *org_table;\n"
    "    char *db; char *catalog; char *def;\n"
    "    unsigned long length; unsigned long max_length;\n"
    "    unsigned int name_length; unsigned int org_name_length;\n"
    "    unsigned int table_length; unsigned int org_table_length;\n"
    "    unsigned int db_length; unsigned int catalog_length;\n"
    "    unsigned int def_length; unsigned int flags;\n"
    "    unsigned int decimals; unsigned int charsetnr;\n"
    "    unsigned int type; void *extension;\n"
    "} MYSQL_FIELD;\n"
    "MYSQL *mysql_init(MYSQL *mysql);\n"
    "MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, "
    "const char *passwd, const char *db, unsigned int port, "
    "const char *unix_socket, unsigned long client_flag);\n"
    "void mysql_close(MYSQL *mysql);\n"
    "int mysql_query(MYSQL *mysql, const char *query);\n"
    "const char *mysql_error(MYSQL *mysql);\n"
    "unsigned int mysql_errno(MYSQL *mysql);\n"
    "MYSQL_RES *mysql_store_result(MYSQL *mysql);\n"
    "MYSQL_ROW mysql_fetch_row(MYSQL_RES *result);\n"
    "MYSQL_FIELD *mysql_fetch_fields(MYSQL_RES *result);\n"
    "MYSQL_FIELD *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);\n"
    "unsigned int mysql_num_fields(MYSQL_RES *result);\n"
    "my_ulonglong mysql_num_rows(MYSQL_RES *result);\n"
    "my_ulonglong mysql_affected_rows(MYSQL *mysql);\n"
    "void mysql_free_result(MYSQL_RES *result);\n"
    "unsigned long *mysql_fetch_lengths(MYSQL_RES *result);\n"
    "int mysql_set_character_set(MYSQL *mysql, const char *csname);\n"
    "unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, "
    "const char *from, unsigned long length);\n"
    "int mysql_autocommit(MYSQL *mysql, char auto_mode);\n"
    "int mysql_commit(MYSQL *mysql);\n"
    "int mysql_rollback(MYSQL *mysql);"
)

LIB = "libmysqlclient.so.21"

# The $ffi = FFI::cdef(...) block inserted at the start of each method.
CDEF = (
    "        $ffi = \\FFI::cdef(\n"
    "            '" + H + "',\n"
    "            '" + LIB + "'\n"
    "        );"
)

def write_connection_php():
    return '''<?php

declare(strict_types=1);

namespace LPhenom\\Db\\Driver;

use FFI;
use LPhenom\\Db\\Contract\\ConnectionInterface;
use LPhenom\\Db\\Contract\\ResultInterface;
use LPhenom\\Db\\Contract\\TransactionCallbackInterface;
use LPhenom\\Db\\Exception\\ConnectionException;
use LPhenom\\Db\\Exception\\QueryException;
use LPhenom\\Db\\Param\\Param;
use LPhenom\\Db\\Param\\ParamBinder;

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
     * @var FFI\\CData
     */
    private FFI\\CData $mysql;

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
''' + CDEF + '''

        $handle = $ffi->mysql_init(null);
        if ($handle === null) {
            throw new ConnectionException(\'mysql_init() returned null — out of memory?\');
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
            $error = FFI::string($ffi->mysql_error($handle));
            $ffi->mysql_close($handle);
            throw new ConnectionException(\'MySQL FFI connect failed: \' . $error);
        }

        $this->mysql = $handle;
        $ffi->mysql_set_character_set($this->mysql, \'utf8mb4\');
    }

    /**
     * @param array<string, Param> $params
     * @throws QueryException
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        $finalSql = $this->buildSql($sql, $params);

''' + CDEF + '''

        $ret = $ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            throw new QueryException(
                \'MySQL FFI query failed: \' . FFI::string($ffi->mysql_error($this->mysql)),
                (int) $ffi->mysql_errno($this->mysql)
            );
        }

        $res = $ffi->mysql_store_result($this->mysql);
        if ($res === null) {
            $storeErr = FFI::string($ffi->mysql_error($this->mysql));
            throw new QueryException(\'mysql_store_result() failed: \' . $storeErr);
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

''' + CDEF + '''

        $ret = $ffi->mysql_query($this->mysql, $finalSql);

        if ($ret !== 0) {
            throw new QueryException(
                \'MySQL FFI execute failed: \' . FFI::string($ffi->mysql_error($this->mysql)),
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
     * @throws \\Throwable
     */
    public function transaction(TransactionCallbackInterface $callback): mixed
    {
''' + CDEF + '''

        $ffi->mysql_autocommit($this->mysql, 0);

        $exception = null;
        $result    = null;
        try {
            $result = $callback->execute($this);
            $ffi->mysql_commit($this->mysql);
        } catch (\\Throwable $e) {
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
''' + CDEF + '''

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
            return \'NULL\';
        }

        if ($param->type === ParamBinder::PARAM_INT || $param->type === ParamBinder::PARAM_BOOL) {
            return $param->value;
        }

        $raw    = $param->value;
        $maxLen = strlen($raw) * 2 + 1;

''' + CDEF + '''

        /** @var FFI\\CData $buf */
        $buf = FFI::new("char[$maxLen]");
        $ffi->mysql_real_escape_string($this->mysql, $buf, $raw, strlen($raw));

        return "\'" . FFI::string($buf) . "\'";
    }
}
'''


def write_result_php():
    return '''<?php

declare(strict_types=1);

namespace LPhenom\\Db\\Driver;

use FFI;
use LPhenom\\Db\\Contract\\ResultInterface;

/**
 * @lphenom-build shared,kphp
 *
 * KPHP FFI note: each method that calls MySQL C functions creates a local
 *   $ffi = FFI::cdef(LITERAL_HEADER, \'libmysqlclient.so.21\')
 * so KPHP resolves mysql_*() method types at compile time.
 * FFI objects are NOT passed as parameters (KPHP loses scope info through params).
 * PHP returns the same cached FFI instance for identical cdef() arguments.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiMySqlResult implements ResultInterface
{
    /** @var FFI\\CData */
    private FFI\\CData $result;

    /** @var array<string>|null */
    private ?array $columnNames = null;

    /** @var bool */
    private bool $freed = false;

    public function __construct(FFI\\CData $result)
    {
        $this->result = $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
''' + CDEF + '''

        $row = $ffi->mysql_fetch_row($this->result);

        if ($row === null) {
            if (!$this->freed) {
                $ffi->mysql_free_result($this->result);
                $this->freed = true;
            }
            return null;
        }

        $assoc = $this->rowToAssoc($row);

        if (!$this->freed) {
            $ffi->mysql_free_result($this->result);
            $this->freed = true;
        }

        return $assoc;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
''' + CDEF + '''

        $rows = [];
        while (true) {
            $row = $ffi->mysql_fetch_row($this->result);
            if ($row === null) {
                break;
            }
            $rows[] = $this->rowToAssoc($row);
        }

        if (!$this->freed) {
            $ffi->mysql_free_result($this->result);
            $this->freed = true;
        }

        return $rows;
    }

    /**
     * @param FFI\\CData $row
     * @return array<string, mixed>
     */
    private function rowToAssoc(FFI\\CData $row): array
    {
        $columns   = $this->getColumnNames();
        $numFields = count($columns);
        $assoc     = [];

        for ($i = 0; $i < $numFields; $i++) {
            $cell            = $row[$i];
            $assoc[$columns[$i]] = $cell !== null ? FFI::string($cell) : null;
        }

        return $assoc;
    }

    /**
     * @return array<string>
     */
    private function getColumnNames(): array
    {
        if ($this->columnNames !== null) {
            return $this->columnNames;
        }

''' + CDEF + '''

        $num    = (int) $ffi->mysql_num_fields($this->result);

        $names = [];
        for ($i = 0; $i < $num; $i++) {
            // mysql_fetch_field_direct returns MYSQL_FIELD* — single struct pointer.
            // KPHP resolves ->name from MYSQL_FIELD struct definition in the FFI header.
            // Avoids $array[$i]->field pointer-arithmetic pattern unsupported by KPHP.
            $field   = $ffi->mysql_fetch_field_direct($this->result, $i);
            $names[] = FFI::string($field->name);
        }

        $this->columnNames = $names;

        return $names;
    }
}
'''


conn_content = write_connection_php()
result_content = write_result_php()

with open(os.path.join(BASE, 'FfiMySqlConnection.php'), 'w') as f:
    f.write(conn_content)

with open(os.path.join(BASE, 'FfiMySqlResult.php'), 'w') as f:
    f.write(result_content)

# Verify: all cdef header strings must be identical
import re
pattern = re.compile(r"FFI::cdef\(\s*\n\s+'(.*?)',\s*\n\s+'libmysqlclient", re.DOTALL)

conn_matches = pattern.findall(conn_content)
res_matches = pattern.findall(result_content)
all_matches = conn_matches + res_matches

unique_hashes = set(hashlib.md5(m.encode()).hexdigest() for m in all_matches)
print(f"FfiMySqlConnection.php: {len(conn_matches)} cdef blocks")
print(f"FfiMySqlResult.php:     {len(res_matches)} cdef blocks")
print(f"Unique header hashes:   {len(unique_hashes)} (MUST be 1)")
if len(unique_hashes) == 1:
    print("OK: All headers are identical!")
else:
    print("ERROR: Headers differ!")
    for i, m in enumerate(all_matches):
        print(f"  Block {i}: hash={hashlib.md5(m.encode()).hexdigest()[:8]} len={len(m)}")

