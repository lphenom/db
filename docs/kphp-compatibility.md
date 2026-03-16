# Совместимость с KPHP — lphenom/db

В этом документе описаны все KPHP-специфичные правила, применяемые в пакете `lphenom/db`,
и объяснены решения по проектированию, принятые для обеспечения совместимости с KPHP.

---

## Обзор

`lphenom/db` спроектирован для работы в **двух режимах**:

| Режим                      | Runtime             | Драйвер               |
|----------------------------|---------------------|-----------------------|
| PHP 8.1+ shared hosting    | `ext-pdo_mysql`     | `PdoMySqlConnection`  |
| KPHP compiled binary       | `FFI` + `libmysqlclient` | `FfiMySqlConnection` |

Один и тот же код репозиториев работает в обоих режимах — меняется только драйвер.

---

## Применённые правила KPHP (по файлам)

### `src/Contract/ConnectionInterface.php`

**Правило: нет `callable` в сигнатурах методов**

KPHP не может хранить или передавать `callable` в типизированных контекстах.

```php
// ❌ ЗАПРЕЩЕНО
public function transaction(callable $callback): mixed;

// ✅ ПРАВИЛЬНО
public function transaction(TransactionCallbackInterface $callback): mixed;
```

### `src/Contract/TransactionCallbackInterface.php`

Заменяет `callable` для транзакций. Реализуйте этот интерфейс вместо замыканий:

```php
$conn->transaction(new class ($data) implements TransactionCallbackInterface {
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data) { $this->data = $data; }

    public function execute(ConnectionInterface $conn): mixed
    {
        return $conn->execute('INSERT INTO ...', [':name' => ParamBinder::str($this->data['name'])]);
    }
});
```

### `src/Param/Param.php`

**Правило: нет constructor property promotion с `readonly`**

```php
// ❌ ЗАПРЕЩЕНО в KPHP
final class Param {
    public function __construct(
        public readonly mixed $value,
        public readonly int $type,
    ) {}
}

// ✅ ПРАВИЛЬНО
final class Param {
    public mixed $value;
    public int $type;

    public function __construct(mixed $value, int $type) {
        $this->value = $value;
        $this->type  = $type;
    }
}
```

### `src/Driver/PdoMySqlConnection.php`

**Правило: нет `callable`, нет `try/finally` без `catch`**

Паттерн транзакции:
```php
// ❌ ЗАПРЕЩЕНО
public function transaction(callable $callback): mixed {
    $this->pdo->beginTransaction();
    try {
        $result = $callback($this);
        $this->pdo->commit();
        return $result;
    } finally {               // ❌ try/finally без catch — запрещено
        $this->pdo->rollBack();
    }
}

// ✅ ПРАВИЛЬНО
public function transaction(TransactionCallbackInterface $callback): mixed {
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

**Замечание:** PDO **недоступен** в KPHP runtime. `PdoMySqlConnection` используется только
в PHP-режиме (shared hosting). KPHP-драйвер — `FfiMySqlConnection`.

### `src/Driver/FfiMySqlConnection.php`

**Применённые правила:**
1. Нет constructor property promotion / `readonly`
2. Нет `callable` — используется `TransactionCallbackInterface`
3. Нет `try/finally` без `catch` — восстановление autocommit происходит после блока catch
4. `FFI\Exception` перехватывается явно (наследует `\Error` в PHP 8.x)

```php
// Иерархия FFI\Exception: FFI.Exception extends \Error (PHP 8.x)
// Порядок catch: наиболее специфичные — первыми
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

**Как KPHP использует FFI:**

KPHP читает `FFI::cdef(string $header, string $lib)` **во время компиляции**.
C-объявления разбираются статически и компилируются в нативные C++ вызовы.
Результирующий бинарник линкуется с `libmysqlclient` напрямую — без накладных расходов `dlopen`.

Требования к KPHP FFI:
- `FFI::cdef()` должен получать **строковые литералы** (не динамические строки)
- C-заголовок должен быть полным и корректным
- Путь к библиотеке должен быть известен на этапе компиляции (или передаваться через конфиг)

### `src/Repository/AbstractRepository.php`

**Правило: нет возвращаемого типа `object` (не поддерживается в KPHP)**

```php
// ❌ ЗАПРЕЩЕНО
abstract protected function fromRow(array $row): object;

// ✅ ПРАВИЛЬНО — используйте mixed, переопределяйте с конкретным типом в подклассе
abstract protected function fromRow(array $row): mixed;
```

### `src/Migration/MigrationPlan.php`

**Правило: нет constructor property promotion / `readonly`**

Поля `MigrationPlan` публичные (не `readonly`) для совместимости с KPHP.

---

## KPHP entrypoint

KPHP не поддерживает Composer PSR-4 autoloading. Используйте `build/kphp-entrypoint.php`:

```bash
kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
```

Порядок файлов в entrypoint (интерфейсы и исключения — раньше классов):
1. `ResultInterface`, `ConnectionInterface`, `TransactionCallbackInterface`
2. Все исключения
3. `Param`, `ParamBinder`
4. `MigrationInterface`, `MigrationPlan`, `SchemaMigrations`
5. `AbstractRepository`
6. `FfiMySqlResult`, `FfiMySqlConnection`, `ConnectionFactory`

**Замечание:** `PdoMySqlConnection` и `PdoResult` **не** включены в KPHP entrypoint,
так как PDO недоступен в KPHP runtime.

---

## Проверка KPHP-сборки

```bash
# Собирает KPHP binary и PHAR, проверяет оба
make kphp-check
# или напрямую:
docker build -f Dockerfile.check -t lphenom-db-check .
```

`Dockerfile.check` содержит две стадии:
- **`kphp-build`** — компилирует через `vkcom/kphp`, запускает бинарник
- **`phar-build`** — собирает PHAR через PHP 8.1, smoke-тест

Обе стадии должны завершиться с кодом 0.

---

## Сводка запрещённых конструкций

| Конструкция                                             | Файл(ы)                                      | Замена                         |
|---------------------------------------------------------|----------------------------------------------|--------------------------------|
| `callable` как тип параметра                            | `ConnectionInterface`, все драйверы          | `TransactionCallbackInterface` |
| Constructor property promotion (`private readonly`)     | Все value objects, драйверы                  | Явное `private $prop` + присваивание в теле |
| `readonly` свойства                                     | `Param`, `MigrationPlan`, `AbstractRepository` | Обычные изменяемые свойства  |
| `try { } finally { }` без `catch`                       | `FfiMySqlConnection::transaction()`          | Сохранить исключение, проверить после блока |
| Возвращаемый тип `object`                               | `AbstractRepository::fromRow()`              | `mixed`                        |
| `str_starts_with()`, `str_ends_with()`, `str_contains()` | —                                           | `substr()` / `strpos()`        |
| `FFI\Exception` не перехватывается явно                 | `FfiMySqlConnection::__construct()`          | Явный `catch (\FFI\Exception $e)` |

---

## Ссылки

- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [KPHP FFI documentation](https://vkcom.github.io/kphp/kphp-language/howto-convert/ffi.html)
- [vkcom/kphp Docker image](https://hub.docker.com/r/vkcom/kphp)
- [drivers.md](./drivers.md)
- [repositories.md](./repositories.md)

