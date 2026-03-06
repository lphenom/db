<?php

declare(strict_types=1);

namespace LPhenom\Db\Driver;

use FFI;
use LPhenom\Db\Contract\ResultInterface;

/**
 * ResultInterface backed by a MySQL FFI result set (MYSQL_RES*).
 *
 * Wraps mysql_fetch_row / mysql_fetch_fields calls.
 * Frees the result resource on destruction.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class FfiMySqlResult implements ResultInterface
{
    /** @var array<string>|null */
    private ?array $columnNames = null;

    public function __construct(
        private readonly FFI       $ffi,
        private readonly FFI\CData $result,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        $row = $this->ffi->mysql_fetch_row($this->result);
        if ($row === null) {
            return null;
        }

        return $this->rowToAssoc($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        $rows = [];
        while (true) {
            $row = $this->ffi->mysql_fetch_row($this->result);
            if ($row === null) {
                break;
            }
            $rows[] = $this->rowToAssoc($row);
        }

        return $rows;
    }

    public function __destruct()
    {
        $this->ffi->mysql_free_result($this->result);
    }

    /**
     * @param FFI\CData $row MYSQL_ROW (char**)
     * @return array<string, mixed>
     */
    private function rowToAssoc(FFI\CData $row): array
    {
        $columns = $this->getColumnNames();
        $numFields = count($columns);
        $assoc = [];

        for ($i = 0; $i < $numFields; $i++) {
            $cell = $row[$i];
            $assoc[$columns[$i]] = $cell !== null ? FFI::string($cell) : null;
        }

        return $assoc;
    }

    /**
     * Lazily load column names from MYSQL_FIELD descriptors.
     *
     * @return array<string>
     */
    private function getColumnNames(): array
    {
        if ($this->columnNames !== null) {
            return $this->columnNames;
        }

        $num = (int) $this->ffi->mysql_num_fields($this->result);
        $fields = $this->ffi->mysql_fetch_fields($this->result);

        $names = [];
        for ($i = 0; $i < $num; $i++) {
            $names[] = FFI::string($fields[$i]->name);
        }

        $this->columnNames = $names;

        return $names;
    }
}
