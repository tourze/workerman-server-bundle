<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Mickeywaugh\WorkermanServerBundle\DependencyInjection\WorkermanServerExtension;

/**
 * @internal
 */
#[CoversClass(WorkermanServerExtension::class)]
final class WorkermanServerExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
