<?php

/**
 * KPHP entrypoint for lphenom/db.
 *
 * KPHP does not support Composer PSR-4 autoloading.
 * All files must be included explicitly via require_once in dependency order:
 *   Interfaces → Exceptions → Value objects → Concrete classes
 *
 * FFI NOTE:
 *   FfiMySqlConnection and FfiMySqlResult use FFI::scope('mysql').
 *   KPHP traces the scope name through lphenom_db_mysql_get_ffi() to all
 *   mysql_*() call sites and resolves method types from src/Driver/mysql.h
 *   (annotated with // @kphp-ffi-scope mysql). No "Method not found" errors.
 *
 * This entrypoint verifies:
 *   - Contract interfaces (KPHP-compatible)
 *   - Exceptions (KPHP-compatible)
 *   - Param / ParamBinder: string $value + bool $isNull (KPHP union-type fix)
 *   - Migration: MigrationInterface, MigrationPlan, SchemaMigrations
 *   - Repository: AbstractRepository
 *   - FFI MySQL driver: FfiMySqlConnection, FfiMySqlResult (real implementation)
 *
 * Compatible with PHP 8.1+ and KPHP.
 */

declare(strict_types=1);

// ── Contracts (interfaces — no dependencies) ──────────────────────────────
require_once __DIR__ . '/../src/Contract/ResultInterface.php';
require_once __DIR__ . '/../src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../src/Contract/TransactionCallbackInterface.php';

// ── Exceptions (no dependencies on src/) ─────────────────────────────────
require_once __DIR__ . '/../src/Exception/ConnectionException.php';
require_once __DIR__ . '/../src/Exception/NotImplementedException.php';
require_once __DIR__ . '/../src/Exception/QueryException.php';

// ── Param (no dependencies on drivers) ───────────────────────────────────
require_once __DIR__ . '/../src/Param/Param.php';
require_once __DIR__ . '/../src/Param/ParamBinder.php';

// ── Migration (depends on Contract, Param) ────────────────────────────────
require_once __DIR__ . '/../src/Migration/MigrationInterface.php';
require_once __DIR__ . '/../src/Migration/MigrationPlan.php';
require_once __DIR__ . '/../src/Migration/SchemaMigrations.php';

// ── Repository (depends on Contract, Param) ───────────────────────────────
require_once __DIR__ . '/../src/Repository/AbstractRepository.php';

// ── FFI MySQL driver — real implementation (no stubs) ─────────────────────
// FfiMySqlHeader.php defines the global lphenom_db_mysql_get_ffi() using
// FFI::scope('mysql'). KPHP resolves all mysql_*() types at compile time
// from src/Driver/mysql.h (annotated with // @kphp-ffi-scope mysql).
require_once __DIR__ . '/../src/Driver/FfiMySqlHeader.php';
require_once __DIR__ . '/../src/Driver/FfiMySqlResult.php';
require_once __DIR__ . '/../src/Driver/FfiMySqlConnection.php';

// ── Smoke test: verify type resolution ────────────────────────────────────

// Verify Param value object construction (string $value + bool $isNull)
$intParam   = \LPhenom\Db\Param\ParamBinder::int(42);
$strParam   = \LPhenom\Db\Param\ParamBinder::str('hello');
$nullParam  = \LPhenom\Db\Param\ParamBinder::null();
$boolParam  = \LPhenom\Db\Param\ParamBinder::bool(true);
$floatParam = \LPhenom\Db\Param\ParamBinder::float(3.14);

// Verify MigrationPlan construction
$plan    = new \LPhenom\Db\Migration\MigrationPlan('20260101000000', 'CreateUsersTable');
$applied = $plan->isApplied();

// FfiMySqlConnection is compiled with FFI types resolved from mysql.h.
// We do NOT instantiate it here — that requires a live MySQL server.
// Compilation success proves FFI::scope('mysql') resolution works correctly.

echo 'lphenom/db kphp-entrypoint: OK' . PHP_EOL;

