<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Exception\ConnectionException;

/**
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
 * Config array shape:
 * <code>
 * [
 *     'driver'   => 'pdo_mysql',  // or 'ffi_mysql'
 *     'host'     => '127.0.0.1',
 *     'port'     => 3306,
 *     'dbname'   => 'myapp',
 *     'user'     => 'root',
 *     'password' => 'secret',
 *     // For ffi_mysql only:
 *     'ffi_lib'  => 'libmysqlclient.so.21',  // optional, default libmysqlclient.so.21
 * ]
 * </code>
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
                $driver,
            ),
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
                'ext-ffi is not loaded. FfiMySqlConnection requires the FFI PHP extension.',
            );
        }

        $host     = isset($config['host']) ? (string) $config['host'] : '127.0.0.1';
        $port     = isset($config['port']) ? (int)    $config['port'] : 3306;
        $dbname   = isset($config['dbname']) ? (string) $config['dbname'] : '';
        $user     = isset($config['user']) ? (string) $config['user'] : '';
        $password = isset($config['password']) ? (string) $config['password'] : '';
        $lib      = isset($config['ffi_lib']) ? (string) $config['ffi_lib'] : 'libmysqlclient.so.21';

        return new FfiMySqlConnection($host, $user, $password, $dbname, $port, $lib);
    }
}
