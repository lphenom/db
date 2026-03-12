<?php

declare(strict_types=1);

/**
 * Global constants for KPHP FFI::cdef() compatibility.
 *
 * KPHP's FFI scope registrar can only resolve GLOBAL (non-namespaced) PHP constants
 * for FFI::cdef() first argument analysis. Namespace-level constants are NOT resolved.
 *
 * This file MUST NOT have a namespace declaration.
 * It is loaded via composer.json "autoload.files" and via kphp-entrypoint.php require_once.
 *
 * @lphenom-build shared,kphp
 */

/**
 * C header string for libmysqlclient FFI bindings.
 * Must be a global PHP const for KPHP FFI scope analysis to work.
 */
const LPHENOM_DB_MYSQL_FFI_HEADER = 'typedef unsigned long long my_ulonglong;
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
MYSQL *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
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
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int mysql_autocommit(MYSQL *mysql, char auto_mode);
int mysql_commit(MYSQL *mysql);
int mysql_rollback(MYSQL *mysql);';

/**
 * Default libmysqlclient shared library path.
 * Used in lphenom_db_mysql_get_ffi() FFI::cdef() fallback when the 'mysql'
 * FFI scope is not preloaded. Override via FFI_MYSQL_LIB environment variable
 * (e.g. "libmariadb.so.3" on Alpine Linux).
 */
const LPHENOM_DB_MYSQL_DEFAULT_LIB = 'libmysqlclient.so.21';

/**
 * Return the FFI binding for libmysqlclient.
 *
 * For PHP (without KPHP): use this function to obtain a cached FFI instance.
 * Library path is taken from FFI_MYSQL_LIB env var (e.g. libmariadb.so.3 on Alpine)
 * or falls back to LPHENOM_DB_MYSQL_DEFAULT_LIB.
 *
 * For KPHP: this function is NOT used by FfiMySqlConnection/FfiMySqlResult —
 * those classes use inline FFI::cdef() with string literals per method body
 * for KPHP type resolution (KPHP requires FFI::cdef() inline, not wrapped).
 * This function is kept for PHP compatibility and backward API compatibility.
 *
 * @return \FFI
 */
function lphenom_db_mysql_get_ffi(): \FFI
{
    $lib = (string) (getenv('FFI_MYSQL_LIB') ?: LPHENOM_DB_MYSQL_DEFAULT_LIB);
    return \FFI::cdef(LPHENOM_DB_MYSQL_FFI_HEADER, $lib);
}
