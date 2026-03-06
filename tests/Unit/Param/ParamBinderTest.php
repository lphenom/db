<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Param;

use LPhenom\Db\Param\Param;
use LPhenom\Db\Param\ParamBinder;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Db\Param\ParamBinder
 * @covers \LPhenom\Db\Param\Param
 */
final class ParamBinderTest extends TestCase
{
    public function testIntReturnsParamWithParamInt(): void
    {
        $param = ParamBinder::int(42);

        self::assertInstanceOf(Param::class, $param);
        self::assertSame(42, $param->value);
        self::assertSame(PDO::PARAM_INT, $param->type);
    }

    public function testStrReturnsParamWithParamStr(): void
    {
        $param = ParamBinder::str('hello');

        self::assertInstanceOf(Param::class, $param);
        self::assertSame('hello', $param->value);
        self::assertSame(PDO::PARAM_STR, $param->type);
    }

    public function testBoolTrueReturnsParamWithParamBool(): void
    {
        $param = ParamBinder::bool(true);

        self::assertInstanceOf(Param::class, $param);
        self::assertSame(true, $param->value);
        self::assertSame(PDO::PARAM_BOOL, $param->type);
    }

    public function testBoolFalseReturnsParamWithParamBool(): void
    {
        $param = ParamBinder::bool(false);

        self::assertSame(false, $param->value);
        self::assertSame(PDO::PARAM_BOOL, $param->type);
    }

    public function testNullReturnsParamWithParamNull(): void
    {
        $param = ParamBinder::null();

        self::assertInstanceOf(Param::class, $param);
        self::assertNull($param->value);
        self::assertSame(PDO::PARAM_NULL, $param->type);
    }

    public function testFloatReturnsParamAsStringWithParamStr(): void
    {
        $param = ParamBinder::float(3.14);

        self::assertInstanceOf(Param::class, $param);
        self::assertIsString($param->value);
        self::assertSame(PDO::PARAM_STR, $param->type);
        self::assertSame('3.14', $param->value);
    }

    public function testIntZeroIsValid(): void
    {
        $param = ParamBinder::int(0);

        self::assertSame(0, $param->value);
        self::assertSame(PDO::PARAM_INT, $param->type);
    }

    public function testStrEmptyStringIsValid(): void
    {
        $param = ParamBinder::str('');

        self::assertSame('', $param->value);
        self::assertSame(PDO::PARAM_STR, $param->type);
    }
}

