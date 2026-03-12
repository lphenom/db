// @kphp-ffi-scope mysql

// PHP FFI preloading directives (used when FFI::load() is called):
#define FFI_SCOPE "mysql"
#define FFI_LIB "libmysqlclient.so.21"

typedef unsigned long long my_ulonglong;
typedef struct MYSQL MYSQL;
typedef struct MYSQL_RES MYSQL_RES;
typedef char** MYSQL_ROW;
typedef unsigned int MYSQL_FIELD_OFFSET;

typedef struct {
    char          *name;
    char          *org_name;
    char          *table;
    char          *org_table;
    char          *db;
    char          *catalog;
    char          *def;
    unsigned long  length;
    unsigned long  max_length;
    unsigned int   name_length;
    unsigned int   org_name_length;
    unsigned int   table_length;
    unsigned int   org_table_length;
    unsigned int   db_length;
    unsigned int   catalog_length;
    unsigned int   def_length;
    unsigned int   flags;
    unsigned int   decimals;
    unsigned int   charsetnr;
    unsigned int   type;
    void          *extension;
} MYSQL_FIELD;

MYSQL        *mysql_init(MYSQL *mysql);
MYSQL        *mysql_real_connect(MYSQL *mysql, const char *host, const char *user, const char *passwd, const char *db, unsigned int port, const char *unix_socket, unsigned long client_flag);
void          mysql_close(MYSQL *mysql);
int           mysql_query(MYSQL *mysql, const char *query);
int           mysql_real_query(MYSQL *mysql, const char *query, unsigned long length);
const char   *mysql_error(MYSQL *mysql);
unsigned int  mysql_errno(MYSQL *mysql);
MYSQL_RES    *mysql_store_result(MYSQL *mysql);
MYSQL_RES    *mysql_use_result(MYSQL *mysql);
MYSQL_ROW     mysql_fetch_row(MYSQL_RES *result);
MYSQL_FIELD  *mysql_fetch_fields(MYSQL_RES *result);
MYSQL_FIELD  *mysql_fetch_field_direct(MYSQL_RES *result, unsigned int fieldnr);
unsigned int  mysql_num_fields(MYSQL_RES *result);
my_ulonglong  mysql_num_rows(MYSQL_RES *result);
my_ulonglong  mysql_affected_rows(MYSQL *mysql);
void          mysql_free_result(MYSQL_RES *result);
unsigned long *mysql_fetch_lengths(MYSQL_RES *result);
int           mysql_set_character_set(MYSQL *mysql, const char *csname);
unsigned long mysql_real_escape_string(MYSQL *mysql, char *to, const char *from, unsigned long length);
int           mysql_autocommit(MYSQL *mysql, char auto_mode);
int           mysql_commit(MYSQL *mysql);
int           mysql_rollback(MYSQL *mysql);

