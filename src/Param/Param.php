<?php

declare(strict_types=1);

namespace LPhenom\Db\Param;

/**
 * @lphenom-build shared,kphp
 *
 * Immutable value-object representing a bound SQL parameter with its PDO type.
 *
 * KPHP notes:
 *   - Constructor property promotion with readonly is not supported.
 *   - mixed is not used — explicit union type is used instead.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class Param
{
    /**
     * @var int|string|bool|float|null
     */
    public int|string|bool|float|null $value;

    /**
     * @var int PDO::PARAM_* constant value (0=NULL, 1=INT, 2=STR, 5=BOOL)
     */
    public int $type;

    /**
     * @param int|string|bool|float|null $value
     * @param int                        $type  PDO::PARAM_* constant value (0=NULL, 1=INT, 2=STR, 5=BOOL)
     */
    public function __construct(int|string|bool|float|null $value, int $type)
    {
        $this->value = $value;
        $this->type  = $type;
    }

    /**
     * Create a string parameter (PDO::PARAM_STR = 2).
     */
    public static function str(string $value): self
    {
        return new self($value, 2);
    }

    /**
     * Create an integer parameter (PDO::PARAM_INT = 1).
     */
    public static function int(int $value): self
    {
        return new self($value, 1);
    }

    /**
     * Create a boolean parameter (PDO::PARAM_BOOL = 5).
     */
    public static function bool(bool $value): self
    {
        return new self($value, 5);
    }

    /**
     * Create a null parameter (PDO::PARAM_NULL = 0).
     */
    public static function null(): self
    {
        return new self(null, 0);
    }
}
