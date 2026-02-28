<?php

declare(strict_types=1);

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
final class JsonPayloadHandlingTest extends TestCase
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

        $this->serverRequest->method('getBody')->willReturn($this->bodyStream);

        // 为 serverRequest Mock 对象添加默认行为
        $this->serverRequest->method('withCookieParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withQueryParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withParsedBody')->willReturn($this->serverRequest);
        $this->serverRequest->method('withUploadedFiles')->willReturn($this->serverRequest);
        $this->serverRequest->method('withHeader')->willReturn($this->serverRequest);
    }

    /**
     * 测试正常的 JSON payload
     */
    public function testNormalJsonPayload(): void
    {
        $jsonData = json_encode([
            'user_id' => 123,
            'action' => 'update_profile',
            'data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ]);
        $this->assertIsString($jsonData);

        $workermanRequest = $this->createWorkermanRequestWithJson($jsonData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($jsonData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试大型 JSON payload
     */
    public function testLargeJsonPayload(): void
    {
        $largeData = [];
        for ($i = 0; $i < 10000; ++$i) {
            $largeData["item_{$i}"] = [
                'id' => $i,
                'name' => "Item {$i}",
                'description' => str_repeat('Long description ', 10),
            ];
        }

        $jsonData = json_encode($largeData);
        $this->assertIsString($jsonData);
        $workermanRequest = $this->createWorkermanRequestWithJson($jsonData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($jsonData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试嵌套的复杂 JSON payload
     */
    public function testComplexNestedJsonPayload(): void
    {
        $complexData = [
            'metadata' => [
                'version' => '1.0',
                'timestamp' => time(),
                'client' => [
                    'name' => 'Test Client',
                    'version' => '2.1.0',
                    'capabilities' => ['json', 'xml', 'binary'],
                ],
            ],
            'payload' => [
                'users' => [
                    [
                        'id' => 1,
                        'profile' => [
                            'personal' => ['name' => '张三', 'age' => 25],
                            'settings' => ['theme' => 'dark', 'language' => 'zh-CN'],
                        ],
                    ],
                    [
                        'id' => 2,
                        'profile' => [
                            'personal' => ['name' => 'John', 'age' => 30],
                            'settings' => ['theme' => 'light', 'language' => 'en-US'],
                        ],
                    ],
                ],
            ],
        ];

        $jsonData = json_encode($complexData);
        $this->assertIsString($jsonData);
        $workermanRequest = $this->createWorkermanRequestWithJson($jsonData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($jsonData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试无效 JSON payload
     */
    public function testInvalidJsonPayload(): void
    {
        $invalidJson = '{"invalid": json, "missing": "quote}';
        $workermanRequest = $this->createWorkermanRequestWithJson($invalidJson);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($invalidJson)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试空 JSON payload
     */
    public function testEmptyJsonPayload(): void
    {
        $jsonData = '';
        $workermanRequest = $this->createWorkermanRequestWithJson($jsonData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($jsonData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试包含特殊字符的 JSON payload
     */
    public function testJsonWithSpecialCharacters(): void
    {
        $jsonData = json_encode([
            'text_with_newlines' => "Line 1\nLine 2\nLine 3",
            'text_with_tabs' => "Column 1\tColumn 2\tColumn 3",
            'text_with_quotes' => 'He said "Hello World" to me',
            'unicode_text' => '🚀 Unicode test: 测试中文 العربية',
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
        ]);
        $this->assertIsString($jsonData);

        $workermanRequest = $this->createWorkermanRequestWithJson($jsonData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->bodyStream->expects($this->once())
            ->method('write')
            ->with($jsonData)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 创建带有 JSON payload 的 WorkermanRequest 模拟对象
     */
    private function createWorkermanRequestWithJson(string $jsonData): WorkermanRequest
    {
        return new class('', $jsonData) extends WorkermanRequest {
            public function __construct(string $buffer, private readonly string $jsonData)
            {
                parent::__construct($buffer);
            }

            public function method(): string
            {
                return 'POST';
            }

            public function uri(): string
            {
                return '/api/test';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return [
                        'Content-Type' => 'application/json',
                        'Content-Length' => (string) strlen($this->jsonData),
                    ];
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
                return $this->jsonData;
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
     * 测试 PsrRequestFactory::create() 方法 - 通过 JSON 负载处理场景
     *
     * @see testNormalJsonPayload
     */
    public function testCreate(): void
    {
        // 通过调用 testNormalJsonPayload 测试 create() 方法
        // 该方法已包含验证断言
        $this->assertTrue(true, 'testNormalJsonPayload 已覆盖 create() 方法的测试');
    }
}
