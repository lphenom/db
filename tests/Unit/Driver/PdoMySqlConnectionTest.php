<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Driver;

use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Exception\QueryException;
use LPhenom\Db\Param\ParamBinder;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style unit tests using SQLite in-memory database.
 *
 * @covers \LPhenom\Db\Driver\PdoMySqlConnection
 * @covers \LPhenom\Db\Driver\PdoResult
 */
final class PdoMySqlConnectionTest extends TestCase
{
    private PdoMySqlConnection $conn;

    protected function setUp(): void
    {
        // Use SQLite in-memory for fast unit tests without Docker
        $this->conn = new PdoMySqlConnection('sqlite::memory:', '', '');
        $this->conn->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1)');
    }

    public function testExecuteInsertReturnsAffectedRows(): void
    {
        $affected = $this->conn->execute(
            'INSERT INTO users (id, name, active) VALUES (:id, :name, :active)',
            [
                ':id'     => ParamBinder::int(1),
                ':name'   => ParamBinder::str('Alice'),
                ':active' => ParamBinder::bool(true),
            ],
        );

        self::assertSame(1, $affected);
    }

    public function testQueryFetchOneReturnsRow(): void
    {
        $this->conn->execute(
            'INSERT INTO users (id, name, active) VALUES (:id, :name, :active)',
            [
                ':id'     => ParamBinder::int(1),
                ':name'   => ParamBinder::str('Alice'),
                ':active' => ParamBinder::int(1),
            ],
        );

        $row = $this->conn->query(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int(1)],
        )->fetchOne();

        self::assertNotNull($row);
        self::assertSame('Alice', $row['name']);
    }

    public function testQueryFetchOneReturnsNullWhenNoRows(): void
    {
        $row = $this->conn->query(
            'SELECT * FROM users WHERE id = :id',
            [':id' => ParamBinder::int(999)],
        )->fetchOne();

        self::assertNull($row);
    }

    public function testQueryFetchAllReturnsAllRows(): void
    {
        $this->conn->execute('INSERT INTO users (id, name, active) VALUES (1, \'Alice\', 1)');
        $this->conn->execute('INSERT INTO users (id, name, active) VALUES (2, \'Bob\', 1)');

        $rows = $this->conn->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testExecuteUpdateReturnsAffectedRows(): void
    {
        $this->conn->execute('INSERT INTO users (id, name, active) VALUES (1, \'Alice\', 1)');
        $this->conn->execute('INSERT INTO users (id, name, active) VALUES (2, \'Bob\', 1)');

        $affected = $this->conn->execute(
            'UPDATE users SET active = :active',
            [':active' => ParamBinder::int(0)],
        );

        self::assertSame(2, $affected);
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $this->conn->transaction(function (PdoMySqlConnection $conn): void {
            $conn->execute('INSERT INTO users (id, name, active) VALUES (1, \'Alice\', 1)');
        });

        $row = $this->conn->query('SELECT * FROM users WHERE id = 1')->fetchOne();
        self::assertNotNull($row);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->conn->transaction(function (PdoMySqlConnection $conn): void {
                $conn->execute('INSERT INTO users (id, name, active) VALUES (1, \'Alice\', 1)');

                throw new \RuntimeException('Something went wrong');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->conn->query('SELECT * FROM users WHERE id = 1')->fetchOne();
        self::assertNull($row);
    }

    public function testQueryThrowsQueryExceptionOnInvalidSql(): void
    {
        $this->expectException(QueryException::class);

        $this->conn->query('INVALID SQL STATEMENT');
    }
}

