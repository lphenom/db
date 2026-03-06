<?php

declare(strict_types=1);

namespace LPhenom\Db\Contract;

/**
 * Database connection contract.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
interface ConnectionInterface
{
    /**
     * Execute a SELECT query and return a result set.
     *
     * @param array<string, \LPhenom\Db\Param\Param> $params
     */
    public function query(string $sql, array $params = []): ResultInterface;

    /**
     * Execute an INSERT/UPDATE/DELETE query and return affected rows count.
     *
     * @param array<string, \LPhenom\Db\Param\Param> $params
     */
    public function execute(string $sql, array $params = []): int;

    /**
     * Run a callable inside a database transaction.
     * Commits on success, rolls back on exception.
     *
     * Returns whatever the callable returns (null if void).
     * KPHP note: mixed return is avoided — use @return annotation for generics.
     *
     * @return int|string|bool|float|null
     */
    public function transaction(callable $callback): int|string|bool|float|null;
}
