<?php

declare(strict_types=1);

namespace LPhenom\Db\Repository;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Param\Param;

/**
 * Base class for all repositories.
 *
 * Provides thin wrappers around ConnectionInterface.
 * All SQL must be written in subclasses — no ORM magic.
 *
 * KPHP notes:
 *   - `object` return type is not supported in KPHP; use concrete class types in subclasses.
 *   - Constructor property promotion with readonly is not supported.
 *   - Subclasses must override fromRow() and declare a concrete return type.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
abstract class AbstractRepository
{
    /**
     * @var ConnectionInterface
     */
    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Map a raw database row to a domain DTO.
     * Subclasses must override and return their concrete DTO type.
     *
     * KPHP note: `object` return type is not supported.
     * Override this method in subclasses with a concrete return type.
     *
     * @param array<string, mixed> $row
     * @return mixed
     */
    abstract protected function fromRow(array $row): mixed;

    /**
     * Execute a SELECT and return the first mapped DTO, or null.
     *
     * @param array<string, Param> $params
     * @return mixed
     */
    protected function fetchOne(string $sql, array $params = []): mixed
    {
        $row = $this->connection->query($sql, $params)->fetchOne();

        return $row !== null ? $this->fromRow($row) : null;
    }

    /**
     * Execute a SELECT and return all mapped DTOs.
     *
     * @param array<string, Param>  $params
     * @return array<int, mixed>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $rows = $this->connection->query($sql, $params)->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->fromRow($row);
        }

        return $result;
    }

    /**
     * Execute an INSERT / UPDATE / DELETE and return affected row count.
     *
     * @param array<string, Param> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->connection->execute($sql, $params);
    }

    /**
     * Execute a raw SELECT and return the raw ResultInterface.
     *
     * @param array<string, Param> $params
     */
    protected function query(string $sql, array $params = []): ResultInterface
    {
        return $this->connection->query($sql, $params);
    }
}
