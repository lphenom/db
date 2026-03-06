<?php

declare(strict_types=1);

namespace LPhenom\Db\Migration;

use LPhenom\Db\Contract\ConnectionInterface;

/**
 * Database migration contract.
 *
 * Each migration must implement up() and down() methods.
 * Compatible with PHP 8.1+ and KPHP.
 */
interface MigrationInterface
{
    /**
     * Apply the migration (create tables, add columns, etc.).
     */
    public function up(ConnectionInterface $conn): void;

    /**
     * Revert the migration (drop tables, remove columns, etc.).
     */
    public function down(ConnectionInterface $conn): void;

    /**
     * Return the migration version identifier (e.g. "20260101000000").
     */
    public function getVersion(): string;
}
