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
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
abstract class AbstractRepository
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
    ) {
    }

    /**
     * Map a raw database row to a domain DTO.
     *
     * @param array<string, mixed> $row
     */
    abstract protected function fromRow(array $row): object;

    /**
     * Execute a SELECT and return the first mapped DTO, or null.
     *
     * @param array<string, Param> $params
     */
    protected function fetchOne(string $sql, array $params = []): ?object
    {
        $row = $this->connection->query($sql, $params)->fetchOne();

        return $row !== null ? $this->fromRow($row) : null;
    }

    /**
     * Execute a SELECT and return all mapped DTOs.
     *
     * @param array<string, Param>   $params
     * @return array<int, object>
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

