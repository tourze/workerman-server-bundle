<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Mickeywaugh\WorkermanServerBundle\HTTP\PsrRequestFactory;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * @internal
 */
#[CoversClass(PsrRequestFactory::class)]
final class PsrRequestFactoryTest extends TestCase
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
        parent::setUp();

        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);

        $this->serverRequest = $this->createMock(ServerRequestInterface::class);
        $this->bodyStream = $this->createMock(StreamInterface::class);

        // 为 serverRequest Mock 对象添加默认行为
        $this->serverRequest->method('withCookieParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withQueryParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withParsedBody')->willReturn($this->serverRequest);
        $this->serverRequest->method('withUploadedFiles')->willReturn($this->serverRequest);
        $this->serverRequest->method('withHeader')->willReturn($this->serverRequest);
        $this->serverRequest->method('getBody')
            ->willReturn($this->bodyStream)
        ;
    }

    public function testCreate(): void
    {
        // 创建一个简化的测试类来模拟 WorkermanRequest
        // 这避免了 PHPUnit 12 对 method() 方法名的弃用警告
        $workermanRequest = new class('') extends WorkermanRequest {
            public function method(): string
            {
                return 'GET';
            }

            public function uri(): string
            {
                return '/test';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['Content-Type' => 'application/json', 'Host' => 'example.com'];
                }

                return $default;
            }

            public function cookie(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['session' => '123'];
                }

                return $default;
            }

            public function get(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['foo' => 'bar'];
                }

                return $default;
            }

            public function post(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['baz' => 'qux'];
                }

                return $default;
            }

            /** @return array<string, mixed> */
            public function file(?string $name = null): array
            {
                return [];
            }

            public function rawBody(): string
            {
                return '{"test":"data"}';
            }
        };

        // 使用 TcpConnection 具体类 mock 是必要的，因为：
        // 1. Workerman 的 TcpConnection 类没有对应的接口
        // 2. 该类是 Workerman 框架的核心组件，无法替换为抽象接口
        // 3. 测试需要验证与 Workerman 网络层的集成行为
        $connection = $this->createMock(TcpConnection::class);

        // 配置连接对象的行为
        $connection->method('getRemoteIp')
            ->willReturn('127.0.0.1')
        ;

        $connection->method('getRemotePort')
            ->willReturn(1234)
        ;

        $expectedHeaders = [
            'Content-Type' => 'application/json',
            'Host' => 'example.com',
        ];

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
            ->willReturn($this->serverRequest)
        ;

        // 配置 bodyStream 的 write 方法
        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with('{"test":"data"}')
        ;

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
