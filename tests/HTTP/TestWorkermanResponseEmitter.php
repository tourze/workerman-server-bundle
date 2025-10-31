<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * 测试用的响应发送器
 */
class TestWorkermanResponseEmitter
{
    private bool $called = false;

    public function __construct(private readonly mixed $testCase = null)
    {
    }

    public function emit(WorkermanRequest $request, ResponseInterface $response, TcpConnection $connection): void
    {
        $this->called = true;
        if (null !== $this->testCase
            && is_object($this->testCase)
            && method_exists($this->testCase, 'assertSame')
            && property_exists($this->testCase, 'request')
            && property_exists($this->testCase, 'psrResponse')
            && property_exists($this->testCase, 'connection')) {
            $this->testCase->assertSame($this->testCase->request, $request);
            $this->testCase->assertSame($this->testCase->psrResponse, $response);
            $this->testCase->assertSame($this->testCase->connection, $connection);
        }
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }
}
