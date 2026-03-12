<?php

declare(strict_types=1);

namespace LPhenom\Db\Param;

/**
 * @lphenom-build shared,kphp
 *
 * Factory for creating type-safe bound parameters.
 *
 * Uses integer constants matching PDO::PARAM_* values so that the same
 * code compiles under KPHP (which has no PDO extension) and runs under
 * standard PHP with PDO.
 *
 * Constant mapping:
 *   PARAM_NULL  = 0  (PDO::PARAM_NULL)
 *   PARAM_INT   = 1  (PDO::PARAM_INT)
 *   PARAM_STR   = 2  (PDO::PARAM_STR)
 *   PARAM_BOOL  = 5  (PDO::PARAM_BOOL)
 *
 * PDO does not have a PARAM_FLOAT constant, so float values are stored
 * as strings (PARAM_STR = 2) to preserve precision and avoid silent casting.
 *
 * KPHP note: Param::$value is string (not mixed) to avoid KPHP union-type inference
 * errors (int|string|bool|null is not a valid KPHP union). All values are
 * pre-formatted as strings. Null is indicated by Param::$isNull = true.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class ParamBinder
{
    /** @internal PDO::PARAM_NULL = 0 */
    public const PARAM_NULL = 0;

    /** @internal PDO::PARAM_INT = 1 */
    public const PARAM_INT = 1;

    /** @internal PDO::PARAM_STR = 2 */
    public const PARAM_STR = 2;

    /** @internal PDO::PARAM_BOOL = 5 */
    public const PARAM_BOOL = 5;

    private function __construct()
    {
    }

    /**
     * Bind an integer value (PARAM_INT = 1).
     * Stored as string representation: e.g. "42".
     */
    public static function int(int $value): Param
    {
        return new Param((string) $value, self::PARAM_INT);
    }

    /**
     * Bind a string value (PARAM_STR = 2).
     */
    public static function str(string $value): Param
    {
        return new Param($value, self::PARAM_STR);
    }

    /**
     * Bind a boolean value (PARAM_BOOL = 5).
     * Stored as "1" (true) or "0" (false).
     */
    public static function bool(bool $value): Param
    {
        return new Param($value ? '1' : '0', self::PARAM_BOOL);
    }

    /**
     * Bind a null value (PARAM_NULL = 0).
     * Param::$isNull is set to true; PDO receives null.
     */
    public static function null(): Param
    {
        return new Param('', self::PARAM_NULL, true);
    }

    /**
     * Bind a float value as a string (PARAM_STR = 2).
     *
     * PDO has no PARAM_FLOAT; string representation preserves decimal precision.
     */
    public static function float(float $value): Param
    {
        return new Param((string) $value, self::PARAM_STR);
    }
}
