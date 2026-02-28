<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\Command;

use League\MimeTypeDetection\MimeTypeDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Mickeywaugh\WorkermanServerBundle\Command\WorkermanHttpCommand;

/**
 * @internal
 *
 * 测试 WorkermanHttpCommand 的基本配置和方法，不启动实际的 Workerman 进程
 */
#[CoversClass(WorkermanHttpCommand::class)]
#[RunTestsInSeparateProcesses]
final class WorkermanHttpCommandTest extends AbstractCommandTestCase
{
    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(WorkermanHttpCommand::class);
        $this->assertInstanceOf(WorkermanHttpCommand::class, $command);

        return new CommandTester($command);
    }

    protected function onSetUp(): void
    {
        // Mock services that are required for command instantiation
        $mockKernel = $this->createMock(KernelInterface::class);
        $mockKernel->method('getProjectDir')->willReturn('/tmp');
        $mockKernel->method('getBuildDir')->willReturn('/tmp/var/cache');
        $mockKernel->method('isDebug')->willReturn(false);

        $mockMimeTypeDetector = $this->createMock(MimeTypeDetector::class);

        self::getContainer()->set(KernelInterface::class, $mockKernel);
        self::getContainer()->set('workerman-server.mime-detector', $mockMimeTypeDetector);
    }

    public function testCommandConfiguration(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        // 测试命令配置
        $reflection = new \ReflectionClass(WorkermanHttpCommand::class);
        $attributes = $reflection->getAttributes(AsCommand::class);

        $this->assertCount(1, $attributes, '应该有一个AsCommand属性');
        $attribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(AsCommand::class, $attribute);

        $this->assertEquals('workerman:http', $attribute->name);
        $this->assertEquals('启动Workerman-HTTP服务', $attribute->description);

        // 检查命令参数
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('start'));
        $this->assertTrue($definition->hasArgument('stop'));
        $this->assertTrue($definition->hasArgument('restart'));
        $this->assertTrue($definition->hasArgument('status'));
        $this->assertTrue($definition->hasArgument('reload'));
        $this->assertTrue($definition->hasArgument('connections'));
    }

    public function testArgumentStart(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('start'));

        $argument = $definition->getArgument('start');
        $this->assertFalse($argument->isRequired());
    }

    public function testArgumentStop(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('stop'));

        $argument = $definition->getArgument('stop');
        $this->assertFalse($argument->isRequired());
    }

    public function testArgumentRestart(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('restart'));

        $argument = $definition->getArgument('restart');
        $this->assertFalse($argument->isRequired());
    }

    public function testArgumentStatus(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('status'));

        $argument = $definition->getArgument('status');
        $this->assertFalse($argument->isRequired());
    }

    public function testArgumentReload(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('reload'));

        $argument = $definition->getArgument('reload');
        $this->assertFalse($argument->isRequired());
    }

    public function testArgumentConnections(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('connections'));

        $argument = $definition->getArgument('connections');
        $this->assertFalse($argument->isRequired());
    }

    public function testResetServiceTimer(): void
    {
        $command = self::getService(WorkermanHttpCommand::class);
        $output = new BufferedOutput();

        // 使用反射访问 resetServiceTimer 方法
        $reflection = new \ReflectionClass(WorkermanHttpCommand::class);
        $method = $reflection->getMethod('resetServiceTimer');
        $method->setAccessible(true);

        // 调用方法，确保不抛出异常
        $this->assertNull($method->invoke($command, $output));
    }
}
