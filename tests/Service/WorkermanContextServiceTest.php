<?php

namespace Tourze\WorkermanServerBundle\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;
use Tourze\WorkermanServerBundle\Service\WorkermanContextService;

class WorkermanContextServiceTest extends TestCase
{
    /**
     * @var ContextServiceInterface&MockObject
     */
    private $innerService;

    /**
     * @var WorkermanContextService
     */
    private $contextService;

    protected function setUp(): void
    {
        $this->innerService = $this->createMock(ContextServiceInterface::class);
        $this->contextService = new WorkermanContextService($this->innerService);
    }

    public function testGetIdWhenNotRunning(): void
    {
        // 由于 Worker::isRunning() 是静态方法，直接测试会返回 false，所以这里测试非运行时的情况
        $this->innerService->expects($this->once())
            ->method('getId')
            ->willReturn('test-id');

        $this->assertEquals('test-id', $this->contextService->getId());
    }

    public function testDeferWhenNotRunning(): void
    {
        $callback = function () {
            return true;
        };

        $this->innerService->expects($this->once())
            ->method('defer')
            ->with($callback);

        $this->contextService->defer($callback);
    }

    public function testSupportCoroutineWhenNotRunning(): void
    {
        $this->innerService->expects($this->once())
            ->method('supportCoroutine')
            ->willReturn(false);

        $this->assertFalse($this->contextService->supportCoroutine());
    }

    public function testReset(): void
    {
        $this->innerService->expects($this->once())
            ->method('reset');

        $this->contextService->reset();
    }
}
