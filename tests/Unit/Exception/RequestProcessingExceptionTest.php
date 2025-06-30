<?php

namespace Tourze\WorkermanServerBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tourze\WorkermanServerBundle\Exception\RequestProcessingException;

class RequestProcessingExceptionTest extends TestCase
{
    public function testExceptionCreation(): void
    {
        $message = 'Test exception message';
        $code = 123;
        $previous = new RuntimeException('Previous exception');

        $exception = new RequestProcessingException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testExceptionWithDefaultValues(): void
    {
        $exception = new RequestProcessingException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithMessageOnly(): void
    {
        $message = 'Test message';
        $exception = new RequestProcessingException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}