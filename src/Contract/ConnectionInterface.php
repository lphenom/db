<?php

declare(strict_types=1);

namespace LPhenom\Db\Contract;

/**
 * @lphenom-build shared,kphp
 *
 * Database connection contract.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 *
 * KPHP note: `callable` is not supported as a parameter type in KPHP.
 * Use TransactionCallbackInterface instead of closures for transactions.
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
     * Run a TransactionCallbackInterface inside a database transaction.
     * Commits on success, rolls back on exception.
     *
     * Returns whatever the callback returns (null if void).
     *
     * KPHP note: `callable` is forbidden — use TransactionCallbackInterface.
     * KPHP note: complex union int|string|bool|float|null is not supported — mixed is used.
     *
     * @return mixed
     */
    public function transaction(TransactionCallbackInterface $callback): mixed;
}
