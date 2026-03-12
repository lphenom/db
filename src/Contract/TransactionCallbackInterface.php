<?php

declare(strict_types=1);

namespace LPhenom\Db\Contract;

/**
 * @lphenom-build shared,kphp
 *
 * Callback interface for database transactions.
 *
 * Used instead of `callable` for KPHP compatibility.
 * KPHP does not support storing or passing `callable` in typed contexts.
 *
 * Usage:
 * <code>
 * $conn->transaction(new class($data) implements TransactionCallbackInterface {
 *     public function __construct(private array $data) {}
 *     public function execute(ConnectionInterface $conn): mixed
 *     {
 *         return $conn->execute('INSERT INTO ...', [...]);
 *     }
 * });
 * </code>
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
interface TransactionCallbackInterface
{
    /**
     * Executed inside a transaction.
     * Return value is passed through from ConnectionInterface::transaction().
     *
     * KPHP note: complex union int|string|bool|float|null is not supported — mixed is used.
     *
     * @return mixed
     */
    public function execute(ConnectionInterface $conn): mixed;
}
