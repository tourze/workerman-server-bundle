<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Mickeywaugh\WorkermanServerBundle\Exception\WorkermanServerException;

/**
 * @internal
 */
#[CoversClass(WorkermanServerException::class)]
final class WorkermanServerExceptionTest extends AbstractExceptionTestCase
{
    public function testPhpExecutableNotFound(): void
    {
        $exception = WorkermanServerException::phpExecutableNotFound();

        $this->assertInstanceOf(WorkermanServerException::class, $exception);
        $this->assertSame('PHP executable not found', $exception->getMessage());
    }
}
