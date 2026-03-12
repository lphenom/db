#!/usr/bin/env php
<?php

/**
 * FFI preload script for lphenom/db MySQL driver.
 *
 * Registers the 'mysql' FFI scope from src/Driver/mysql.h so that
 * FFI::scope('mysql') works in regular PHP without needing FFI::cdef().
 *
 * Usage (CLI with opcache):
 *   php -d opcache.enable_cli=1 -d opcache.preload=/app/build/ffi-preload.php script.php
 *
 * Usage (php.ini / opcache.preload):
 *   opcache.preload = /path/to/build/ffi-preload.php
 *   opcache.preload_user = www-data
 *   ffi.enable = preload
 *
 * Note: lphenom_db_mysql_get_ffi() automatically falls back to FFI::cdef()
 * when the scope is not preloaded, so this script is OPTIONAL for PHP usage.
 * It is NOT used by KPHP (KPHP resolves FFI types at compile time).
 *
 * Compatible with PHP 8.1+.
 */

declare(strict_types=1);

$headerFile = dirname(__DIR__) . '/src/Driver/mysql.h';

if (!file_exists($headerFile)) {
    fwrite(STDERR, "ffi-preload: mysql.h not found at {$headerFile}" . PHP_EOL);
    exit(1);
}

FFI::load($headerFile);

echo 'ffi-preload: mysql scope loaded OK' . PHP_EOL;

