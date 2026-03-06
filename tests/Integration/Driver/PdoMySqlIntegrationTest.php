<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Integration\Driver;

use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\ParamBinder;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PdoMySqlConnection against a real MySQL server.
 *
 * These tests require environment variables:
 *   DB_HOST     (default: 127.0.0.1)
 *   DB_PORT     (default: 3306)
 *   DB_NAME     (default: lphenom)
 *   DB_USER     (default: lphenom)
 *   DB_PASSWORD (default: secret)
 *
 * Run via: make test-integration
 * or:      vendor/bin/phpunit --testsuite integration
 *
 * @group integration
 * @covers \LPhenom\Db\Driver\PdoMySqlConnection
 * @covers \LPhenom\Db\Driver\PdoResult
 */
final class PdoMySqlIntegrationTest extends TestCase
{
    private PdoMySqlConnection $conn;

    protected function setUp(): void
    {
        $host     = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port     = (int)    (getenv('DB_PORT') ?: 3306);
        $dbname   = (string) (getenv('DB_NAME') ?: 'lphenom');
        $user     = (string) (getenv('DB_USER') ?: 'lphenom');
        $password = (string) (getenv('DB_PASSWORD') ?: 'secret');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);

        $this->conn = new PdoMySqlConnection($dsn, $user, $password);

        $this->conn->execute('DROP TABLE IF EXISTS inttest_users');
        $this->conn->execute('
            CREATE TABLE inttest_users (
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
        $this->conn->execute('DROP TABLE IF EXISTS inttest_users');
    }

    public function testInsertAndFetchOne(): void
    {
        $affected = $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active, notes)
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
            'SELECT * FROM inttest_users WHERE email = :email',
            [':email' => ParamBinder::str('alice@example.com')],
        )->fetchOne();

        self::assertNotNull($row);
        self::assertSame('Alice', $row['name']);
        self::assertSame('alice@example.com', $row['email']);
        self::assertNull($row['notes']);
        self::assertSame('1', (string) $row['active']);
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, :score, :active)',
            [':name' => ParamBinder::str('Alice'), ':email' => ParamBinder::str('a@ex.com'), ':score' => ParamBinder::float(1.0), ':active' => ParamBinder::bool(true)],
        );
        $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, :score, :active)',
            [':name' => ParamBinder::str('Bob'), ':email' => ParamBinder::str('b@ex.com'), ':score' => ParamBinder::float(2.5), ':active' => ParamBinder::bool(false)],
        );

        $rows = $this->conn->query('SELECT * FROM inttest_users ORDER BY name ASC')->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
        self::assertSame('0', (string) $rows[1]['active']);
    }

    public function testUpdateAffectedRows(): void
    {
        $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active) VALUES (:n1, :e1, 0, 1), (:n2, :e2, 0, 1)',
            [':n1' => ParamBinder::str('A'), ':e1' => ParamBinder::str('a@x.com'), ':n2' => ParamBinder::str('B'), ':e2' => ParamBinder::str('b@x.com')],
        );

        $affected = $this->conn->execute(
            'UPDATE inttest_users SET active = :active',
            [':active' => ParamBinder::bool(false)],
        );

        self::assertSame(2, $affected);
    }

    public function testDeleteAffectedRows(): void
    {
        $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, 0, 1)',
            [':name' => ParamBinder::str('ToDelete'), ':email' => ParamBinder::str('del@x.com')],
        );

        $affected = $this->conn->execute(
            'DELETE FROM inttest_users WHERE email = :email',
            [':email' => ParamBinder::str('del@x.com')],
        );

        self::assertSame(1, $affected);

        $row = $this->conn->query('SELECT * FROM inttest_users WHERE email = :email', [':email' => ParamBinder::str('del@x.com')])->fetchOne();
        self::assertNull($row);
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $this->conn->transaction(function (PdoMySqlConnection $c): void {
            $c->execute(
                'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, 0, 1)',
                [':name' => ParamBinder::str('Tx'), ':email' => ParamBinder::str('tx@x.com')],
            );
        });

        $row = $this->conn->query('SELECT * FROM inttest_users WHERE email = :email', [':email' => ParamBinder::str('tx@x.com')])->fetchOne();
        self::assertNotNull($row);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->conn->transaction(function (PdoMySqlConnection $c): void {
                $c->execute(
                    'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, 0, 1)',
                    [':name' => ParamBinder::str('TxFail'), ':email' => ParamBinder::str('txfail@x.com')],
                );

                throw new \RuntimeException('rollback!');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->conn->query('SELECT * FROM inttest_users WHERE email = :email', [':email' => ParamBinder::str('txfail@x.com')])->fetchOne();
        self::assertNull($row);
    }

    public function testSpecialCharsInStringParam(): void
    {
        $name = "O'Brien; DROP TABLE inttest_users; --";
        $this->conn->execute(
            'INSERT INTO inttest_users (name, email, score, active) VALUES (:name, :email, 0, 1)',
            [':name' => ParamBinder::str($name), ':email' => ParamBinder::str('safe@x.com')],
        );

        $row = $this->conn->query('SELECT name FROM inttest_users WHERE email = :email', [':email' => ParamBinder::str('safe@x.com')])->fetchOne();
        self::assertNotNull($row);
        self::assertSame($name, $row['name']);
    }

    public function testInvalidQueryThrowsQueryException(): void
    {
        $this->expectException(QueryException::class);
        $this->conn->query('THIS IS NOT SQL');
    }
}
