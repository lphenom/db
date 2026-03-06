<?php

declare(strict_types=1);

namespace LPhenom\Db\Param;

/**
 * Immutable value-object representing a bound SQL parameter with its PDO type.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class Param
{
    /**
     * @param int|string|bool|float|null $value
     * @param int                        $type  PDO::PARAM_* constant
     */
    public function __construct(
        public readonly mixed $value,
        public readonly int $type,
    ) {
    }
}
