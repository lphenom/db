<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use FFI;
use LPhenom\Db\Contract\ResultInterface;

/**
 * @lphenom-build shared,kphp
 *
 * KPHP FFI note: each method that calls MySQL C functions creates a local
 *   $ffi = FFI::cdef(LITERAL_HEADER, 'libmysqlclient.so.21')
 * so KPHP resolves mysql_*() method types at compile time.
 * FFI objects are NOT passed as parameters (KPHP loses scope info through params).
 * PHP returns the same cached FFI instance for identical cdef() arguments.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiMySqlResult implements ResultInterface
{
    /** @var FFI\CData */
    private FFI\CData $result;

    /** @var array<string>|null */
    private ?array $columnNames = null;

    /** @var bool */
    private bool $freed = false;

    public function __construct(FFI\CData $result)
    {
        $this->result = $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
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
     * @param FFI\CData $row
     * @return array<string, mixed>
     */
    private function rowToAssoc(FFI\CData $row): array
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
