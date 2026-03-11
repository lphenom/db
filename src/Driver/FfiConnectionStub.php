<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;
use LPhenom\Db\Exception\NotImplementedException;

/**
 * @lphenom-build shared,kphp
 *
 * Placeholder connection for environments where the real FFI driver is unavailable.
 *
 * This stub satisfies ConnectionInterface for KPHP compilation compatibility.
 * All methods throw NotImplementedException at runtime.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiConnectionStub implements ConnectionInterface
{
    /**
     * @param array<string, \LPhenom\Db\Param\Param> $params
     * @throws NotImplementedException
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        throw new NotImplementedException('FFI driver is not implemented. Use PdoMySqlConnection instead.');
    }

    /**
     * @param array<string, \LPhenom\Db\Param\Param> $params
     * @throws NotImplementedException
     */
    public function execute(string $sql, array $params = []): int
    {
        throw new NotImplementedException('FFI driver is not implemented. Use PdoMySqlConnection instead.');
    }

    /**
     * @throws NotImplementedException
     */
    public function transaction(TransactionCallbackInterface $callback): int|string|bool|float|null
    {
        throw new NotImplementedException('FFI driver is not implemented. Use PdoMySqlConnection instead.');
    }
}
