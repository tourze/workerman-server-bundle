<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Mickeywaugh\WorkermanServerBundle\HTTP\OnMessage;

/**
 * OnMessage 测试类
 *
 * 由于 OnMessage 类依赖 Workerman 运行时环境（Worker::stopAll()、TcpConnection 等），
 * 无法进行有意义的运行时测试。该测试仅验证类的基本结构和构造函数签名。
 *
 * @internal
 */
#[CoversClass(OnMessage::class)]
#[RunTestsInSeparateProcesses]
final class OnMessageTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // OnMessage 需要 Workerman 运行时，无需额外初始化
    }

    /**
     * 测试 OnMessage 类存在且可反射
     */
    public function test__invoke(): void
    {
        $reflection = new \ReflectionClass(OnMessage::class);

        $this->assertTrue($reflection->hasMethod('__invoke'));
        $this->assertTrue($reflection->getMethod('__invoke')->isPublic());

        // 验证构造函数参数
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(5, $params);

        // 验证参数名称
        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        $this->assertContains('kernel', $paramNames);
        $this->assertContains('psrRequestFactory', $paramNames);
        $this->assertContains('workermanResponseEmitter', $paramNames);
        $this->assertContains('requestHandler', $paramNames);
        $this->assertContains('logger', $paramNames);
    }

    /**
     * 测试 __invoke 方法签名
     */
    public function testInvokeMethodSignature(): void
    {
        $reflection = new \ReflectionClass(OnMessage::class);
        $method = $reflection->getMethod('__invoke');

        $params = $method->getParameters();
        $this->assertCount(2, $params);

        // 第一个参数是 WorkermanTcpConnection
        $this->assertEquals('workermanTcpConnection', $params[0]->getName());

        // 第二个参数是 WorkermanRequest
        $this->assertEquals('workermanRequest', $params[1]->getName());

        // 返回类型是 void
        $returnType = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertEquals('void', $returnType->getName());
    }
}
