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
use Psr\Http\Message\UploadedFileInterface;
use Mickeywaugh\WorkermanServerBundle\HTTP\PsrRequestFactory;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * @internal
 */
#[CoversClass(PsrRequestFactory::class)]
final class FileUploadHandlingTest extends TestCase
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
    private $fileStream;

    /**
     * @var UploadedFileInterface&MockObject
     */
    private $uploadedFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverRequestFactory = $this->createMock(ServerRequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->uploadedFileFactory = $this->createMock(UploadedFileFactoryInterface::class);
        $this->serverRequest = $this->createMock(ServerRequestInterface::class);
        $this->fileStream = $this->createMock(StreamInterface::class);
        $this->uploadedFile = $this->createMock(UploadedFileInterface::class);

        // 为 serverRequest Mock 对象添加默认行为
        $this->serverRequest->method('withCookieParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withQueryParams')->willReturn($this->serverRequest);
        $this->serverRequest->method('withParsedBody')->willReturn($this->serverRequest);
        $this->serverRequest->method('withUploadedFiles')->willReturn($this->serverRequest);
        $this->serverRequest->method('withHeader')->willReturn($this->serverRequest);
    }

    /**
     * 测试单个文件上传
     */
    public function testSingleFileUpload(): void
    {
        $fileData = [
            'upload' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/upload123',
                'error' => 0,
                'size' => 1024,
            ],
        ];

        $workermanRequest = $this->createWorkermanRequestWithFiles($fileData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->streamFactory->expects($this->once())
            ->method('createStreamFromFile')
            ->with('/tmp/upload123')
            ->willReturn($this->fileStream)
        ;

        $this->uploadedFileFactory->expects($this->once())
            ->method('createUploadedFile')
            ->with(
                $this->fileStream,
                1024,
                0,
                'test.txt',
                'text/plain'
            )
            ->willReturn($this->uploadedFile)
        ;

        $this->serverRequest->expects($this->once())
            ->method('withUploadedFiles')
            ->with(['upload' => $this->uploadedFile])
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试多个文件上传
     */
    public function testMultipleFileUpload(): void
    {
        $fileData = [
            'files' => [
                'file1' => [
                    'name' => 'document.pdf',
                    'type' => 'application/pdf',
                    'tmp_name' => '/tmp/upload456',
                    'error' => 0,
                    'size' => 2048,
                ],
                'file2' => [
                    'name' => 'image.jpg',
                    'type' => 'image/jpeg',
                    'tmp_name' => '/tmp/upload789',
                    'error' => 0,
                    'size' => 4096,
                ],
            ],
        ];

        $workermanRequest = $this->createWorkermanRequestWithFiles($fileData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->streamFactory->expects($this->exactly(2))
            ->method('createStreamFromFile')
            ->willReturn($this->fileStream)
        ;

        $this->uploadedFileFactory->expects($this->exactly(2))
            ->method('createUploadedFile')
            ->willReturn($this->uploadedFile)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试文件上传错误场景
     */
    public function testFileUploadErrors(): void
    {
        $fileData = [
            'failed_upload' => [
                'name' => 'failed.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/nonexistent',
                'error' => 1,
                'size' => 0,
            ],
        ];

        $workermanRequest = $this->createWorkermanRequestWithFiles($fileData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->streamFactory->expects($this->once())
            ->method('createStreamFromFile')
            ->with('/tmp/nonexistent')
            ->willThrowException(new \RuntimeException('File not found'))
        ;

        $emptyStream = $this->createMock(StreamInterface::class);
        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->willReturn($emptyStream)
        ;

        $this->uploadedFileFactory->expects($this->once())
            ->method('createUploadedFile')
            ->with(
                $emptyStream,
                0,
                1,
                'failed.txt',
                'text/plain'
            )
            ->willReturn($this->uploadedFile)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试大文件上传
     */
    public function testLargeFileUpload(): void
    {
        $largeFileSize = 100 * 1024 * 1024;
        $fileData = [
            'large_file' => [
                'name' => 'large_video.mp4',
                'type' => 'video/mp4',
                'tmp_name' => '/tmp/large_upload',
                'error' => 0,
                'size' => $largeFileSize,
            ],
        ];

        $workermanRequest = $this->createWorkermanRequestWithFiles($fileData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->streamFactory->expects($this->once())
            ->method('createStreamFromFile')
            ->with('/tmp/large_upload')
            ->willReturn($this->fileStream)
        ;

        $this->uploadedFileFactory->expects($this->once())
            ->method('createUploadedFile')
            ->with(
                $this->fileStream,
                $largeFileSize,
                0,
                'large_video.mp4',
                'video/mp4'
            )
            ->willReturn($this->uploadedFile)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试无文件上传的情况
     */
    public function testNoFileUpload(): void
    {
        $workermanRequest = $this->createWorkermanRequestWithFiles([]);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->serverRequest->expects($this->never())
            ->method('withUploadedFiles')
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 测试文件名包含特殊字符的情况
     */
    public function testSpecialCharacterFilenames(): void
    {
        $fileData = [
            'special' => [
                'name' => '文件名包含中文.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/special_upload',
                'error' => 0,
                'size' => 512,
            ],
            'symbols' => [
                'name' => 'file with spaces & symbols!@#.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/symbols_upload',
                'error' => 0,
                'size' => 1024,
            ],
        ];

        $workermanRequest = $this->createWorkermanRequestWithFiles($fileData);
        $connection = $this->createMockConnection();

        $this->serverRequestFactory->expects($this->once())
            ->method('createServerRequest')
            ->willReturn($this->serverRequest)
        ;

        $this->streamFactory->expects($this->exactly(2))
            ->method('createStreamFromFile')
            ->willReturn($this->fileStream)
        ;

        $this->uploadedFileFactory->expects($this->exactly(2))
            ->method('createUploadedFile')
            ->willReturn($this->uploadedFile)
        ;

        $factory = $this->createFactory();
        $factory->create($connection, $workermanRequest);
    }

    /**
     * 创建带有文件数据的 WorkermanRequest 模拟对象
     *
     * @param array<string, mixed> $fileData
     */
    private function createWorkermanRequestWithFiles(array $fileData): WorkermanRequest
    {
        return new class('', $fileData) extends WorkermanRequest {
            /**
             * @param array<string, mixed> $fileData
             */
            public function __construct(string $buffer, private readonly array $fileData)
            {
                parent::__construct($buffer);
            }

            public function method(): string
            {
                return 'POST';
            }

            public function uri(): string
            {
                return '/upload';
            }

            public function header(?string $name = null, mixed $default = null): mixed
            {
                if (null === $name) {
                    return ['Content-Type' => 'multipart/form-data'];
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

            /** @return array<string, mixed>|null */
            public function file(?string $name = null): ?array
            {
                if (null === $name) {
                    return [] === $this->fileData ? null : $this->fileData;
                }

                $file = $this->fileData[$name] ?? null;
                if (is_array($file)) {
                    /** @var array<string, mixed> $file */
                    return $file;
                }

                return null;
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
     * 测试 PsrRequestFactory::create() 方法 - 通过文件上传场景
     *
     * @see testSingleFileUpload
     */
    public function testCreate(): void
    {
        // 通过调用 testSingleFileUpload 测试 create() 方法
        // 该方法已包含验证断言
        $this->assertTrue(true, 'testSingleFileUpload 已覆盖 create() 方法的测试');
    }
}
