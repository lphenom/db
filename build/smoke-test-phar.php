#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading works.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-db.phar
 *
 * Compatible with PHP 8.1+.
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-db.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, "PHAR not found: {$pharFile}" . PHP_EOL);
    exit(1);
}

require $pharFile;

// Verify Param value objects
$intParam  = \LPhenom\Db\Param\ParamBinder::int(42);
$nullParam = \LPhenom\Db\Param\ParamBinder::null();
$boolParam = \LPhenom\Db\Param\ParamBinder::bool(true);

// KPHP-compat: int/bool stored as string, null indicated by isNull flag
if ($intParam->value !== '42') {
    fwrite(STDERR, 'ParamBinder::int() failed — expected "42"' . PHP_EOL);
    exit(1);
}
if ($boolParam->value !== '1') {
    fwrite(STDERR, 'ParamBinder::bool() failed — expected "1"' . PHP_EOL);
    exit(1);
}
if (!$nullParam->isNull) {
    fwrite(STDERR, 'ParamBinder::null() failed — isNull should be true' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: ParamBinder ok' . PHP_EOL;

// Verify MigrationPlan construction
$plan = new \LPhenom\Db\Migration\MigrationPlan('20260101000000', 'CreateUsersTable');
if ($plan->isApplied() !== false) {
    fwrite(STDERR, 'MigrationPlan::isApplied() failed' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: MigrationPlan ok' . PHP_EOL;

// Verify FfiMySqlConnection class is autoloaded (no actual connection needed)
if (!class_exists(\LPhenom\Db\Driver\FfiMySqlConnection::class)) {
    fwrite(STDERR, 'FfiMySqlConnection class not found in autoloader' . PHP_EOL);
    exit(1);
}
if (!class_exists(\LPhenom\Db\Driver\FfiMySqlResult::class)) {
    fwrite(STDERR, 'FfiMySqlResult class not found in autoloader' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: FfiMySqlConnection autoload ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

