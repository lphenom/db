# KPHP Compatibility — lphenom/db

This document describes all KPHP-specific rules applied in the `lphenom/db` package
and explains every design decision made for KPHP compatibility.

---

## Overview

`lphenom/db` is designed to work in **two modes**:

| Mode | Runtime | Driver |
|------|---------|--------|
| PHP 8.1+ shared hosting | `ext-pdo_mysql` | `PdoMySqlConnection` |
| KPHP compiled binary | `FFI` + `libmysqlclient` | `FfiMySqlConnection` |

The same repository code works in both modes — only the driver changes.

---

## Applied KPHP rules (per file)

### `src/Contract/ConnectionInterface.php`

**Rule: no `callable` in method signatures**

KPHP cannot store or pass `callable` in typed contexts.

```php
// ❌ FORBIDDEN
public function transaction(callable $callback): mixed;

// ✅ CORRECT
public function transaction(TransactionCallbackInterface $callback): int|string|bool|float|null;
```

### `src/Contract/TransactionCallbackInterface.php`

Replaces `callable` for transactions. Implement this interface instead of using closures:

```php
$conn->transaction(new class ($data) implements TransactionCallbackInterface {
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data) { $this->data = $data; }

    public function execute(ConnectionInterface $conn): int|string|bool|float|null
    {
        return $conn->execute('INSERT INTO ...', [':name' => ParamBinder::str($this->data['name'])]);
    }
});
```

### `src/Param/Param.php`

**Rule: no constructor property promotion with `readonly`**

```php
// ❌ FORBIDDEN in KPHP
final class Param {
    public function __construct(
        public readonly int|string|bool|float|null $value,
        public readonly int $type,
    ) {}
}

// ✅ CORRECT
final class Param {
    public int|string|bool|float|null $value;
    public int $type;

    public function __construct(int|string|bool|float|null $value, int $type) {
        $this->value = $value;
        $this->type  = $type;
    }
}
```

### `src/Driver/PdoMySqlConnection.php`

**Rule: no `callable`, no `try/finally` without `catch`**

Transaction pattern:
```php
// ❌ FORBIDDEN
public function transaction(callable $callback): mixed {
    $this->pdo->beginTransaction();
    try {
        $result = $callback($this);
        $this->pdo->commit();
        return $result;
    } finally {               // ❌ try/finally without catch is forbidden
        $this->pdo->rollBack();
    }
}

// ✅ CORRECT
public function transaction(TransactionCallbackInterface $callback): int|string|bool|float|null {
    $this->pdo->beginTransaction();
    $exception = null;
    $result = null;
    try {
        $result = $callback->execute($this);
        $this->pdo->commit();
    } catch (\Throwable $e) {
        $exception = $e;
        $this->pdo->rollBack();
    }
    if ($exception !== null) {
        throw $exception;
    }
    return $result;
}
```

**Note:** PDO is NOT available in KPHP runtime. `PdoMySqlConnection` is used only in
PHP mode (shared hosting). The KPHP driver is `FfiMySqlConnection`.

### `src/Driver/FfiMySqlConnection.php`

**Rules applied:**
1. No constructor property promotion / `readonly`
2. No `callable` — uses `TransactionCallbackInterface`
3. No `try/finally` without `catch` — autocommit restore happens after catch block
4. `FFI\Exception` caught explicitly (extends `\Error` on PHP 8.x)

```php
// FFI\Exception hierarchy: FFI\Exception extends \Error (PHP 8.x)
// Catch order: most specific first
try {
    $this->ffi = FFI::cdef(self::C_HEADER, $libPath);
} catch (\FFI\Exception $e) {
    $ffiException = new ConnectionException('...', 0, $e);
} catch (\Error $e) {
    $ffiException = new ConnectionException('...');
} catch (\Exception $e) {
    $ffiException = new ConnectionException('...', 0, $e);
}
if ($ffiException !== null) {
    throw $ffiException;
}
```

**How KPHP uses FFI:**

KPHP reads `FFI::cdef(string $header, string $lib)` **at compile time**.
The C declarations are parsed statically and compiled into native C++ calls.
The resulting binary links against `libmysqlclient` directly — no `dlopen` overhead.

Requirements for KPHP FFI:
- `FFI::cdef()` must receive **string literals** (not dynamic strings)
- The C header must be complete and valid
- Library path must be known at compile time (or passed via config)

### `src/Repository/AbstractRepository.php`

**Rule: no `object` return type (not supported in KPHP)**

```php
// ❌ FORBIDDEN
abstract protected function fromRow(array $row): object;

// ✅ CORRECT — use mixed, override with concrete type in subclass
abstract protected function fromRow(array $row): mixed;
```

### `src/Migration/MigrationPlan.php`

**Rule: no constructor property promotion / `readonly`**

`MigrationPlan` fields are `public` (not `readonly`) for KPHP compatibility.

---

## KPHP entrypoint

KPHP does not support Composer PSR-4 autoloading. Use `build/kphp-entrypoint.php`:

```bash
kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
```

File order in entrypoint (interfaces and exceptions before classes):
1. `ResultInterface`, `ConnectionInterface`, `TransactionCallbackInterface`
2. All exceptions
3. `Param`, `ParamBinder`
4. `MigrationInterface`, `MigrationPlan`, `SchemaMigrations`
5. `AbstractRepository`
6. `FfiMySqlResult`, `FfiMySqlConnection`, `FfiConnectionStub`, `ConnectionFactory`

**Note:** `PdoMySqlConnection` and `PdoResult` are **not** included in the KPHP entrypoint
because PDO is unavailable in KPHP runtime.

---

## KPHP build verification

```bash
# Builds both KPHP binary and PHAR, verifies both work
make kphp-check
# or directly:
docker build -f Dockerfile.check -t lphenom-db-check .
```

`Dockerfile.check` has two stages:
- **`kphp-build`** — compiles via `vkcom/kphp`, runs the binary
- **`phar-build`** — builds PHAR with PHP 8.1, smoke-tests it

Both must exit 0 for the check to pass.

---

## Forbidden constructs summary

| Construct | File(s) | Replacement |
|-----------|---------|-------------|
| `callable` parameter type | `ConnectionInterface`, all drivers | `TransactionCallbackInterface` |
| Constructor property promotion (`private readonly`) | All value objects, drivers | Explicit `private $prop` + assignment in body |
| `readonly` properties | `Param`, `MigrationPlan`, `AbstractRepository` | Regular mutable properties |
| `try { } finally { }` without `catch` | `FfiMySqlConnection::transaction()` | Store exception, check after block |
| `object` return type | `AbstractRepository::fromRow()` | `mixed` |
| `str_starts_with()`, `str_ends_with()`, `str_contains()` | — | `substr()` / `strpos()` |
| `FFI\Exception` not caught explicitly | `FfiMySqlConnection::__construct()` | Explicit `catch (\FFI\Exception $e)` |

---

## References

- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [KPHP FFI documentation](https://vkcom.github.io/kphp/kphp-language/howto-convert/ffi.html)
- [vkcom/kphp Docker image](https://hub.docker.com/r/vkcom/kphp)
- [drivers.md](./drivers.md)
- [repositories.md](./repositories.md)

