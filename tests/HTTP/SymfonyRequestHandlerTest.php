<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;
use Tourze\WorkermanServerBundle\RequestHandler\SymfonyRequestHandler;

/**
 * 此类测试 HTTP 请求处理器
 */
class SymfonyRequestHandlerTest extends TestCase
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
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(strval(time()), (string)$response->getBody());
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
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(strval(time()), (string)$response->getBody());
    }

    /**
     * 测试正常请求处理（非静态文件路径）
     */
    public function testHandleRegularRequest(): void
    {
        // 创建模拟对象
        /** @var ContextServiceInterface|MockObject $contextService */
        $contextService = $this->createMock(ContextServiceInterface::class);

        // 创建核心模拟对象
        /** @var KernelInterface|HttpKernelInterface|MockObject $kernel */
        $kernel = $this->createMock(KernelInterface::class);

        // 创建 Symfony 容器模拟对象
        /** @var SymfonyContainerInterface|MockObject $symfonyContainer */
        $symfonyContainer = $this->createMock(SymfonyContainerInterface::class);

        // 设置容器行为
        $symfonyContainer->method('has')
            ->with(ContextServiceInterface::class)
            ->willReturn(true);

        $symfonyContainer->method('get')
            ->with(ContextServiceInterface::class)
            ->willReturn($contextService);

        // 设置核心行为
        $kernel->method('getContainer')
            ->willReturn($symfonyContainer);

        $kernel->method('handle')
            ->willReturn(new Response('test response'));

        // 核心需要实现 HttpKernelInterface, 所以手动添加方法
        if (!$kernel instanceof HttpKernelInterface) {
            $kernel = $this->getMockBuilder(get_class($kernel))
                ->addMethods(['handle'])
                ->getMock();

            $kernel->method('handle')
                ->willReturn(new Response('test response'));
        }

        // 创建模拟对象
        /** @var HttpFoundationFactoryInterface|MockObject $httpFoundationFactory */
        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        /** @var HttpMessageFactoryInterface|MockObject $httpMessageFactory */
        $httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        // 创建模拟容器
        /** @var ContainerInterface|MockObject $container */
        $container = $this->createMock(ContainerInterface::class);

        $container->method('get')
            ->with(ContextServiceInterface::class)
            ->willReturn($contextService);

        // 创建模拟 Symfony 请求
        /** @var Request|MockObject $sfRequest */
        $sfRequest = $this->createMock(Request::class);
        $sfRequest->headers = new HeaderBag();
        $sfRequest->server = new ServerBag();
        /** @var ParameterBag|MockObject $queryBag */
        $queryBag = $this->createMock(ParameterBag::class);
        $sfRequest->query = $queryBag;

        $queryBag->method('get')
            ->willReturn(null);

        // 设置请求工厂期望
        $httpFoundationFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($sfRequest);

        // 设置消息工厂期望
        /** @var ResponseInterface|MockObject $psrResponse */
        $psrResponse = $this->createMock(ResponseInterface::class);
        $httpMessageFactory->expects($this->once())
            ->method('createResponse')
            ->willReturn($psrResponse);

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
        /** @var HttpKernelInterface|KernelInterface|MockObject $kernel */
        $kernel = $this->createMock(HttpKernelInterface::class);

        /** @var HttpFoundationFactoryInterface|MockObject $httpFoundationFactory */
        $httpFoundationFactory = $this->createMock(HttpFoundationFactoryInterface::class);
        /** @var HttpMessageFactoryInterface|MockObject $httpMessageFactory */
        $httpMessageFactory = $this->createMock(HttpMessageFactoryInterface::class);
        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        // 健康检查端点不需要调用 kernel->getProjectDir()，所以这里不需要模拟它

        // 创建处理器
        $handler = new SymfonyRequestHandler(
            $kernel,
            $httpFoundationFactory,
            $httpMessageFactory,
            $logger
        );

        return $handler;
    }
}
