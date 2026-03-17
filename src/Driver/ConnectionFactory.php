<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Exception\ConnectionException;

/**
 * @lphenom-build shared
 *
 * Factory that creates a ConnectionInterface instance based on a driver config.
 *
 * This is the single place where driver selection happens.
 * Repository code never needs to know which driver is active —
 * it always depends only on ConnectionInterface.
 *
 * Supported drivers:
 *   "pdo_mysql"  — PdoMySqlConnection (standard PHP / shared hosting)
 *   "ffi_mysql"  — FfiMySqlConnection  (KPHP compiled mode / FFI-capable PHP)
 *
 * Note: In KPHP binary mode ConnectionFactory is NOT included — the connection
 * is created directly (new FfiMySqlConnection(...)) in the application bootstrap.
 * Driver selection at runtime is not needed in compiled binaries.
 *
 * Config array shape:
 * <code>
 * [
 *     'driver'   => 'pdo_mysql',  // or 'ffi_mysql'
 *     'host'     => '127.0.0.1',
 *     'port'     => 3306,
 *     'dbname'   => 'myapp',
 *     'user'     => 'root',
 *     'password' => 'secret',
 * ]
 * </code>
 *
 * For ffi_mysql library path: set FFI_MYSQL_LIB env variable (e.g. "libmariadb.so.3" on Alpine)
 * or configure PHP FFI preloading via build/ffi-preload.php.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
final class ConnectionFactory
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $config
     * @throws ConnectionException
     */
    public static function create(array $config): ConnectionInterface
    {
        $driver = isset($config['driver']) ? (string) $config['driver'] : 'pdo_mysql';

        if ($driver === 'pdo_mysql') {
            return self::createPdo($config);
        }

        if ($driver === 'ffi_mysql') {
            return self::createFfi($config);
        }

        throw new ConnectionException(
            sprintf(
                'Unknown driver "%s". Supported drivers: pdo_mysql, ffi_mysql.',
                $driver
            )
        );
    }

    /**
     * @param array<string, mixed> $config
     * @throws ConnectionException
     */
    private static function createPdo(array $config): PdoMySqlConnection
    {
        $host     = isset($config['host']) ? (string) $config['host'] : '127.0.0.1';
        $port     = isset($config['port']) ? (int)    $config['port'] : 3306;
        $dbname   = isset($config['dbname']) ? (string) $config['dbname'] : '';
        $user     = isset($config['user']) ? (string) $config['user'] : '';
        $password = isset($config['password']) ? (string) $config['password'] : '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);

        return new PdoMySqlConnection($dsn, $user, $password);
    }

    /**
     * @param array<string, mixed> $config
     * @throws ConnectionException
     */
    private static function createFfi(array $config): FfiMySqlConnection
    {
        if (!extension_loaded('ffi')) {
            throw new ConnectionException(
                'ext-ffi is not loaded. FfiMySqlConnection requires the FFI PHP extension.'
            );
        }

        // If FFI_MYSQL_LIB env var is set, validate the path exists before attempting FFI load.
        // This allows tests and CI to force a "library missing" scenario deterministically.
        $libOverride = getenv('FFI_MYSQL_LIB');
        if ($libOverride !== false && $libOverride !== '') {
            if (!file_exists($libOverride)) {
                throw new ConnectionException(
                    sprintf('MySQL FFI library not found: %s', $libOverride)
                );
            }
        }

        $host     = isset($config['host']) ? (string) $config['host'] : '127.0.0.1';
        $port     = isset($config['port']) ? (int)    $config['port'] : 3306;
        $dbname   = isset($config['dbname']) ? (string) $config['dbname'] : '';
        $user     = isset($config['user']) ? (string) $config['user'] : '';
        $password = isset($config['password']) ? (string) $config['password'] : '';

        try {
            return new FfiMySqlConnection($host, $user, $password, $dbname, $port);
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException(
                'FfiMySqlConnection failed: ' . $e->getMessage()
            );
        }
    }
}
