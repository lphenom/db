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
 *   - mixed $value is NOT used — KPHP infers int|string|bool|null union which is unsupported.
 *   - Values are stored as string pre-representation for KPHP type safety.
 *   - NULL is indicated by bool $isNull flag; $value is '' when null.
 *   - PDO binding uses $isNull ? null : $value to pass proper null.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class Param
{
    /**
     * String representation of the value (numeric for int/bool, raw string for str/float).
     * Empty string when $isNull is true.
     *
     * @var string
     */
    public string $value;

    /**
     * @var int PDO::PARAM_* constant value (0=NULL, 1=INT, 2=STR, 5=BOOL)
     */
    public int $type;

    /**
     * Whether this parameter represents a SQL NULL value.
     *
     * @var bool
     */
    public bool $isNull;

    /**
     * @param string $value  String representation of the value; '' when $isNull is true
     * @param int    $type   PDO::PARAM_* constant (0=NULL, 1=INT, 2=STR, 5=BOOL)
     * @param bool   $isNull Whether the value is SQL NULL
     */
    public function __construct(string $value, int $type, bool $isNull = false)
    {
        $this->value  = $value;
        $this->type   = $type;
        $this->isNull = $isNull;
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
     * Value stored as its string representation.
     */
    public static function int(int $value): self
    {
        return new self((string) $value, 1);
    }

    /**
     * Create a boolean parameter (PDO::PARAM_BOOL = 5).
     * Stored as "1" (true) or "0" (false).
     */
    public static function bool(bool $value): self
    {
        return new self($value ? '1' : '0', 5);
    }

    /**
     * Create a null parameter (PDO::PARAM_NULL = 0).
     */
    public static function null(): self
    {
        return new self('', 0, true);
    }
}
