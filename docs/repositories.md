# Репозитории

## Правило: весь SQL — в репозиториях

Все запросы к базе данных **должны** быть написаны внутри классов-репозиториев,
расширяющих `AbstractRepository`.
SQL запрещён в контроллерах, сервисах и доменных моделях.

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

    // Замечание KPHP: возвращаемый тип `mixed` (не `object`) — переопределяйте с конкретным типом в подклассе
    abstract protected function fromRow(array $row): mixed;

    protected function fetchOne(string $sql, array $params = []): mixed;

    /** @return array<int, mixed> */
    protected function fetchAll(string $sql, array $params = []): array;

    protected function execute(string $sql, array $params = []): int;
    protected function query(string $sql, array $params = []): ResultInterface;
}
```

---

## Правила DTO

- DTO — простые объекты-значения, **без бизнес-логики**.
- Замечание KPHP: **запрещено** constructor property promotion и `readonly` свойства.
- У DTO есть статический фабричный метод `fromRow(array $row): self`.
- Никогда не внедряйте сервисы в DTO.
- Все приведения типов должны быть явными: `(int)`, `(string)`, `(bool)`, `(float)`.

### Пример DTO (KPHP-совместимый)

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

## Пример репозитория (KPHP-совместимый)

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

## Привязка параметров

Всегда используйте `ParamBinder` — **никогда** не интерполируйте значения напрямую в SQL.

 Метод                      | Тип PDO            | Примечания
-----------------------------|--------------------|------------------------------------------------
 `ParamBinder::int($v)`      | `PDO::PARAM_INT`   |
 `ParamBinder::str($v)`      | `PDO::PARAM_STR`   |
 `ParamBinder::bool($v)`     | `PDO::PARAM_BOOL`  |
 `ParamBinder::null()`       | `PDO::PARAM_NULL`  |
 `ParamBinder::float($v)`    | `PDO::PARAM_STR`   | PDO не имеет PARAM_FLOAT; хранится как строка

---

## Транзакции

KPHP не поддерживает `callable` как тип параметра.
Всегда используйте `TransactionCallbackInterface` — **не** замыкания.

```php
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;

// ❌ ЗАПРЕЩЕНО (несовместимо с KPHP)
// $conn->transaction(function (ConnectionInterface $conn) use ($data): void { ... });

// ✅ ПРАВИЛЬНО (KPHP-совместимо)
$conn->transaction(new class ($userRepo, $orderRepo) implements TransactionCallbackInterface {
    /** @var UserRepository */
    private UserRepository $userRepo;
    /** @var OrderRepository */
    private OrderRepository $orderRepo;

    public function __construct(UserRepository $u, OrderRepository $o)
    {
        $this->userRepo  = $u;
        $this->orderRepo = $o;
    }

    public function execute(ConnectionInterface $conn): mixed
    {
        $this->userRepo->save(1, 'Alice', 'alice@example.com');
        $this->orderRepo->createFor(1);
        return null;
        // При успехе — автоматический commit; при любом исключении — rollback
    }
});
```

---

## Чеклист совместимости с KPHP

 Правило                                                                          | Статус
----------------------------------------------------------------------------------|-------
 Нет `reflection`, `eval`, `variable variables`                                   | ✅
 Нет динамических вызовов методов (`$method()`)                                   | ✅
 Нет `callable` / `Closure` как типа параметра                                    | ✅ используйте `TransactionCallbackInterface`
 Нет constructor property promotion (`__construct(private T $x)`)                 | ✅ явные свойства
 Нет `readonly` свойств                                                           | ✅
 `fromRow()` возвращает `mixed` (не `object`)                                     | ✅
 Все приведения типов явные: `(int)`, `(string)`, `(bool)`                        | ✅
 `str_starts_with()`, `str_ends_with()`, `str_contains()` — **запрещены**         | используйте `strpos()` / `substr()`
 `try/finally` без `catch` — **запрещено**                                        | добавьте `catch (\Throwable $e)`

---

## Выбор драйвера

Репозитории зависят только от `ConnectionInterface`. Конкретный драйвер выбирается **один раз**
при старте приложения через `ConnectionFactory`:

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

// Один и тот же репозиторий работает с обоими драйверами:
$repo = new UserRepository($conn);
```

 Драйвер      | Окружение                     | Требования
--------------|-------------------------------|-------------------------------
 `pdo_mysql`  | Shared hosting / обычный PHP  | `ext-pdo_mysql`
 `ffi_mysql`  | KPHP compiled binary          | `ext-ffi` + `libmysqlclient`

Подробная документация по драйверам — в [drivers.md](./drivers.md).
