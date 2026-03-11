# Build Targets — lphenom/db

Этот документ описывает **систему меток build-target** для LPhenom Builder.

Builder компилирует монолит в два режима:
- **`shared`** — PHP runtime (shared hosting, Apache/Nginx + PHP-FPM, PDO доступен)
- **`kphp`** — KPHP binary (компилированный статический бинарник, PDO недоступен, используется FFI)

Каждый файл пакета помечен в `@lphenom-build` аннотации заголовочного DocBlock.
Builder читает эти метки и решает, включать файл или нет.

---

## Метки `@lphenom-build`

| Метка | shared | kphp | Описание |
|-------|:------:|:----:|---------|
| `@lphenom-build shared,kphp` | ✅ | ✅ | Включается в оба режима (интерфейсы, контракты, value objects) |
| `@lphenom-build shared` | ✅ | ❌ | Только PHP runtime (PDO, ext-dependent) |
| `@lphenom-build kphp` | ❌ | ✅ | Только KPHP binary (FFI-драйвер) |
| `@lphenom-build none` | ❌ | ❌ | Никогда не включается (dev-инструменты) |

---

## Карта файлов lphenom/db

### ✅ shared + kphp — `@lphenom-build shared,kphp`

Эти файлы включаются в **оба** режима. Не зависят от PDO или ext-*, совместимы с KPHP.

| Файл | Описание |
|------|----------|
| `src/Exception/ConnectionException.php` | Ошибка подключения к БД |
| `src/Exception/NotImplementedException.php` | Заглушка для нереализованных методов |
| `src/Exception/QueryException.php` | Ошибка выполнения SQL-запроса |
| `src/Contract/ConnectionInterface.php` | Публичный API соединения с БД |
| `src/Contract/ResultInterface.php` | Публичный API результирующего набора |
| `src/Contract/TransactionCallbackInterface.php` | Callback-контракт для транзакций |
| `src/Param/Param.php` | Value object параметра запроса |
| `src/Param/ParamBinder.php` | Хелпер типобезопасного биндинга параметров |
| `src/Repository/AbstractRepository.php` | Базовый класс репозитория (зависит только от контрактов) |
| `src/Migration/MigrationInterface.php` | Контракт миграции (up/down) |
| `src/Migration/MigrationPlan.php` | DTO плана миграции (version, name, appliedAt) |
| `src/Migration/SchemaMigrations.php` | Хелпер таблицы schema_migrations (DDL + DML) |
| `src/Driver/FfiConnectionStub.php` | Заглушка FFI-соединения (throws NotImplemented) — нужна для компиляции |
| `src/Driver/FfiMySqlResult.php` | ResultInterface на базе FFI MYSQL_RES* |
| `src/Driver/FfiMySqlConnection.php` | FFI MySQL-драйвер через libmysqlclient |

> **Примечание о FFI-файлах:** `FfiConnectionStub`, `FfiMySqlResult` и `FfiMySqlConnection` входят в `shared,kphp`, потому что:
> - В KPHP они являются **основным драйвером** (PDO отсутствует).
> - В shared PHP runtime они **работают** при наличии `ext-ffi` и `libmysqlclient`, либо `FfiConnectionStub` используется как безопасная заглушка.
> - Код совместим с KPHP (FFI — одна из немногих системных интеграций, поддерживаемых KPHP нативно).

### ⚠️ shared only — `@lphenom-build shared`

Эти файлы включаются **только в PHP runtime**. Зависят от PDO (`ext-pdo`, `ext-pdo_mysql`).
В KPHP binary их нет — используется `FfiMySqlConnection` вместо `PdoMySqlConnection`.

| Файл | Причина исключения из KPHP |
|------|---------------------------|
| `src/Driver/PdoMySqlConnection.php` | Зависит от `\PDO` и `\PDOStatement` (ext-pdo_mysql) — PDO недоступен в KPHP |
| `src/Driver/PdoResult.php` | Зависит от `\PDOStatement` (ext-pdo_mysql) — PDO недоступен в KPHP |
| `src/Driver/ConnectionFactory.php` | Создаёт `PdoMySqlConnection` и `FfiMySqlConnection` по конфигу — в KPHP соединение создаётся напрямую через `FfiMySqlConnection` |

> **Почему `ConnectionFactory` только shared:** Factory содержит `match`/`if` ветки для выбора `pdo_mysql` vs `ffi_mysql`. KPHP-монолит не нуждается в динамическом выборе драйвера — драйвер фиксирован на этапе компиляции. Builder генерирует KPHP entrypoint, где соединение создаётся явно.

---

## Как Builder читает метки

Builder (пакет `lphenom/build`, будущий) сканирует PHP-файлы и извлекает `@lphenom-build` из DocBlock класса:

```php
// Пример чтения метки без Reflection:
// Builder читает файл как текст через file() и ищет @lphenom-build через strpos().
// Никакого Reflection — Builder сам KPHP-compatible.

$lines = file($filePath); // только 1 аргумент — KPHP-friendly
$content = implode('', (array) $lines);
$hasBuildTag = strpos($content, '@lphenom-build') !== false;
```

### Пример DocBlock с меткой

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build shared,kphp
 *
 * Connection contract — KPHP-compatible.
 */
interface ConnectionInterface
{
    // ...
}
```

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build shared
 *
 * PDO MySQL connection — PHP runtime only.
 * NOT included in KPHP binary — use FfiMySqlConnection instead.
 */
final class PdoMySqlConnection implements ConnectionInterface
{
    // ...
}
```

```php
<?php
declare(strict_types=1);

/**
 * @lphenom-build shared,kphp
 *
 * FFI MySQL connection — works in both PHP (with ext-ffi) and KPHP binary.
 * Primary driver for KPHP compiled mode.
 */
final class FfiMySqlConnection implements ConnectionInterface
{
    // ...
}
```

---

## Как Builder генерирует KPHP entrypoint

Builder обходит все файлы пакета, фильтрует по `@lphenom-build shared,kphp` или `@lphenom-build kphp`, и генерирует `require_once` список в порядке зависимостей:

```php
// Сгенерированный Builder-ом фрагмент build/kphp-entrypoint.php для монолита

// lphenom/db — только KPHP-совместимые файлы
// Порядок: исключения → контракты → param → migration → repository → ffi-драйверы
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/ConnectionException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/NotImplementedException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/QueryException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationPlan.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/SchemaMigrations.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Repository/AbstractRepository.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiConnectionStub.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiMySqlResult.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Driver/FfiMySqlConnection.php';

// Файлы с @lphenom-build shared — пропускаются:
//   src/Driver/PdoMySqlConnection.php  (PDO недоступен в KPHP)
//   src/Driver/PdoResult.php           (PDOStatement недоступен в KPHP)
//   src/Driver/ConnectionFactory.php   (динамический выбор драйвера не нужен в KPHP)
```

---

## Как Builder собирает shared PHAR

Для shared PHAR Builder включает файлы с `@lphenom-build shared` и `@lphenom-build shared,kphp`.
Файлы с `@lphenom-build kphp` и `@lphenom-build none` — исключаются.

```
@lphenom-build shared,kphp  → включить в PHAR
@lphenom-build shared       → включить в PHAR
@lphenom-build kphp         → исключить из PHAR
@lphenom-build none         → исключить из PHAR
```

В shared PHAR входят все файлы пакета `lphenom/db` — и PDO-, и FFI-драйверы.
`ConnectionFactory` выбирает конкретный драйвер в runtime по конфигу.

---

## Связь с composer.json

Метки `@lphenom-build` работают **совместно** с `composer.json` autoload-изоляцией.
Это два независимых уровня защиты:

| Уровень | Механизм | Защищает от |
|---------|----------|-------------|
| 1 | `composer.json` PSR-4 autoload (точечные неймспейсы) | Только нужные namespace-пути в автозагрузке |
| 2 | `@lphenom-build` метки в DocBlock | Builder явно фильтрует файлы по режиму сборки |

В `lphenom/db` нет dev-only CLI инструментов (`@lphenom-build none`), поэтому два уровня достаточны.

---

## Таблица быстрого референса

```
Файл                                          | shared | kphp
----------------------------------------------|--------|------
src/Exception/ConnectionException.php         |   ✅   |  ✅
src/Exception/NotImplementedException.php     |   ✅   |  ✅
src/Exception/QueryException.php              |   ✅   |  ✅
src/Contract/ConnectionInterface.php          |   ✅   |  ✅
src/Contract/ResultInterface.php              |   ✅   |  ✅
src/Contract/TransactionCallbackInterface.php |   ✅   |  ✅
src/Param/Param.php                           |   ✅   |  ✅
src/Param/ParamBinder.php                     |   ✅   |  ✅
src/Repository/AbstractRepository.php        |   ✅   |  ✅
src/Migration/MigrationInterface.php          |   ✅   |  ✅
src/Migration/MigrationPlan.php               |   ✅   |  ✅
src/Migration/SchemaMigrations.php            |   ✅   |  ✅
src/Driver/FfiConnectionStub.php              |   ✅   |  ✅  (заглушка / компиляционная совместимость)
src/Driver/FfiMySqlResult.php                 |   ✅   |  ✅  (FFI MYSQL_RES*)
src/Driver/FfiMySqlConnection.php             |   ✅   |  ✅  (основной драйвер в KPHP)
src/Driver/PdoResult.php                      |   ✅   |  ❌  (PDOStatement — нет в KPHP)
src/Driver/PdoMySqlConnection.php             |   ✅   |  ❌  (PDO — нет в KPHP)
src/Driver/ConnectionFactory.php              |   ✅   |  ❌  (динамический выбор — не нужен в KPHP)
```

---

## Правило выбора драйвера в KPHP-монолите

В KPHP binary соединение создаётся **явно** в точке входа приложения — без `ConnectionFactory`:

```php
// В KPHP entrypoint / bootstrap монолита:
$connection = new \LPhenom\Db\Driver\FfiMySqlConnection(
    host: '127.0.0.1',
    port: 3306,
    dbname: 'myapp',
    user: 'root',
    password: 'secret',
    libPath: '/usr/lib/x86_64-linux-gnu/libmysqlclient.so.21',
);
```

В shared PHP runtime — через `ConnectionFactory` по конфигу:

```php
// В shared / bootstrap:
$connection = \LPhenom\Db\Driver\ConnectionFactory::create([
    'driver'   => 'pdo_mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'myapp',
    'user'     => 'root',
    'password' => 'secret',
]);
```

Оба варианта возвращают `ConnectionInterface` — **API репозитория одинаков в обоих режимах**.

---

## Ссылки

- [KPHP FFI Documentation](https://vkcom.github.io/kphp/kphp-language/ffi.html)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [docs/kphp-compatibility.md](./kphp-compatibility.md) — полный список ограничений KPHP
- [docs/repositories.md](./repositories.md) — правила написания репозиториев
- [docs/drivers.md](./drivers.md) — документация по драйверам PDO и FFI

