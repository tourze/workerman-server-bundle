<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Tourze\WorkermanServerBundle\HTTP\PsrRequestFactory;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

class PsrRequestFactoryTest extends TestCase
{
    /**
     * @var ServerRequestFactoryInterface&MockObject
     */
    private $serverRequestFactory;

    /**
     * @var StreamFactoryInterface&MockObject
     */
    private $streamFactory;

    /**
     * @var UploadedFileFactoryInterface&MockObject
     */
    private $uploadedFileFactory;

    /**
     * @var ServerRequestInterface&MockObject
     */
    private $serverRequest;

    /**
     * @var StreamInterface&MockObject
     */
    private $bodyStream;

    protected function setUp(): void
    {
        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);

        $this->serverRequest = $this->createMock(ServerRequestInterface::class);
        $this->bodyStream = $this->createMock(StreamInterface::class);

        // 配置serverRequest以支持 with* 方法链
        $this->serverRequest->method('withHeader')
            ->willReturnSelf();
        $this->serverRequest->method('withCookieParams')
            ->willReturnSelf();
        $this->serverRequest->method('withQueryParams')
            ->willReturnSelf();
        $this->serverRequest->method('withParsedBody')
            ->willReturnSelf();
        $this->serverRequest->method('withUploadedFiles')
            ->willReturnSelf();
        $this->serverRequest->method('getBody')
            ->willReturn($this->bodyStream);
    }

    public function testCreate(): void
    {
        // 创建模拟的 WorkermanRequest 和 TcpConnection
        /** @var WorkermanRequest&MockObject $workermanRequest */
        $workermanRequest = $this->createMock(WorkermanRequest::class);
        /** @var TcpConnection&MockObject $connection */
        $connection = $this->createMock(TcpConnection::class);

        // 模拟 Workerman 请求的方法返回
        $workermanRequest->expects($this->once())
            ->method('method')
            ->willReturn('GET');

        $workermanRequest->expects($this->once())
            ->method('uri')
            ->willReturn('/test');

        $connection->method('getRemoteIp')
            ->willReturn('127.0.0.1');

        $connection->method('getRemotePort')
            ->willReturn(1234);

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Host' => 'example.com'
        ];

        $workermanRequest->expects($this->once())
            ->method('header')
            ->willReturn($expectedHeaders);

        $workermanRequest->expects($this->once())
            ->method('cookie')
            ->willReturn(['session' => '123']);

        $workermanRequest->expects($this->once())
            ->method('get')
            ->willReturn(['foo' => 'bar']);

        $workermanRequest->expects($this->once())
            ->method('post')
            ->willReturn(['baz' => 'qux']);

        $workermanRequest->expects($this->once())
            ->method('file')
            ->willReturn([]);

        $workermanRequest->expects($this->once())
            ->method('rawBody')
            ->willReturn('{"test":"data"}');

        // 配置 ServerRequestFactory 创建 ServerRequest
        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->with(
                'GET',
                '/test',
                [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'REMOTE_PORT' => '1234',
                ]
            )
            ->willReturn($this->serverRequest);

        // 配置 bodyStream 的 write 方法
        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with('{"test":"data"}');

        // 创建测试目标
        $factory = new PsrRequestFactory(
            $this->serverRequestFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );

        // 执行测试
        $result = $factory->create($connection, $workermanRequest);

        // 验证结果
        $this->assertSame($this->serverRequest, $result);
    }
}
