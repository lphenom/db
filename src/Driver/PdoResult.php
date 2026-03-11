<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use LPhenom\Db\Contract\ResultInterface;
use PDOStatement;

/**
 * @lphenom-build shared
 *
 * PDO-backed result set.
 *
 * Compatible with PHP 8.1+ (no reflection/eval/magic).
 * Note: PDO is not available in KPHP; use FfiMySqlResult in compiled mode.
 * KPHP note: constructor property promotion with readonly is not supported.
 */
final class PdoResult implements ResultInterface
{
    /** @var PDOStatement */
    private PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
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
