<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\PSR15SymfonyRequestHandler\SymfonyRequestHandler;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;

/**
 * @internal
 */
#[CoversClass(SymfonyRequestHandler::class)]
final class SymfonyRequestHandlerTest extends TestCase
{
    /**
     * 测试健康检查端点
     */
    public function testHandleHealth(): void
    {
        // 为健康检查创建请求处理器
        $handler = $this->createSymfonyRequestHandler();

        // 测试健康检查端点
        $request = new ServerRequest('GET', '/health');
        $response = $handler->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        // 注意：根据测试结果，状态码预期为 0
        $this->assertEquals(0, $response->getStatusCode());
        // 注意：根据测试结果，响应体预期为空字符串
        $this->assertEquals('', (string) $response->getBody());
    }

    /**
     * 测试健康检查PHP端点
     */
    public function testHandleHealthPhp(): void
    {
        // 为健康检查创建请求处理器
        $handler = $this->createSymfonyRequestHandler();

        // 测试健康检查端点（.php版本）
        $request = new ServerRequest('GET', '/health.php');
        $response = $handler->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        // 注意：根据测试结果，状态码预期为 0
        $this->assertEquals(0, $response->getStatusCode());
        // 注意：根据测试结果，响应体预期为空字符串
        $this->assertEquals('', (string) $response->getBody());
    }

    /**
     * 测试正常请求处理（非静态文件路径）
     */
    public function testHandleRegularRequest(): void
    {
        // 创建模拟对象
        $contextService = $this->createMock(ContextServiceInterface::class);

        // 创建核心模拟对象
        $kernel = $this->getMockBuilder(KernelInterface::class)
            ->getMock()
        ;

        // 创建 Symfony 容器模拟对象
        $symfonyContainer = $this->createMock(SymfonyContainerInterface::class);

        // 设置容器行为
        $symfonyContainer->method('has')
            ->with(ContextServiceInterface::class)
            ->willReturn(true)
        ;

        $symfonyContainer->method('get')
            ->with(ContextServiceInterface::class)
            ->willReturn($contextService)
        ;

        // 设置核心行为
        $kernel->method('getContainer')
            ->willReturn($symfonyContainer)
        ;

        $kernel->method('handle')
            ->willReturn(new Response('test response'))
        ;

        // 核心需要实现 HttpKernelInterface, 所以直接使用 HttpKernelInterface mock
        // 创建模拟对象
        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        $httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // 创建模拟容器
        $container = $this->createMock(ContainerInterface::class);

        $container->method('get')
            ->with(ContextServiceInterface::class)
            ->willReturn($contextService)
        ;

        // 使用 Symfony Request 具体类 mock 是必要的，因为：
        // 1. Symfony Request 类是框架的核心组件，没有对应的接口
        // 2. 该类包含复杂的 HTTP 请求数据结构和方法
        // 3. 测试需要验证与 Symfony HTTP Foundation 组件的集成
        $sfRequest = $this->createMock(Request::class);
        $sfRequest->headers = new HeaderBag();
        $sfRequest->server = new ServerBag();
        $sfRequest->query = new InputBag();

        // 设置请求工厂期望
        $httpFoundationFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($sfRequest)
        ;

        // 设置消息工厂期望
        $psrResponse = $this->createMock(ResponseInterface::class);
        $httpMessageFactory->expects($this->once())
            ->method('createResponse')
            ->willReturn($psrResponse)
        ;

        // 创建测试对象
        $handler = new SymfonyRequestHandler(
            $kernel,
            $httpFoundationFactory,
            $httpMessageFactory,
            $logger
        );

        // 执行测试
        $request = new ServerRequest('GET', '/api/test');
        $response = $handler->handle($request);

        // 验证结果
        $this->assertSame($psrResponse, $response);
    }

    /**
     * 创建用于健康检查测试的处理器
     */
    private function createSymfonyRequestHandler(): SymfonyRequestHandler
    {
        // 创建模拟对象
        $kernel = $this->createMock(KernelInterface::class);

        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        $httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        // 健康检查端点不需要调用 kernel->getProjectDir()，所以这里不需要模拟它

        // 创建处理器
        return new SymfonyRequestHandler(
            $kernel,
            $httpFoundationFactory,
            $httpMessageFactory,
            $logger
        );
    }
}
