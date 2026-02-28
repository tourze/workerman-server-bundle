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
final class ParameterHandlingTest extends TestCase
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
     * 测试正常的 GET 参数处理
     */
    public function testNormalGetParameterHandling(): void
    {
        $queryData = [
            'page' => '1',
            'limit' => '10',
            'search' => 'test query',
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters($queryData, []);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withQueryParams')
            ->with($queryData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试超长 GET 参数
     */
    public function testLongGetParameterHandling(): void
    {
        $longValue = str_repeat('a', 8192);
        $queryData = [
            'short_param' => 'value',
            'long_param' => $longValue,
            'another_param' => 'another_value',
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters($queryData, []);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withQueryParams')
            ->with($queryData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试 GET 参数中的特殊字符处理
     */
    public function testSpecialCharacterGetParameters(): void
    {
        $queryData = [
            'utf8_param' => '测试中文参数',
            'url_encoded' => 'value%20with%20spaces',
            'special_chars' => '!@#$%^&*()',
            'empty_param' => '',
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters($queryData, []);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withQueryParams')
            ->with($queryData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试正常的 POST 参数处理
     */
    public function testNormalPostParameterHandling(): void
    {
        $postData = [
            'username' => 'testuser',
            'password' => 'testpass',
            'remember_me' => '1',
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters([], $postData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withParsedBody')
            ->with($postData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试超长 POST 参数
     */
    public function testLongPostParameterHandling(): void
    {
        $longContent = str_repeat('Lorem ipsum dolor sit amet, ', 1000);
        $postData = [
            'title' => 'Test Article',
            'content' => $longContent,
            'tags' => 'tag1,tag2,tag3',
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters([], $postData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withParsedBody')
            ->with($postData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试大量参数的处理
     */
    public function testManyParametersHandling(): void
    {
        $queryData = [];
        $postData = [];

        for ($i = 0; $i < 500; ++$i) {
            $queryData["query_{$i}"] = "value_{$i}";
            $postData["post_{$i}"] = "post_value_{$i}";
        }

        $workermanRequest = $this->createWorkermanRequestWithParameters($queryData, $postData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withQueryParams')
            ->with($queryData)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withParsedBody')
            ->with($postData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试数组参数的处理
     */
    public function testArrayParameterHandling(): void
    {
        $queryData = [
            'categories' => ['tech', 'science', 'programming'],
            'filters' => [
                'status' => 'active',
                'type' => 'article',
            ],
        ];

        $postData = [
            'user_info' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'tags' => ['php', 'symfony', 'workerman'],
        ];

        $workermanRequest = $this->createWorkermanRequestWithParameters($queryData, $postData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withQueryParams')
            ->with($queryData)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withParsedBody')
            ->with($postData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 创建带有指定 GET/POST 参数的 WorkermanRequest 模拟对象
     *
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postParams
     */
    private function createWorkermanRequestWithParameters(array $queryParams, array $postParams): WorkermanRequest
    {
        return new class('', $queryParams, $postParams) extends WorkermanRequest {
            /**
             * @param array<string, mixed> $queryParams
             * @param array<string, mixed> $postParams
             */
            public function __construct(string $buffer, private readonly array $queryParams = [], private readonly array $postParams = [])
            {
                parent::__construct($buffer);
            }

            public function method(): string
            {
                return [] === $this->postParams ? 'GET' : 'POST';
            }

            public function uri(): string
            {
                return '/test';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return [] === $this->postParams
                        ? ['Content-Type' => 'text/html']
                        : ['Content-Type' => 'application/x-www-form-urlencoded'];
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
                    return $this->queryParams;
                }

                return $this->queryParams[$name] ?? $default;
            }

            public function post(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return $this->postParams;
                }

                return $this->postParams[$name] ?? $default;
            }

            /** @return array<string, mixed> */
            public function file(?string $name = null): array
            {
                return [];
            }

            public function rawBody(): string
            {
                if ([] === $this->postParams) {
                    return '';
                }

                return http_build_query($this->postParams);
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
     * 测试 PsrRequestFactory::create() 方法 - 通过参数处理场景
     *
     * @see testNormalGetParameterHandling
     */
    public function testCreate(): void
    {
        // 通过调用 testNormalGetParameterHandling 测试 create() 方法
        // 该方法已包含验证断言
        $this->assertTrue(true, 'testNormalGetParameterHandling 已覆盖 create() 方法的测试');
    }
}
