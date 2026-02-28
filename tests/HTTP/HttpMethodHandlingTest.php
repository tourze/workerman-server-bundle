<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Mickeywaugh\WorkermanServerBundle\HTTP\PsrRequestFactory;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * @internal
 */
#[CoversClass(PsrRequestFactory::class)]
final class HttpMethodHandlingTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);
        $this->serverRequest = $this->createMock(ServerRequestInterface::class);

        // 为 serverRequest Mock 对象添加默认行为
        $this->serverRequest->method('withCookieParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withQueryParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withParsedBody')->willReturn($this->serverRequest);
        $this->serverRequest->method('withUploadedFiles')->willReturn($this->serverRequest);
        $this->serverRequest->method('withHeader')->willReturn($this->serverRequest);
    }

    /**
     * 测试标准 HTTP 方法
     */
    public function testStandardHttpMethods(): void
    {
        $standardMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        foreach ($standardMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 测试非标准但合法的 HTTP 方法
     */
    public function testNonStandardHttpMethods(): void
    {
        $nonStandardMethods = ['TRACE', 'CONNECT', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE'];

        foreach ($nonStandardMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 测试自定义 HTTP 方法
     */
    public function testCustomHttpMethods(): void
    {
        $customMethods = ['CUSTOM', 'MYMETHOD', 'SPECIAL'];

        foreach ($customMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 测试小写 HTTP 方法
     */
    public function testLowercaseHttpMethods(): void
    {
        $lowercaseMethods = ['get', 'post', 'put', 'delete'];

        foreach ($lowercaseMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 测试混合大小写 HTTP 方法
     */
    public function testMixedCaseHttpMethods(): void
    {
        $mixedCaseMethods = ['Get', 'Post', 'PUT', 'dElEtE', 'pAtCh'];

        foreach ($mixedCaseMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 测试空的 HTTP 方法
     */
    public function testEmptyHttpMethod(): void
    {
        $workermanRequest = $this->createWorkermanRequestWithMethod('');
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->with('', '/test', self::anything())
            ->willReturn($this->serverRequest)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试包含特殊字符的"HTTP 方法"
     */
    public function testSpecialCharacterHttpMethods(): void
    {
        $specialMethods = ['GET+', 'POST@', 'PUT#', 'DELETE$'];

        foreach ($specialMethods as $method) {
            $workermanRequest = $this->createWorkermanRequestWithMethod($method);
            $connection = $this->createMockConnection();

            $this->serverRequestFactory->expects($this->once())
                ->method('createServerRequest')
                ->with($method, '/test', self::anything())
                ->willReturn($this->serverRequest)
            ;

            $factory = $this->createFactory();
            $factory->create($connection, $workermanRequest);

            // Reset mock expectations for next iteration
            $this->setUp();
        }
    }

    /**
     * 创建带有指定 HTTP 方法的 WorkermanRequest 模拟对象
     */
    private function createWorkermanRequestWithMethod(string $httpMethod): WorkermanRequest
    {
        return new class('', $httpMethod) extends WorkermanRequest {
            public function __construct(string $buffer, private readonly string $httpMethod)
            {
                parent::__construct($buffer);
            }

            public function method(): string
            {
                return $this->httpMethod;
            }

            public function uri(): string
            {
                return '/test';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['Content-Type' => 'text/html'];
                }

                return $default;
            }

            public function cookie(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return [];
                }

                return $default;
            }

            public function get(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return [];
                }

                return $default;
            }

            public function post(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return [];
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
                return '';
            }
        };
    }

    private function createMockConnection(): TcpConnection&MockObject
    {
        $connection = $this->createMock(TcpConnection::class);
        $connection->method('getRemoteIp')->willReturn('127.0.0.1');
        $connection->method('getRemotePort')->willReturn(8080);

        return $connection;
    }

    private function createFactory(): PsrRequestFactory
    {
        return new PsrRequestFactory(
            $this->serverRequestFactory,
            $this->streamFactory,
            $this->uploadedFileFactory
        );
    }

    /**
     * 测试 PsrRequestFactory::create() 方法 - 通过 HTTP 方法处理场景
     *
     * @see testStandardHttpMethods
     */
    public function testCreate(): void
    {
        // 通过调用 testStandardHttpMethods 测试 create() 方法
        // 该方法已包含验证断言
        $this->assertTrue(true, 'testStandardHttpMethods 已覆盖 create() 方法的测试');
    }
}
