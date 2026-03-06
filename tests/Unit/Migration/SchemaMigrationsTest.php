<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Migration;

use LPhenom\Db\Driver\PdoMySqlConnection;
use LPhenom\Db\Migration\SchemaMigrations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Db\Migration\SchemaMigrations
 */
final class SchemaMigrationsTest extends TestCase
{
    private PdoMySqlConnection $conn;
    private SchemaMigrations $migrations;

    protected function setUp(): void
    {
        $this->conn = new PdoMySqlConnection('sqlite::memory:', '', '');
        $this->migrations = new SchemaMigrations($this->conn);
        $this->migrations->ensureTable();
    }

    public function testEnsureTableCreatesTableSuccessfully(): void
    {
        // If ensureTable() doesn't throw, the table was created
        // Try to run it twice — should be idempotent (IF NOT EXISTS)
        $this->migrations->ensureTable();

        $rows = $this->conn->query('SELECT * FROM schema_migrations')->fetchAll();
        self::assertSame([], $rows);
    }

    public function testMarkAppliedRecordsVersion(): void
    {
        $this->migrations->markApplied('20260101000000', 'CreateUsersTable');

        $applied = $this->migrations->getApplied();

        self::assertCount(1, $applied);
        self::assertSame('20260101000000', $applied[0]);
    }

    public function testGetAppliedReturnsVersionsInAscendingOrder(): void
    {
        $this->migrations->markApplied('20260103000000', 'ThirdMigration');
        $this->migrations->markApplied('20260101000000', 'FirstMigration');
        $this->migrations->markApplied('20260102000000', 'SecondMigration');

        $applied = $this->migrations->getApplied();

        self::assertSame([
            '20260101000000',
            '20260102000000',
            '20260103000000',
        ], $applied);
    }

    public function testMarkRevertedRemovesVersion(): void
    {
        $this->migrations->markApplied('20260101000000', 'CreateUsersTable');
        $this->migrations->markApplied('20260102000000', 'AddEmailToUsers');

        $this->migrations->markReverted('20260101000000');

        $applied = $this->migrations->getApplied();

        self::assertCount(1, $applied);
        self::assertSame('20260102000000', $applied[0]);
    }

    public function testGetAppliedReturnsEmptyArrayWhenNoMigrations(): void
    {
        $applied = $this->migrations->getApplied();

        self::assertSame([], $applied);
    }

    public function testCreateTableSqlContainsRequiredColumns(): void
    {
        $sql = SchemaMigrations::createTableSql();

        self::assertStringContainsString('version', $sql);
        self::assertStringContainsString('name', $sql);
        self::assertStringContainsString('applied_at', $sql);
        self::assertStringContainsString('schema_migrations', $sql);
    }
}
