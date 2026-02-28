<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Mickeywaugh\WorkermanServerBundle\HTTP\WorkermanResponseEmitter;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * @internal
 */
#[CoversClass(WorkermanResponseEmitter::class)]
final class WorkermanResponseEmitterTest extends TestCase
{
    /**
     * @var ResponseInterface&MockObject
     */
    private $psrResponse;

    /**
     * @var StreamInterface&MockObject
     */
    private $responseBody;

    private TestWorkermanRequest $workermanRequest;

    /**
     * @var TcpConnection&MockObject
     */
    private $connection;

    private WorkermanResponseEmitter $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->psrResponse = $this->createMock(ResponseInterface::class);
        $this->responseBody = $this->createMock(StreamInterface::class);

        // 创建一个简化的测试对象来模拟 WorkermanRequest
        // 这避免了 PHPUnit 12 对 method() 方法名的弃用警告
        $this->workermanRequest = new TestWorkermanRequest('');

        // 使用 TcpConnection 具体类 mock 是必要的，因为：
        // 1. Workerman 的 TcpConnection 类没有对应的接口
        // 2. 该类是 Workerman 框架的核心组件，无法替换为抽象接口
        // 3. 响应发送器需要通过连接对象发送 HTTP 响应
        $this->connection = $this->createMock(TcpConnection::class);

        $this->emitter = new WorkermanResponseEmitter();
    }

    public function testEmitWithKeepAlive(): void
    {
        // 配置 PSR 响应
        $this->psrResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->psrResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('OK')
        ;

        $this->psrResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([
                'Content-Type' => ['application/json'],
                'X-Custom-Header' => ['Value'],
            ])
        ;

        $this->psrResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($this->responseBody)
        ;

        $this->responseBody->expects($this->once())
            ->method('__toString')
            ->willReturn('{"result":"success"}')
        ;

        // 配置 Workerman 请求
        $this->workermanRequest->setConnectionHeader('keep-alive');

        // 验证 connection 的发送调用 - 注意参数 2 是 false 而不是 true
        $this->connection->expects($this->once())
            ->method('send')
            ->with(
                self::callback(function ($response) {
                    // 确保参数是一个 WorkermanResponse 对象
                    self::assertInstanceOf(WorkermanResponse::class, $response);

                    // 这里不能直接测试对象内部属性，因为它们是私有的
                    // 但我们可以断言发送被调用
                    return true;
                })
            )
        ;

        // 验证关闭不被调用（因为是 keep-alive）
        $this->connection->expects($this->never())
            ->method('close')
        ;

        // 执行测试
        $this->emitter->emit($this->workermanRequest, $this->psrResponse, $this->connection);
    }

    public function testEmitWithoutKeepAlive(): void
    {
        // 配置 PSR 响应
        $this->psrResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $this->psrResponse->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('OK')
        ;

        $this->psrResponse->expects($this->once())
            ->method('getHeaders')
            ->willReturn([
                'Content-Type' => ['text/html'],
            ])
        ;

        $this->psrResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($this->responseBody)
        ;

        $this->responseBody->expects($this->once())
            ->method('__toString')
            ->willReturn('<html><body>Test</body></html>')
        ;

        // 配置 Workerman 请求
        $this->workermanRequest->setConnectionHeader('close');

        // 验证 connection 的关闭调用
        $this->connection->expects($this->once())
            ->method('close')
            ->with(self::isInstanceOf(WorkermanResponse::class))
        ;

        // 验证 send 不被调用
        $this->connection->expects($this->never())
            ->method('send')
        ;

        // 执行测试
        $this->emitter->emit($this->workermanRequest, $this->psrResponse, $this->connection);
    }
}
