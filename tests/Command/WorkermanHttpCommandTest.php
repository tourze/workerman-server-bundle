<?php

namespace Tourze\WorkermanServerBundle\Tests\Command;

use League\MimeTypeDetection\MimeTypeDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\WorkermanServerBundle\Command\WorkermanHttpCommand;

class WorkermanHttpCommandTest extends TestCase
{
    /**
     * @var KernelInterface&MockObject
     */
    private $kernel;

    /**
     * @var MimeTypeDetector&MockObject
     */
    private $mimeTypeDetector;

    /**
     * @var WorkermanHttpCommand
     */
    private $command;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->mimeTypeDetector = $this->createMock(MimeTypeDetector::class);

        $this->kernel->method('getProjectDir')
            ->willReturn('/tmp/project');

        $this->kernel->method('getBuildDir')
            ->willReturn('/tmp/project/var');

        $this->kernel->method('isDebug')
            ->willReturn(true);

        $this->command = new WorkermanHttpCommand($this->kernel, $this->mimeTypeDetector);
    }

    public function testCommandConfiguration(): void
    {
        // 测试命令配置
        $reflection = new \ReflectionClass(WorkermanHttpCommand::class);
        $attributes = $reflection->getAttributes(AsCommand::class);

        $this->assertCount(1, $attributes, '应该有一个AsCommand属性');
        $attribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(AsCommand::class, $attribute);

        $this->assertEquals('workerman:http', $attribute->name);
        $this->assertEquals('启动Workerman-HTTP服务', $attribute->description);

        // 检查命令选项/参数
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('start'));
        $this->assertTrue($definition->hasArgument('stop'));
        $this->assertTrue($definition->hasArgument('restart'));
        $this->assertTrue($definition->hasArgument('status'));
        $this->assertTrue($definition->hasArgument('reload'));
        $this->assertTrue($definition->hasArgument('connections'));
    }

    /**
     * 注意：由于 WorkermanHttpCommand 会尝试启动实际的 Worker 进程，
     * 我们无法在单元测试中进行真正的执行测试。这里仅测试命令的配置。
     * 完整的功能测试应该在集成测试环境中进行。
     */
    public function testCommandExecution(): void
    {
        // 使用反射来测试一些保护方法或属性，避免实际执行 Worker::runAll()
        $reflection = new \ReflectionClass(WorkermanHttpCommand::class);

        // 断言命令类有预期的方法
        $this->assertTrue($reflection->hasMethod('runHttpServer'));
        $this->assertTrue($reflection->hasMethod('resetServiceTimer'));
        $this->assertTrue($reflection->hasMethod('createMessenger'));
        $this->assertTrue($reflection->hasMethod('createFileMonitor'));
        $this->assertTrue($reflection->hasMethod('execute'));

        // 验证命令实例化成功
        $this->assertInstanceOf(WorkermanHttpCommand::class, $this->command);
    }
}
