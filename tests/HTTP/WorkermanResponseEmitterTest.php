<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tourze\WorkermanServerBundle\HTTP\WorkermanFileResponse;
use Tourze\WorkermanServerBundle\HTTP\WorkermanResponseEmitter;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

class WorkermanResponseEmitterTest extends TestCase
{
    public function testEmitNormalResponse(): void
    {
        // 创建模拟对象
        /** @var WorkermanRequest&MockObject $workermanRequest */
        $workermanRequest = $this->createMock(WorkermanRequest::class);
        /** @var ResponseInterface&MockObject $psrResponse */
        $psrResponse = $this->createMock(ResponseInterface::class);
        /** @var TcpConnection&MockObject $connection */
        $connection = $this->createMock(TcpConnection::class);
        /** @var StreamInterface&MockObject $body */
        $body = $this->createMock(StreamInterface::class);

        // 配置 WorkermanRequest
        $workermanRequest->expects($this->once())
            ->method('header')
            ->with('connection')
            ->willReturn('close');

        // 配置 PSR 响应
        $psrResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $psrResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('OK');

        $psrResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([
                'Content-Type' => ['application/json'],
                'X-Custom-Header' => ['Value1', 'Value2']
            ]);

        $psrResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        $body->expects($this->once())
            ->method('__toString')
            ->willReturn('{"response":"data"}');

        // 配置 connection
        $connection->expects($this->once())
            ->method('close')
            ->with($this->isInstanceOf('Workerman\Protocols\Http\Response'));

        // 创建测试目标
        $emitter = new WorkermanResponseEmitter();

        // 执行测试
        $emitter->emit($workermanRequest, $psrResponse, $connection);
    }

    public function testEmitFileResponse(): void
    {
        // 创建模拟对象
        /** @var WorkermanRequest&MockObject $workermanRequest */
        $workermanRequest = $this->createMock(WorkermanRequest::class);
        /** @var TcpConnection&MockObject $connection */
        $connection = $this->createMock(TcpConnection::class);

        // 配置 WorkermanRequest
        $workermanRequest->expects($this->once())
            ->method('header')
            ->with('connection')
            ->willReturn('close');

        // 创建文件响应
        $fileResponse = new WorkermanFileResponse();
        $fileResponse->setFile(__FILE__); // 使用当前测试文件作为测试文件

        // 配置 connection
        $connection->expects($this->once())
            ->method('close')
            ->with($this->isInstanceOf('Workerman\Protocols\Http\Response'));

        // 创建测试目标
        $emitter = new WorkermanResponseEmitter();

        // 执行测试
        $emitter->emit($workermanRequest, $fileResponse, $connection);
    }

    public function testEmitWithKeepAlive(): void
    {
        // 创建模拟对象
        /** @var WorkermanRequest&MockObject $workermanRequest */
        $workermanRequest = $this->createMock(WorkermanRequest::class);
        /** @var ResponseInterface&MockObject $psrResponse */
        $psrResponse = $this->createMock(ResponseInterface::class);
        /** @var TcpConnection&MockObject $connection */
        $connection = $this->createMock(TcpConnection::class);
        /** @var StreamInterface&MockObject $body */
        $body = $this->createMock(StreamInterface::class);

        // 配置 WorkermanRequest (Keep-Alive)
        $workermanRequest->expects($this->once())
            ->method('header')
            ->with('connection')
            ->willReturn('Keep-Alive');

        // 配置 PSR 响应
        $psrResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $psrResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('OK');

        $psrResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]);

        $psrResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        $body->expects($this->once())
            ->method('__toString')
            ->willReturn('');

        // 配置 connection (使用 send 而不是 close)
        $connection->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf('Workerman\Protocols\Http\Response'));

        // 创建测试目标
        $emitter = new WorkermanResponseEmitter();

        // 执行测试
        $emitter->emit($workermanRequest, $psrResponse, $connection);
    }
}
