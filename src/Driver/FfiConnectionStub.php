<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Exception\NotImplementedException;

/**
 * Placeholder connection for KPHP FFI driver (not yet implemented).
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
    public function transaction(callable $callback): mixed
    {
        throw new NotImplementedException('FFI driver is not implemented. Use PdoMySqlConnection instead.');
    }
}

