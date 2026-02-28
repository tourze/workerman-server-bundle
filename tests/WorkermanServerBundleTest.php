<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Mickeywaugh\WorkermanServerBundle\WorkermanServerBundle;

/**
 * @internal
 */
#[CoversClass(WorkermanServerBundle::class)]
#[RunTestsInSeparateProcesses]
final class WorkermanServerBundleTest extends AbstractBundleTestCase
{
}
