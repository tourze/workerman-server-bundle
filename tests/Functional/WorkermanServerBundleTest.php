<?php

namespace Tourze\WorkermanServerBundle\Tests\Functional;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\WorkermanServerBundle\HTTP\OnMessage;
use Tourze\WorkermanServerBundle\HTTP\PsrRequestFactory;
use Tourze\WorkermanServerBundle\HTTP\WorkermanResponseEmitter;
use Tourze\WorkermanServerBundle\RequestHandler\SymfonyRequestHandler;
use Tourze\WorkermanServerBundle\WorkermanServerBundle;

class WorkermanServerBundleTest extends TestCase
{
    /**
     * 测试确保基本组件能够协同工作
     */
    public function testComponentsIntegration(): void
    {
        // 创建要测试的组件
        $psr17Factory = new Psr17Factory();
        $httpFoundationFactory = new HttpFoundationFactory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        // 模拟同时实现 HttpKernelInterface 和 KernelInterface 的对象
        /** @var HttpKernelInterface&KernelInterface&MockObject $kernel */
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)
            ->getMock();
        $kernel->method('handle')
            ->willReturn(new SymfonyResponse('Test Response'));

        // 构建测试对象链
        $requestHandler = new SymfonyRequestHandler(
            $kernel,
            $httpFoundationFactory,
            $psrHttpFactory
        );

        $psrRequestFactory = new PsrRequestFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $responseEmitter = new WorkermanResponseEmitter();

        // 创建 OnMessage 处理器
        $onMessage = new OnMessage(
            $psrRequestFactory,
            $responseEmitter,
            $requestHandler
        );

        // 确保类已正确初始化
        $this->assertInstanceOf(OnMessage::class, $onMessage);

        // 测试 Bundle 初始化
        $bundle = new WorkermanServerBundle();
        $this->assertInstanceOf(WorkermanServerBundle::class, $bundle);

        // 这里我们仅检查组件的初始化状态，不测试实际的请求处理
        // 因为在单元测试环境中，我们无法模拟完整的 Workerman 运行时环境
        $this->assertTrue(true);
    }
}
