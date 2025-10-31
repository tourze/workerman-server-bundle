<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tourze\WorkermanServerBundle\Exception\RequestProcessingException;
use Tourze\WorkermanServerBundle\Exception\WorkermanServerException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * @internal
 */
#[CoversClass(TestOnMessage::class)]
final class OnMessageEdgeCaseTest extends TestCase
{
    /**
     * @var RequestHandlerInterface&MockObject
     */
    private $requestHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
    }

    /**
     * 测试请求处理过程中发生异常时的处理
     */
    public function testExceptionHandling(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $workermanRequest = new class('') extends WorkermanRequest {
        };

        $psrRequest = $this->createMock(ServerRequestInterface::class);

        $psrRequestFactory = new TestPsrRequestFactory($psrRequest);
        $responseEmitter = new TestWorkermanResponseEmitter();

        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($psrRequest)
            ->willThrowException(new \RuntimeException('Request handling failed'))
        ;

        $mockOutput = $this->createMock(OutputInterface::class);
        $mockOutput->expects($this->once())
            ->method('writeln')
            ->with(self::stringContains('Request handling failed'))
        ;

        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $this->requestHandler,
            $mockOutput
        );

        $onMessage($connection, $workermanRequest);
    }

    /**
     * 测试 PSR 请求创建过程中发生异常
     */
    public function testPsrRequestCreationException(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $workermanRequest = new class('') extends WorkermanRequest {
        };

        $psrRequestFactory = new TestPsrRequestFactory(null, true);
        $responseEmitter = new TestWorkermanResponseEmitter();

        $mockOutput = $this->createMock(OutputInterface::class);
        $mockOutput->expects($this->once())
            ->method('writeln')
            ->with(self::stringContains('Test exception'))
        ;

        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $this->requestHandler,
            $mockOutput
        );

        $onMessage($connection, $workermanRequest);
    }

    /**
     * 测试正常流程（无异常）
     */
    public function testNormalFlow(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $workermanRequest = new class('') extends WorkermanRequest {
        };

        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrResponse = $this->createMock(ResponseInterface::class);

        $psrRequestFactory = new TestPsrRequestFactory($psrRequest);
        $responseEmitter = new TestWorkermanResponseEmitter();

        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($psrRequest)
            ->willReturn($psrResponse)
        ;

        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $this->requestHandler
        );

        $onMessage($connection, $workermanRequest);

        $this->assertTrue($responseEmitter->wasCalled());
    }

    /**
     * 测试内存耗尽的情况
     */
    public function testOutOfMemoryHandling(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $workermanRequest = new class('') extends WorkermanRequest {
        };

        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrRequestFactory = new TestPsrRequestFactory($psrRequest);
        $responseEmitter = new TestWorkermanResponseEmitter();

        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($psrRequest)
            ->willThrowException(new \Error('Allowed memory size exhausted'))
        ;

        $mockOutput = $this->createMock(OutputInterface::class);
        $mockOutput->expects($this->once())
            ->method('writeln')
            ->with(self::stringContains('Allowed memory size exhausted'))
        ;

        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $this->requestHandler,
            $mockOutput
        );

        $onMessage($connection, $workermanRequest);
    }

    /**
     * 测试响应发送失败的情况
     */
    public function testResponseEmissionFailure(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $workermanRequest = new class('') extends WorkermanRequest {
        };

        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrResponse = $this->createMock(ResponseInterface::class);

        $psrRequestFactory = new TestPsrRequestFactory($psrRequest);

        $responseEmitter = new class extends TestWorkermanResponseEmitter {
            public function emit(Request $request, ResponseInterface $response, TcpConnection $connection): void
            {
                throw new RequestProcessingException('Response emission failed');
            }
        };

        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($psrRequest)
            ->willReturn($psrResponse)
        ;

        $mockOutput = $this->createMock(OutputInterface::class);
        $mockOutput->expects($this->once())
            ->method('writeln')
            ->with(self::stringContains('Response emission failed'))
        ;

        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $this->requestHandler,
            $mockOutput
        );

        $onMessage($connection, $workermanRequest);
    }
}
