<?php

declare(strict_types=1);

namespace LPhenom\Db\Tests\Unit\Driver;

use LPhenom\Db\Driver\FfiConnectionStub;
use LPhenom\Db\Exception\NotImplementedException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Db\Driver\FfiConnectionStub
 */
final class FfiConnectionStubTest extends TestCase
{
    private FfiConnectionStub $stub;

    protected function setUp(): void
    {
        $this->stub = new FfiConnectionStub();
    }

    public function testQueryThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('FFI driver is not implemented');

        $this->stub->query('SELECT 1');
    }

    public function testExecuteThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('FFI driver is not implemented');

        $this->stub->execute('DELETE FROM users WHERE id = 1');
    }

    public function testTransactionThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('FFI driver is not implemented');

        $this->stub->transaction(static function (): void {
        });
    }
}

