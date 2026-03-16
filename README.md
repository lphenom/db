# lphenom/db

[![CI](https://github.com/lphenom/db/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/db/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**LPhenom Database Layer** — сырой SQL, паттерн репозитория, PDO-драйвер, контракты миграций.

Часть экосистемы PHP-фреймворка [LPhenom](https://github.com/lphenom) — совместим с PHP 8.1+ и KPHP-компиляцией.

---

## Возможности

- 🔌 `ConnectionInterface` / `ResultInterface` — чистые контракты
- 🛡 Типобезопасная привязка параметров (`int`, `string`, `bool`, `null`, `float`)
- 🗄 `PdoMySqlConnection` — PDO MySQL-драйвер для shared hosting
- ⚡ `FfiMySqlConnection` — KPHP FFI MySQL-драйвер (libmysqlclient, compiled mode)
- 🔀 `ConnectionFactory` — единый конфигурируемый выбор драйвера (`pdo_mysql` | `ffi_mysql`)
- 📁 `AbstractRepository` + паттерн DTO (без ORM-магии)
- 🔄 `MigrationInterface` + вспомогательный класс `SchemaMigrations`
- ✅ Unit-тесты (SQLite in-memory) + интеграционные тесты (реальный MySQL)
- 🐳 Docker dev-окружение (PHP 8.1-alpine, MySQL 8.0.36)

---

## Требования

- PHP >= 8.1
- `ext-pdo` + `ext-pdo_mysql` — для `PdoMySqlConnection` (shared hosting / обычный PHP)
- `ext-ffi` + `libmysqlclient` — для `FfiMySqlConnection` (KPHP compiled mode)

---

## Установка

```bash
composer require lphenom/db
```

---

## Быстрый старт

```php
use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Param\ParamBinder;

$conn = new PdoMySqlConnection('mysql:host=localhost;dbname=mydb', 'user', 'password');

$result = $conn->query(
    'SELECT * FROM users WHERE id = :id AND active = :active',
    [
        ':id'     => ParamBinder::int(42),
        ':active' => ParamBinder::bool(true),
    ]
);

$user = $result->fetchOne();
```

---

## Разработка

```bash
git clone git@github.com:lphenom/db.git
cd db
make up                  # запустить Docker (PHP 8.1-alpine + MySQL 8.0.36)
make test-unit           # запустить unit-тесты (без реальной БД)
make test-integration    # запустить интеграционные тесты с MySQL
make lint                # проверить стиль кода (php-cs-fixer)
make phpstan             # запустить PHPStan
make down                # остановить Docker
```

---

## Документация

- [Паттерн репозитория](docs/repositories.md)
- [Контракты миграций](docs/migrations.md)
- [Драйверы и ConnectionFactory (PDO vs FFI)](docs/drivers.md)
- [Совместимость с KPHP](docs/kphp-compatibility.md)
- [Build-targets (@lphenom-build)](docs/build-targets.md)

---

## Лицензия

[MIT](LICENSE) © 2026 LPhenom Contributors
