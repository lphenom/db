<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Param;

use LPhenom\Db\Param\Param;
use LPhenom\Db\Param\ParamBinder;
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
        // KPHP-compat: value stored as string representation
        self::assertSame('42', $param->value);
        self::assertSame(ParamBinder::PARAM_INT, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testStrReturnsParamWithParamStr(): void
    {
        $param = ParamBinder::str('hello');

        self::assertInstanceOf(Param::class, $param);
        self::assertSame('hello', $param->value);
        self::assertSame(ParamBinder::PARAM_STR, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testBoolTrueReturnsParamWithParamBool(): void
    {
        $param = ParamBinder::bool(true);

        self::assertInstanceOf(Param::class, $param);
        // KPHP-compat: bool stored as "1"/"0" string
        self::assertSame('1', $param->value);
        self::assertSame(ParamBinder::PARAM_BOOL, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testBoolFalseReturnsParamWithParamBool(): void
    {
        $param = ParamBinder::bool(false);

        self::assertSame('0', $param->value);
        self::assertSame(ParamBinder::PARAM_BOOL, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testNullReturnsParamWithParamNull(): void
    {
        $param = ParamBinder::null();

        self::assertInstanceOf(Param::class, $param);
        // KPHP-compat: null represented by isNull flag
        self::assertTrue($param->isNull);
        self::assertSame('', $param->value);
        self::assertSame(ParamBinder::PARAM_NULL, $param->type);
    }

    public function testFloatReturnsParamAsStringWithParamStr(): void
    {
        $param = ParamBinder::float(3.14);

        self::assertInstanceOf(Param::class, $param);
        self::assertIsString($param->value);
        self::assertSame(ParamBinder::PARAM_STR, $param->type);
        self::assertSame('3.14', $param->value);
        self::assertFalse($param->isNull);
    }

    public function testIntZeroIsValid(): void
    {
        $param = ParamBinder::int(0);

        self::assertSame('0', $param->value);
        self::assertSame(ParamBinder::PARAM_INT, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testStrEmptyStringIsValid(): void
    {
        $param = ParamBinder::str('');

        self::assertSame('', $param->value);
        self::assertSame(ParamBinder::PARAM_STR, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testParamDirectConstructionStr(): void
    {
        $param = new Param('hello', ParamBinder::PARAM_STR);

        self::assertSame('hello', $param->value);
        self::assertSame(ParamBinder::PARAM_STR, $param->type);
        self::assertFalse($param->isNull);
    }

    public function testParamDirectConstructionNull(): void
    {
        $param = new Param('', ParamBinder::PARAM_NULL, true);

        self::assertTrue($param->isNull);
        self::assertSame(ParamBinder::PARAM_NULL, $param->type);
    }
}
