<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ResultInterface;
use PDOStatement;

/**
 * PDO-backed result set.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
final class PdoResult implements ResultInterface
{
    public function __construct(
        private readonly PDOStatement $statement,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }
}

