# Drivers & ConnectionFactory

## Overview

`lphenom/db` ships two MySQL drivers that share the same `ConnectionInterface` API.
Your repository code never references a specific driver — it depends only on the interface.
Driver selection happens **once** at the application bootstrap via `ConnectionFactory`.

```
Application Bootstrap
        │
        ▼
ConnectionFactory::create($config)
        │
        ├─── driver = "pdo_mysql"  ──►  PdoMySqlConnection   (PDO extension)
        │
        └─── driver = "ffi_mysql"  ──►  FfiMySqlConnection   (FFI + libmysqlclient)
                                               │
                                        FfiMySqlResult
```

---

## PdoMySqlConnection — shared hosting / standard PHP

Uses PHP's built-in PDO extension with named prepared statements.

### When to use

- Traditional shared hosting (Apache/Nginx + PHP-FPM)
- Any environment where `ext-pdo_mysql` is available
- Development and testing (also supports `sqlite::memory:` DSN for fast unit tests)

### Construction

```php
use LPhenom\Db\Driver\PdoMySqlConnection;

$conn = new PdoMySqlConnection(
    dsn:      'mysql:host=127.0.0.1;port=3306;dbname=myapp;charset=utf8mb4',
    username: 'myuser',
    password: 'secret',
);
```

### Parameter binding

PDO uses `PDOStatement::bindValue` with `PDO::PARAM_*` constants — **no string interpolation**.

---

## FfiMySqlConnection — KPHP compiled mode

Uses PHP's `FFI` extension to call `libmysqlclient` (MySQL C client library) directly.

### Why FFI for KPHP?

KPHP compiles PHP source to C++ and then to a native binary.
The resulting binary does not include the PDO extension.
FFI declarations in PHP source code are read by the KPHP compiler at compile time —
it generates direct `dlopen` + native C calls in the output binary.

This means **the same PHP source file** with `FFI::cdef(...)` works in two ways:
- In regular PHP: via the `ext-ffi` extension at runtime
- In KPHP binary: compiled to native C function calls with zero overhead

### How KPHP FFI works (step by step)

```
PHP source with FFI::cdef(C_HEADER, 'libmysqlclient.so.21')
        │
        ├─── PHP runtime (ext-ffi)
        │         FFI loads the shared library at runtime via dlopen.
        │         C functions are called through libffi trampoline.
        │
        └─── KPHP compiler (kphp --mode=server)
                  KPHP reads the C declarations at compile time.
                  Generates direct native C calls in the output .cpp.
                  Final binary is linked against libmysqlclient.
                  No dlopen overhead — fully compiled & inlined.
```

### KPHP FFI rules (must follow for KPHP compatibility)

| Rule | Why |
|------|-----|
| `FFI::cdef()` must receive a **string literal** | KPHP needs to parse it at compile time |
| No `FFI::load()` with dynamic paths | Path must be deterministic |
| C header string must not be built dynamically | KPHP parses it statically |
| `FFI\CData` variables must not be stored in generic arrays | KPHP has strict typing for CData |

### Parameter escaping

MySQL C API does not support named prepared statements.
`FfiMySqlConnection` substitutes `:param` placeholders with escaped values:

| `Param::$type` | PDO constant | Escaping strategy |
|----------------|--------------|-------------------|
| `0` | `PDO::PARAM_NULL` | SQL `NULL` literal |
| `1` | `PDO::PARAM_INT`  | `(int)` cast, no quoting |
| `2` | `PDO::PARAM_STR`  | `mysql_real_escape_string` + single-quoted |
| `5` | `PDO::PARAM_BOOL` | `1` or `0` literal |

Float values (stored with `PDO::PARAM_STR` by `ParamBinder::float()`) go through
`mysql_real_escape_string` like any string.

### System requirement

`libmysqlclient.so.21` must be present on the system.

On Debian/Ubuntu:
```bash
apt-get install libmysqlclient-dev
```

On Alpine Linux:
```bash
apk add mysql-client mysql-dev
```

The library path can be customized via the `ffi_lib` config key.

### Construction

```php
use LPhenom\Db\Driver\FfiMySqlConnection;

$conn = new FfiMySqlConnection(
    host:     '127.0.0.1',
    user:     'myuser',
    password: 'secret',
    database: 'myapp',
    port:     3306,
    libPath:  'libmysqlclient.so.21',   // optional
);
```

---

## ConnectionFactory — driver selection from config

`ConnectionFactory::create(array $config)` is the **recommended** way to instantiate a connection.
It is the single point where the driver is selected — repositories never change.

### Config shape

```php
$config = [
    'driver'   => 'pdo_mysql',   // 'pdo_mysql' | 'ffi_mysql'
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'myapp',
    'user'     => 'root',
    'password' => 'secret',
    // ffi_mysql only:
    'ffi_lib'  => 'libmysqlclient.so.21',   // optional
];

$conn = ConnectionFactory::create($config);
```

### Switching from PDO to FFI

```diff
- 'driver' => 'pdo_mysql',
+ 'driver' => 'ffi_mysql',
```

That's it. All repositories work unchanged.

### Environment-based config example

```php
$config = [
    'driver'   => getenv('DB_DRIVER') ?: 'pdo_mysql',
    'host'     => getenv('DB_HOST')   ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('DB_NAME')   ?: 'myapp',
    'user'     => getenv('DB_USER')   ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
];

$conn = ConnectionFactory::create($config);
```

---

## ConnectionInterface — the unified API

Both drivers implement exactly the same interface:

```php
interface ConnectionInterface
{
    /** Execute a SELECT — returns ResultInterface */
    public function query(string $sql, array $params = []): ResultInterface;

    /** Execute INSERT/UPDATE/DELETE — returns affected row count */
    public function execute(string $sql, array $params = []): int;

    /** Run callable in a transaction; commits on success, rolls back on exception */
    public function transaction(callable $callback): mixed;
}
```

Repository code uses **only** this interface:

```php
final class UserRepository extends AbstractRepository
{
    public function findById(int $id): ?UserDto
    {
        // Works identically with PdoMySqlConnection and FfiMySqlConnection
        return $this->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int($id)],
        );
    }
}
```

---

## ParamBinder — type-safe parameter creation

Always use `ParamBinder` — never interpolate values into SQL strings.

```php
use LPhenom\Db\Param\ParamBinder;

[
    ':id'     => ParamBinder::int(42),
    ':name'   => ParamBinder::str('Alice'),
    ':active' => ParamBinder::bool(true),
    ':score'  => ParamBinder::float(9.75),
    ':notes'  => ParamBinder::null(),
]
```

`Param` is a simple value-object: `value` + `type` (PDO::PARAM_* integer).
Both drivers read these same fields — PDO uses `bindValue`, FFI uses `escapeSingleParam`.

---

## Testing strategy

| Suite | Command | Driver used | DB required |
|-------|---------|-------------|-------------|
| Unit | `make test-unit` | SQLite in-memory (via PDO) | ❌ |
| Integration — PDO | `make test-integration` | PdoMySqlConnection | ✅ MySQL |
| Integration — FFI | `SKIP_FFI_TESTS=0 make test-integration` | FfiMySqlConnection | ✅ MySQL + libmysqlclient |

FFI integration tests auto-skip when:
- `SKIP_FFI_TESTS=1` (default in docker-compose)
- `ext-ffi` is not loaded
- `libmysqlclient` is not found

