<?php

namespace Tourze\WorkermanServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\WorkermanServerBundle\DependencyInjection\WorkermanServerExtension;

/**
 * @internal
 */
#[CoversClass(WorkermanServerExtension::class)]
final class WorkermanServerExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
