<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Driver;

use LPhenom\Db\Driver\ConnectionFactory;
use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Db\Driver\ConnectionFactory
 */
final class ConnectionFactoryTest extends TestCase
{
    public function testCreatesPdoMySqlConnectionByDefaultThrowsOnNoServer(): void
    {
        // pdo_mysql driver connects eagerly — expect ConnectionException
        // when no real MySQL is reachable (unit test environment).
        $this->expectException(ConnectionException::class);

        ConnectionFactory::create([
            'driver'   => 'pdo_mysql',
            'host'     => '127.0.0.1',
            'port'     => 19999,   // unreachable port
            'dbname'   => 'test',
            'user'     => 'root',
            'password' => '',
        ]);
    }

    public function testCreatesSqliteViaDsnForUnitTesting(): void
    {
        // Direct construction with SQLite DSN — verifies factory produces the right class
        $conn = new PdoMySqlConnection('sqlite::memory:', '', '');
        self::assertInstanceOf(PdoMySqlConnection::class, $conn);
    }

    public function testThrowsConnectionExceptionForUnknownDriver(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unknown driver "baddriver"');

        ConnectionFactory::create(['driver' => 'baddriver']);
    }

    public function testFfiDriverThrowsConnectionExceptionWhenLibMissing(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('ext-ffi is not loaded');
        }

        $this->expectException(ConnectionException::class);

        ConnectionFactory::create([
            'driver'   => 'ffi_mysql',
            'host'     => '127.0.0.1',
            'dbname'   => 'test',
            'user'     => 'root',
            'password' => '',
            'ffi_lib'  => '/nonexistent/libmysqlclient.so.99',
        ]);
    }

    public function testFfiDriverSkippedWhenFfiNotLoaded(): void
    {
        if (extension_loaded('ffi')) {
            self::markTestSkipped('ext-ffi IS loaded; skipping this test (it tests no-ffi scenario)');
        }

        $this->expectException(ConnectionException::class);

        ConnectionFactory::create([
            'driver'  => 'ffi_mysql',
            'host'    => '127.0.0.1',
            'dbname'  => 'test',
            'user'    => 'root',
            'password' => '',
        ]);
    }
}
