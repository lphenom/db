# lphenom/db

[![CI](https://github.com/lphenom/db/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/db/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**LPhenom Database Layer** — raw SQL, repository pattern, PDO driver, migration contracts.

Part of the [LPhenom](https://github.com/lphenom) PHP framework ecosystem — compatible with PHP 8.1+ and KPHP compilation.

---

## Features

- 🔌 `ConnectionInterface` / `ResultInterface` — clean contracts
- 🛡 Safe parameter binding (`int`, `string`, `bool`, `null`, `float`)
- 🗄 `PdoMySqlConnection` — PDO MySQL driver for shared hosting
- ⚡ `FfiMySqlConnection` — KPHP FFI MySQL driver (libmysqlclient, compiled mode)
- 🔀 `ConnectionFactory` — single config-driven driver selection (`pdo_mysql` | `ffi_mysql`)
- 📁 `AbstractRepository` + DTO pattern (no ORM magic)
- 🔄 `MigrationInterface` + `SchemaMigrations` helper
- ✅ Unit tests (SQLite in-memory) + Integration tests (real MySQL)
- 🐳 Docker dev environment (PHP 8.1-alpine, MySQL 8.0.36)

---

## Requirements

- PHP >= 8.1
- `ext-pdo` + `ext-pdo_mysql` — for `PdoMySqlConnection` (shared hosting / standard PHP)
- `ext-ffi` + `libmysqlclient` — for `FfiMySqlConnection` (KPHP compiled mode)

---

## Installation

```bash
composer require lphenom/db
```

---

## Quick Start

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

## Development

```bash
git clone git@github.com:lphenom/db.git
cd db
make up                  # start Docker (PHP 8.1-alpine + MySQL 8.0.36)
make test-unit           # run unit tests (no real DB needed)
make test-integration    # run integration tests against MySQL
make lint                # run php-cs-fixer check
make phpstan             # run PHPStan
make down                # stop Docker
```

---

## Documentation

- [Repository Pattern Guidelines](docs/repositories.md)
- [Migration Contracts](docs/migrations.md)
- [Drivers & ConnectionFactory (PDO vs FFI)](docs/drivers.md)

---

## License

[MIT](LICENSE) © 2026 LPhenom Contributors
