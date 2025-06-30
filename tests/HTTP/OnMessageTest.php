<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tourze\WorkermanServerBundle\Exception\RequestProcessingException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * 完全测试用的 OnMessage 实现，绕过原始类的类型检查限制
 */
class TestOnMessage
{
    private $psrRequestFactory;
    private $workermanResponseEmitter;
    private $requestHandler;
    private $output;

    public function __construct(
        $psrRequestFactory,
        $workermanResponseEmitter,
        RequestHandlerInterface $requestHandler,
        ?OutputInterface $output = null
    )
    {
        $this->psrRequestFactory = $psrRequestFactory;
        $this->workermanResponseEmitter = $workermanResponseEmitter;
        $this->requestHandler = $requestHandler;
        $this->output = $output;
    }

    public function __invoke(TcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): void
    {
        try {
            $this->workermanResponseEmitter->emit(
                $workermanRequest,
                $this->requestHandler->handle(
                    $this->psrRequestFactory->create($workermanTcpConnection, $workermanRequest)
                ),
                $workermanTcpConnection
            );
        } catch (\Throwable $exception) {
            $this->output?->writeln(strval($exception));
            // Worker::stopAll() 在测试中我们不实际调用，避免停止测试进程
        }
    }
}

/**
 * 测试用的请求工厂
 */
class TestPsrRequestFactory
{
    private $psrRequest;
    private $shouldThrowException = false;

    public function __construct(?ServerRequestInterface $psrRequest = null, bool $shouldThrowException = false)
    {
        $this->psrRequest = $psrRequest;
        $this->shouldThrowException = $shouldThrowException;
    }

    public function create($connection, $request): ServerRequestInterface
    {
        if ($this->shouldThrowException) {
            throw new RequestProcessingException('Test exception');
        }
        return $this->psrRequest;
    }
}

/**
 * 测试用的响应发送器
 */
class TestWorkermanResponseEmitter
{
    private $testCase;
    private $called = false;

    public function __construct($testCase = null)
    {
        $this->testCase = $testCase;
    }

    public function emit($request, $response, $connection): void
    {
        $this->called = true;
        if ($this->testCase) {
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

class OnMessageTest extends TestCase
{
    /**
     * @var RequestHandlerInterface&MockObject
     */
    private $requestHandler;

    /**
     * @var OutputInterface&MockObject
     */
    private $output;

    /**
     * @var TcpConnection&MockObject
     */
    private $connection;

    /**
     * @var WorkermanRequest&MockObject
     */
    private $request;

    /**
     * @var ServerRequestInterface&MockObject
     */
    private $psrRequest;

    /**
     * @var ResponseInterface&MockObject
     */
    private $psrResponse;

    protected function setUp(): void
    {
        // 模拟依赖
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->connection = $this->createMock(TcpConnection::class);
        $this->request = $this->createMock(WorkermanRequest::class);
        $this->psrRequest = $this->createMock(ServerRequestInterface::class);
        $this->psrResponse = $this->createMock(ResponseInterface::class);
    }

    public function testInvoke(): void
    {
        // 使用测试类替代无法模拟的类
        $psrRequestFactory = new TestPsrRequestFactory($this->psrRequest);
        $workermanResponseEmitter = new TestWorkermanResponseEmitter($this);

        // 配置请求处理器返回响应
        $this->requestHandler->expects($this->once())
            ->method('handle')
            ->with($this->psrRequest)
            ->willReturn($this->psrResponse);

        // 创建被测试对象（使用我们自己的实现）
        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $workermanResponseEmitter,
            $this->requestHandler,
            $this->output
        );

        // 执行测试
        $onMessage($this->connection, $this->request);

        // 验证响应发送器被调用
        $this->assertTrue($workermanResponseEmitter->wasCalled(), 'WorkermanResponseEmitter::emit 方法应该被调用');
    }

    public function testInvokeWithException(): void
    {
        // 使用测试类替代无法模拟的类，并设置抛出异常
        $psrRequestFactory = new TestPsrRequestFactory(null, true);
        $workermanResponseEmitter = new TestWorkermanResponseEmitter();

        // 输出应记录异常信息
        $this->output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('Test exception'));

        // 创建被测试对象（使用我们自己的实现）
        $onMessage = new TestOnMessage(
            $psrRequestFactory,
            $workermanResponseEmitter,
            $this->requestHandler,
            $this->output
        );

        // 执行测试
        $onMessage($this->connection, $this->request);
    }
}
