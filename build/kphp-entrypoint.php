<?php

/**
 * KPHP entrypoint for lphenom/db.
 *
 * KPHP does not support Composer PSR-4 autoloading.
 * All files must be included explicitly via require_once in dependency order:
 *   Interfaces → Exceptions → Value objects → Concrete classes
 *
 * This file is used only for KPHP compilation verification.
 * In PHP runtime mode, use Composer autoloader instead.
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

// ── Drivers (FfiMySqlConnection — depends on Contract, Param, Exception) ──
// Note: PdoMySqlConnection and PdoResult are NOT included because PDO
// is not available in KPHP runtime. FfiMySqlConnection is the KPHP driver.
require_once __DIR__ . '/../src/Driver/FfiMySqlResult.php';
require_once __DIR__ . '/../src/Driver/FfiMySqlConnection.php';
require_once __DIR__ . '/../src/Driver/FfiConnectionStub.php';
require_once __DIR__ . '/../src/Driver/ConnectionFactory.php';

// ── Smoke test: verify type resolution ────────────────────────────────────

// Verify Param value object construction
$intParam  = \LPhenom\Db\Param\ParamBinder::int(42);
$strParam  = \LPhenom\Db\Param\ParamBinder::str('hello');
$nullParam = \LPhenom\Db\Param\ParamBinder::null();
$boolParam = \LPhenom\Db\Param\ParamBinder::bool(true);
$floatParam = \LPhenom\Db\Param\ParamBinder::float(3.14);

// Verify MigrationPlan construction
$plan = new \LPhenom\Db\Migration\MigrationPlan('20260101000000', 'CreateUsersTable');
$applied = $plan->isApplied();

// Verify FfiConnectionStub construction (no FFI needed)
$stub = new \LPhenom\Db\Driver\FfiConnectionStub();

echo 'lphenom/db kphp-entrypoint: OK' . PHP_EOL;

