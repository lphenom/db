<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Integration\Driver;

use LPhenom\Db\Driver\FfiMySqlConnection;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\ParamBinder;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for FfiMySqlConnection against a real MySQL server.
 *
 * These tests are skipped automatically when:
 *   - the `ffi` PHP extension is not loaded
 *   - libmysqlclient is not available on the system
 *   - SKIP_FFI_TESTS=1 environment variable is set
 *
 * Environment variables (same as PDO integration tests):
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 *
 * Run via: make test-integration
 *
 * @group integration
 * @group ffi
 * @covers \LPhenom\Db\Driver\FfiMySqlConnection
 * @covers \LPhenom\Db\Driver\FfiMySqlResult
 */
final class FfiMySqlIntegrationTest extends TestCase
{
    private FfiMySqlConnection $conn;

    protected function setUp(): void
    {
        if ((string) getenv('SKIP_FFI_TESTS') === '1') {
            self::markTestSkipped('FFI tests disabled via SKIP_FFI_TESTS=1');
        }

        if (!extension_loaded('ffi')) {
            self::markTestSkipped('ext-ffi is not loaded');
        }

        $host     = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port     = (int)    (getenv('DB_PORT') ?: 3306);
        $dbname   = (string) (getenv('DB_NAME') ?: 'lphenom');
        $user     = (string) (getenv('DB_USER') ?: 'lphenom');
        $password = (string) (getenv('DB_PASSWORD') ?: 'secret');
        $lib      = (string) (getenv('FFI_MYSQL_LIB') ?: 'libmysqlclient.so.21');

        try {
            $this->conn = new FfiMySqlConnection($host, $user, $password, $dbname, $port, $lib);
        } catch (\Throwable $e) {
            self::markTestSkipped('Could not create FfiMySqlConnection: ' . $e->getMessage());
        }

        $this->conn->execute('DROP TABLE IF EXISTS ffi_inttest_users');
        $this->conn->execute('
            CREATE TABLE ffi_inttest_users (
                id       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name     VARCHAR(255) NOT NULL,
                email    VARCHAR(255) NOT NULL,
                score    FLOAT        NOT NULL DEFAULT 0,
                active   TINYINT(1)   NOT NULL DEFAULT 1,
                notes    TEXT         NULL
            )
        ');
    }

    protected function tearDown(): void
    {
        if (isset($this->conn)) {
            $this->conn->execute('DROP TABLE IF EXISTS ffi_inttest_users');
            $this->conn->close();
        }
    }

    public function testInsertAndFetchOne(): void
    {
        $affected = $this->conn->execute(
            'INSERT INTO ffi_inttest_users (name, email, score, active, notes)
             VALUES (:name, :email, :score, :active, :notes)',
            [
                ':name'   => ParamBinder::str('Alice'),
                ':email'  => ParamBinder::str('alice@example.com'),
                ':score'  => ParamBinder::float(9.5),
                ':active' => ParamBinder::bool(true),
                ':notes'  => ParamBinder::null(),
            ],
        );

        self::assertSame(1, $affected);

        $row = $this->conn->query(
            'SELECT * FROM ffi_inttest_users WHERE email = :email',
            [':email' => ParamBinder::str('alice@example.com')],
        )->fetchOne();

        self::assertNotNull($row);
        self::assertSame('Alice', $row['name']);
        self::assertSame('alice@example.com', $row['email']);
        self::assertNull($row['notes']);
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        $this->conn->execute(
            'INSERT INTO ffi_inttest_users (name, email, score, active) VALUES (:n, :e, 1.0, 1)',
            [':n' => ParamBinder::str('Alice'), ':e' => ParamBinder::str('a@ex.com')],
        );
        $this->conn->execute(
            'INSERT INTO ffi_inttest_users (name, email, score, active) VALUES (:n, :e, 2.5, 0)',
            [':n' => ParamBinder::str('Bob'), ':e' => ParamBinder::str('b@ex.com')],
        );

        $rows = $this->conn->query('SELECT * FROM ffi_inttest_users ORDER BY name ASC')->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $this->conn->transaction(function (FfiMySqlConnection $c): void {
            $c->execute(
                'INSERT INTO ffi_inttest_users (name, email, score, active) VALUES (:n, :e, 0, 1)',
                [':n' => ParamBinder::str('Tx'), ':e' => ParamBinder::str('tx@x.com')],
            );
        });

        $row = $this->conn->query(
            'SELECT * FROM ffi_inttest_users WHERE email = :e',
            [':e' => ParamBinder::str('tx@x.com')],
        )->fetchOne();

        self::assertNotNull($row);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->conn->transaction(function (FfiMySqlConnection $c): void {
                $c->execute(
                    'INSERT INTO ffi_inttest_users (name, email, score, active) VALUES (:n, :e, 0, 1)',
                    [':n' => ParamBinder::str('TxFail'), ':e' => ParamBinder::str('txfail@x.com')],
                );

                throw new \RuntimeException('rollback!');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->conn->query(
            'SELECT * FROM ffi_inttest_users WHERE email = :e',
            [':e' => ParamBinder::str('txfail@x.com')],
        )->fetchOne();

        self::assertNull($row);
    }

    public function testSpecialCharsEscaped(): void
    {
        $name = "O'Brien; DROP TABLE ffi_inttest_users; --";
        $this->conn->execute(
            'INSERT INTO ffi_inttest_users (name, email, score, active) VALUES (:name, :email, 0, 1)',
            [':name' => ParamBinder::str($name), ':email' => ParamBinder::str('safe@x.com')],
        );

        $row = $this->conn->query(
            'SELECT name FROM ffi_inttest_users WHERE email = :e',
            [':e' => ParamBinder::str('safe@x.com')],
        )->fetchOne();

        self::assertNotNull($row);
        self::assertSame($name, $row['name']);
    }

    public function testInvalidQueryThrowsQueryException(): void
    {
        $this->expectException(QueryException::class);
        $this->conn->query('THIS IS NOT SQL');
    }
}
