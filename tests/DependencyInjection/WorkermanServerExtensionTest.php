<?php

namespace Tourze\WorkermanServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\WorkermanServerBundle\Command\WorkermanHttpCommand;
use Tourze\WorkermanServerBundle\DependencyInjection\WorkermanServerExtension;

class WorkermanServerExtensionTest extends TestCase
{
    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $extension = new WorkermanServerExtension();

        $extension->load([], $container);

        // 检查是否注册了预期的服务
        $this->assertTrue($container->hasDefinition('workerman-server.command.http'));
        $this->assertTrue($container->hasDefinition('workerman-server.mime-detector'));

        // 检查服务定义的类是否正确
        $this->assertEquals(
            WorkermanHttpCommand::class,
            $container->getDefinition('workerman-server.command.http')->getClass()
        );

        // 测试标签
        $this->assertTrue(
            $container->getDefinition('workerman-server.command.http')->hasTag('console.command'),
            'HTTP命令应该有console.command标签'
        );
    }
}
