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
$intParam = \LPhenom\Db\Param\ParamBinder::int(42);
if ($intParam->value !== 42) {
    fwrite(STDERR, 'ParamBinder::int() failed' . PHP_EOL);
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

// Verify FfiConnectionStub construction
$stub = new \LPhenom\Db\Driver\FfiConnectionStub();
try {
    $stub->query('SELECT 1');
    fwrite(STDERR, 'FfiConnectionStub should throw NotImplementedException' . PHP_EOL);
    exit(1);
} catch (\LPhenom\Db\Exception\NotImplementedException $e) {
    echo 'smoke-test: FfiConnectionStub ok' . PHP_EOL;
}

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

