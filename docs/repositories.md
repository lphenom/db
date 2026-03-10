# Repository Pattern Guidelines

## Rule: All SQL lives in repositories

All database queries **must** be written inside repository classes that extend `AbstractRepository`.
No SQL is allowed in controllers, services, or domain models.

---

## AbstractRepository

```php
abstract class AbstractRepository
{
    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    // KPHP note: return type is `mixed` (not `object`) — override with concrete type in subclass
    abstract protected function fromRow(array $row): mixed;

    protected function fetchOne(string $sql, array $params = []): mixed;

    /** @return array<int, mixed> */
    protected function fetchAll(string $sql, array $params = []): array;

    protected function execute(string $sql, array $params = []): int;
    protected function query(string $sql, array $params = []): ResultInterface;
}
```

---

## DTO Rules

- DTOs are plain value-objects — **no business logic**.
- KPHP note: **no `readonly` properties**, **no constructor property promotion**.
- DTOs have a static `fromRow(array $row): self` factory method.
- Never inject services into DTOs.
- All type casts must be explicit: `(int)`, `(string)`, `(bool)`, `(float)`.

### Example DTO (KPHP-compatible)

```php
final class UserDto
{
    /** @var int */
    public int $id;
    /** @var string */
    public string $name;
    /** @var string */
    public string $email;
    /** @var bool */
    public bool $active;

    public function __construct(int $id, string $name, string $email, bool $active)
    {
        $this->id     = $id;
        $this->name   = $name;
        $this->email  = $email;
        $this->active = $active;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)    $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (bool)   $row['active'],
        );
    }
}
```

---

## Example Repository (KPHP-compatible)

```php
final class UserRepository extends AbstractRepository
{
    /**
     * @param array<string, mixed> $row
     * @return UserDto
     */
    protected function fromRow(array $row): mixed
    {
        return UserDto::fromRow($row);
    }

    public function findById(int $id): ?UserDto
    {
        /** @var UserDto|null $result */
        $result = $this->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int($id)],
        );
        return $result;
    }

    /** @return array<int, UserDto> */
    public function findActive(): array
    {
        /** @var array<int, UserDto> $results */
        $results = $this->fetchAll(
            'SELECT * FROM users WHERE active = :active ORDER BY name ASC',
            [':active' => ParamBinder::bool(true)],
        );
        return $results;
    }

    public function save(int $id, string $name, string $email): int
    {
        return $this->execute(
            'INSERT INTO users (id, name, email, active) VALUES (:id, :name, :email, :active)
             ON DUPLICATE KEY UPDATE name = :name, email = :email',
            [
                ':id'     => ParamBinder::int($id),
                ':name'   => ParamBinder::str($name),
                ':email'  => ParamBinder::str($email),
                ':active' => ParamBinder::bool(true),
            ],
        );
    }

    public function delete(int $id): int
    {
        return $this->execute(
            'DELETE FROM users WHERE id = :id',
            [':id' => ParamBinder::int($id)],
        );
    }
}
```

---

## Parameter Binding

Always use `ParamBinder` — **never interpolate** values into SQL strings.

| Method                    | PDO Type          | Notes                                     |
|---------------------------|-------------------|-------------------------------------------|
| `ParamBinder::int($v)`    | `PDO::PARAM_INT`  |                                           |
| `ParamBinder::str($v)`    | `PDO::PARAM_STR`  |                                           |
| `ParamBinder::bool($v)`   | `PDO::PARAM_BOOL` |                                           |
| `ParamBinder::null()`     | `PDO::PARAM_NULL` |                                           |
| `ParamBinder::float($v)`  | `PDO::PARAM_STR`  | PDO has no PARAM_FLOAT; stored as string  |

---

## Transactions

KPHP does not support `callable` as a parameter type.  
Always use `TransactionCallbackInterface` — **not** closures.

```php
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;

// ❌ FORBIDDEN (not KPHP-compatible)
// $conn->transaction(function (ConnectionInterface $conn) use ($data): void { ... });

// ✅ CORRECT (KPHP-compatible)
$conn->transaction(new class ($userRepo, $orderRepo) implements TransactionCallbackInterface {
    /** @var UserRepository */
    private UserRepository $userRepo;
    /** @var OrderRepository */
    private OrderRepository $orderRepo;

    public function __construct(UserRepository $u, OrderRepository $o)
    {
        $this->userRepo   = $u;
        $this->orderRepo  = $o;
    }

    public function execute(ConnectionInterface $conn): int|string|bool|float|null
    {
        $this->userRepo->save(1, 'Alice', 'alice@example.com');
        $this->orderRepo->createFor(1);
        return null;
        // Automatically committed; rolled back on any exception
    }
});
```

---

## KPHP Compatibility Checklist

| Rule | Status |
|------|--------|
| No `reflection`, `eval`, `variable variables` | ✅ |
| No dynamic method calls (`$method()`) | ✅ |
| No `callable` / `Closure` as parameter type | ✅ Use `TransactionCallbackInterface` |
| No constructor property promotion (`__construct(private T $x)`) | ✅ Explicit properties |
| No `readonly` properties | ✅ |
| `fromRow()` returns `mixed` (not `object`) | ✅ |
| All type casts explicit: `(int)`, `(string)`, `(bool)` | ✅ |
| `str_starts_with()`, `str_ends_with()`, `str_contains()` — **forbidden** | Use `strpos()` / `substr()` |
| `try/finally` without `catch` — **forbidden** | Add `catch (\Throwable $e)` |

---

## Driver Selection

Repositories depend only on `ConnectionInterface`. The concrete driver is chosen **once**
at application bootstrap via `ConnectionFactory`:

```php
use LPhenom\Db\Driver\ConnectionFactory;

$conn = ConnectionFactory::create([
    'driver'   => getenv('DB_DRIVER') ?: 'pdo_mysql',  // 'pdo_mysql' | 'ffi_mysql'
    'host'     => getenv('DB_HOST')   ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('DB_NAME')   ?: 'myapp',
    'user'     => getenv('DB_USER')   ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
]);

// Same repository works with both drivers:
$repo = new UserRepository($conn);
```

| Driver      | Environment                    | Requires                         |
|-------------|-------------------------------|----------------------------------|
| `pdo_mysql` | Shared hosting / standard PHP  | `ext-pdo_mysql`                  |
| `ffi_mysql` | KPHP compiled binary           | `ext-ffi` + `libmysqlclient`     |

See [drivers.md](./drivers.md) for full driver documentation.

