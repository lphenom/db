# Repository Pattern Guidelines

## Rule: All SQL lives in repositories

All database queries **must** be written inside repository classes that extend `AbstractRepository`.
No SQL is allowed in controllers, services, or domain models.

---

## AbstractRepository

```php
abstract class AbstractRepository
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
    ) {}

    abstract protected function fromRow(array $row): object;

    protected function fetchOne(string $sql, array $params = []): ?object;
    protected function fetchAll(string $sql, array $params = []): array;
    protected function execute(string $sql, array $params = []): int;
    protected function query(string $sql, array $params = []): ResultInterface;
}
```

---

## DTO Rules

- DTOs are plain value-objects — **no business logic**.
- All properties are `readonly`.
- DTOs have a static `fromRow(array $row): self` factory method.
- Never inject services into DTOs.

### Example DTO

```php
final class UserDto
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool   $active,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id:     (int)    $row['id'],
            name:   (string) $row['name'],
            email:  (string) $row['email'],
            active: (bool)   $row['active'],
        );
    }
}
```

---

## Example Repository

```php
final class UserRepository extends AbstractRepository
{
    protected function fromRow(array $row): object
    {
        return UserDto::fromRow($row);
    }

    public function findById(int $id): ?UserDto
    {
        /** @var UserDto|null */
        return $this->fetchOne(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int($id)],
        );
    }

    /** @return array<int, UserDto> */
    public function findActive(): array
    {
        /** @var array<int, UserDto> */
        return $this->fetchAll(
            'SELECT * FROM users WHERE active = :active ORDER BY name ASC',
            [':active' => ParamBinder::bool(true)],
        );
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

Use `ConnectionInterface::transaction()`:

```php
$conn->transaction(function (ConnectionInterface $conn) use ($userRepo, $orderRepo): void {
    $userRepo->save(1, 'Alice', 'alice@example.com');
    $orderRepo->createFor(1);
    // Automatically committed; rolled back on any exception
});
```

---

## KPHP Compatibility

- No `reflection`, `eval`, `variable variables`
- No dynamic method calls (`$method()`)
- `fromRow()` must use explicit property assignments — no array_map magic
- All type casts must be explicit: `(int)`, `(string)`, `(bool)`, `(float)`

