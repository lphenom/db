# Миграции

## Обзор

`lphenom/db` предоставляет **только контракты** — CLI-инструмент для запуска миграций реализован
в отдельном пакете. Данный пакет определяет интерфейсы и вспомогательный класс
для таблицы отслеживания `schema_migrations`.

---

## MigrationInterface

Каждая миграция должна реализовывать:

```php
interface MigrationInterface
{
    public function up(ConnectionInterface $conn): void;
    public function down(ConnectionInterface $conn): void;
    public function getVersion(): string;
}
```

- `up()` — применяет миграцию (CREATE TABLE, ALTER TABLE, INSERT seed data и т.д.)
- `down()` — откатывает её (DROP TABLE, DROP COLUMN и т.д.)
- `getVersion()` — возвращает строковый идентификатор, по соглашению — временну́ю метку: `"20260101120000"`

---

## Пример миграции

```php
final class CreateUsersTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20260101120000';
    }

    public function up(ConnectionInterface $conn): void
    {
        $conn->execute('
            CREATE TABLE IF NOT EXISTS users (
                id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                email      VARCHAR(255) NOT NULL UNIQUE,
                active     TINYINT(1)   NOT NULL DEFAULT 1,
                created_at DATETIME     NOT NULL
            )
        ');
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS users');
    }
}
```

---

## DTO MigrationPlan

`MigrationPlan` — иммутабельный DTO, используемый инструментами миграций для отслеживания состояния:

```php
$plan = new MigrationPlan(
    version:   '20260101120000',
    name:      'CreateUsersTable',
    appliedAt: null,                // null = ещё не применена
);

$applied = $plan->withAppliedAt(new \DateTimeImmutable());
$applied->isApplied(); // true
```

---

## Вспомогательный класс SchemaMigrations

`SchemaMigrations` управляет таблицей отслеживания `schema_migrations`:

```php
$schema = new SchemaMigrations($conn);

// Убедиться, что таблица отслеживания существует (идемпотентно)
$schema->ensureTable();

// Отметить миграцию как применённую
$schema->markApplied('20260101120000', 'CreateUsersTable');

// Получить все применённые версии в порядке возрастания
$versions = $schema->getApplied(); // ['20260101120000', ...]

// Откат: удалить запись версии
$schema->markReverted('20260101120000');
```

### DDL таблицы schema_migrations

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    VARCHAR(255) NOT NULL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    applied_at DATETIME     NOT NULL
);
```

---

## Как будет работать инструмент миграций

> CLI-runner будет реализован в отдельном пакете `lphenom/migrate`.

Ожидаемый сценарий:

1. **Обнаружение** классов миграций (регистрируются явно — без сканирования файловой системы).
2. **Сравнение** обнаруженных версий с `schema_migrations.getApplied()`.
3. **Планирование** — какие миграции запустить (`up` для новых, `down` для отката).
4. **Выполнение** каждой миграции внутри транзакции (где возможно).
5. **Запись** применённых/откатанных версий через `SchemaMigrations`.

---

## Команды разработки

```bash
# Запуск окружения
make up

# Запуск тестов (включая тесты SchemaMigrations на SQLite in-memory)
make test

# Проверка стиля кода
make lint
```

---

## Совместимость с KPHP

- Классы миграций должны регистрироваться **явно** — никакого сканирования директорий и Reflection.
- Весь DDL-SQL — обычные строки, без query builder.
- `SchemaMigrations` использует `ParamBinder` для всех привязанных значений.
