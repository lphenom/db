<?php

declare(strict_types=1);

namespace LPhenom\Db\Param;

use PDO;

/**
 * Factory for creating type-safe PDO-bound parameters.
 *
 * PDO does not have a PARAM_FLOAT constant, so float values are stored
 * as strings (PDO::PARAM_STR) to preserve precision and avoid silent casting.
 *
 * Compatible with PHP 8.1+ and KPHP.
 */
final class ParamBinder
{
    private function __construct()
    {
    }

    /**
     * Bind an integer value using PDO::PARAM_INT.
     */
    public static function int(int $value): Param
    {
        return new Param($value, PDO::PARAM_INT);
    }

    /**
     * Bind a string value using PDO::PARAM_STR.
     */
    public static function str(string $value): Param
    {
        return new Param($value, PDO::PARAM_STR);
    }

    /**
     * Bind a boolean value using PDO::PARAM_BOOL.
     */
    public static function bool(bool $value): Param
    {
        return new Param($value, PDO::PARAM_BOOL);
    }

    /**
     * Bind a null value using PDO::PARAM_NULL.
     */
    public static function null(): Param
    {
        return new Param(null, PDO::PARAM_NULL);
    }

    /**
     * Bind a float value as a string using PDO::PARAM_STR.
     *
     * PDO has no PARAM_FLOAT; string representation preserves decimal precision.
     */
    public static function float(float $value): Param
    {
        return new Param((string) $value, PDO::PARAM_STR);
    }
}
