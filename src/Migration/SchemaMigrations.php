<?php

declare(strict_types=1);

namespace LPhenom\Db\Migration;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Param\ParamBinder;

/**
 * DDL helper for schema_migrations tracking table.
 *
 * Manages the `schema_migrations` table used to track applied migrations.
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
final class SchemaMigrations
{
    private const TABLE = 'schema_migrations';

    public function __construct(
        private readonly ConnectionInterface $conn,
    ) {
    }

    /**
     * Returns the DDL SQL to create the schema_migrations table.
     */
    public static function createTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            version    VARCHAR(255) NOT NULL PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            applied_at DATETIME     NOT NULL
        )';
    }

    /**
     * Ensure the schema_migrations table exists.
     */
    public function ensureTable(): void
    {
        $this->conn->execute(self::createTableSql());
    }

    /**
     * Mark a migration version as applied.
     */
    public function markApplied(string $version, string $name): void
    {
        $this->conn->execute(
            'INSERT INTO ' . self::TABLE . ' (version, name, applied_at) VALUES (:version, :name, :applied_at)',
            [
                ':version'    => ParamBinder::str($version),
                ':name'       => ParamBinder::str($name),
                ':applied_at' => ParamBinder::str((new \DateTimeImmutable())->format('Y-m-d H:i:s')),
            ],
        );
    }

    /**
     * Remove a migration version record (mark as reverted).
     */
    public function markReverted(string $version): void
    {
        $this->conn->execute(
            'DELETE FROM ' . self::TABLE . ' WHERE version = :version',
            [':version' => ParamBinder::str($version)],
        );
    }

    /**
     * Return all applied migration versions in ascending order.
     *
     * @return array<int, string>
     */
    public function getApplied(): array
    {
        $rows = $this->conn->query(
            'SELECT version FROM ' . self::TABLE . ' ORDER BY version ASC',
        )->fetchAll();

        $versions = [];
        foreach ($rows as $row) {
            $versions[] = (string) $row['version'];
        }

        return $versions;
    }
}
