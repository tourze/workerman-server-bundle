<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tourze\WorkermanServerBundle\HTTP\OnMessage;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * @internal
 */
#[CoversClass(OnMessage::class)]
final class OnMessageTest extends TestCase
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

    private WorkermanRequest $request;

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
        parent::setUp();

        // 模拟依赖
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->output = $this->createMock(OutputInterface::class);

        // 使用 TcpConnection 具体类 mock 是必要的，因为：
        // 1. Workerman 的 TcpConnection 类没有对应的接口
        // 2. 该类是 Workerman 框架的核心组件，无法替换为抽象接口
        // 3. 测试需要验证与 Workerman 网络层的集成行为
        $this->connection = $this->createMock(TcpConnection::class);

        // 创建一个简化的测试类来模拟 WorkermanRequest
        // 这避免了 PHPUnit 12 对 method() 方法名的弃用警告
        $this->request = new class('') extends WorkermanRequest {
            // 这个匿名类继承 WorkermanRequest 但不需要实现任何特殊逻辑
            // 因为测试中不会调用具体的 method() 方法，只需要一个可用的实例
        };

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
            ->willReturn($this->psrResponse)
        ;

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
            ->with(self::stringContains('Test exception'))
        ;

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
