<?php

declare(strict_types=1);

namespace LPhenom\Db\Param;

/**
 * Immutable value-object representing a bound SQL parameter with its PDO type.
 *
 * KPHP note: mixed is not supported in KPHP — value uses explicit union type.
 * Compatible with PHP 8.1+ and KPHP.
 */
final class Param
{
    /**
     * @param int|string|bool|float|null $value
     * @param int                        $type  PDO::PARAM_* constant value (0=NULL, 1=INT, 2=STR, 5=BOOL)
     */
    public function __construct(
        public readonly int|string|bool|float|null $value,
        public readonly int $type,
    ) {
    }
}
