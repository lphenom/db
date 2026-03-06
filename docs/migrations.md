# Migration Contracts

## Overview

`lphenom/db` provides **contracts only** — the migration runner (CLI tool) is a separate package.
This package defines the interfaces and the `schema_migrations` tracking table helper.

---

## MigrationInterface

Every migration must implement:

```php
interface MigrationInterface
{
    public function up(ConnectionInterface $conn): void;
    public function down(ConnectionInterface $conn): void;
    public function getVersion(): string;
}
```

- `up()` — apply the migration (CREATE TABLE, ALTER TABLE, INSERT seed data, etc.)
- `down()` — revert it (DROP TABLE, DROP COLUMN, etc.)
- `getVersion()` — returns a string identifier, conventionally a timestamp: `"20260101120000"`

---

## Example Migration

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

## MigrationPlan DTO

`MigrationPlan` is an immutable DTO used by migration tools to track state:

```php
$plan = new MigrationPlan(
    version:   '20260101120000',
    name:      'CreateUsersTable',
    appliedAt: null,                // null = not yet applied
);

$applied = $plan->withAppliedAt(new \DateTimeImmutable());
$applied->isApplied(); // true
```

---

## SchemaMigrations Helper

`SchemaMigrations` manages the `schema_migrations` tracking table:

```php
$schema = new SchemaMigrations($conn);

// Ensure the tracking table exists (idempotent)
$schema->ensureTable();

// Mark a migration as applied
$schema->markApplied('20260101120000', 'CreateUsersTable');

// Get all applied versions in ascending order
$versions = $schema->getApplied(); // ['20260101120000', ...]

// Revert: remove a version record
$schema->markReverted('20260101120000');
```

### schema_migrations table DDL

```sql
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    VARCHAR(255) NOT NULL PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    applied_at DATETIME     NOT NULL
);
```

---

## How the Migration Tool Will Work

> The CLI runner is implemented in a separate `lphenom/migrate` package.

Expected flow:

1. **Discover** migration classes (registered explicitly — no filesystem scanning).
2. **Compare** discovered versions against `schema_migrations.getApplied()`.
3. **Plan** which migrations to run (`up` for pending, `down` for rollback).
4. **Execute** each migration inside a transaction where possible.
5. **Record** applied/reverted versions via `SchemaMigrations`.

---

## Development Commands

```bash
# Start environment
make up

# Run tests (includes SchemaMigrations tests using SQLite in-memory)
make test

# Check code style
make lint
```

---

## KPHP Compatibility

- Migration classes must be registered **explicitly** — no directory scanning or reflection.
- All DDL SQL is plain strings — no query builder.
- `SchemaMigrations` uses `ParamBinder` for all bound values.

