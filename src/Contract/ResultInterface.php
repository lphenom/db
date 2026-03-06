<?php

declare(strict_types=1);

namespace LPhenom\Db\Contract;

/**
 * Database query result contract.
 *
 * Compatible with PHP 8.1+ and KPHP (no reflection/eval/magic).
 */
interface ResultInterface
{
    /**
     * Fetch a single row as an associative array, or null if no rows.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array;

    /**
     * Fetch all rows as an array of associative arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array;
}

