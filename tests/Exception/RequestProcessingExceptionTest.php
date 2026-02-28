<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Mickeywaugh\WorkermanServerBundle\Exception\RequestProcessingException;

/**
 * @internal
 */
#[CoversClass(RequestProcessingException::class)]
final class RequestProcessingExceptionTest extends AbstractExceptionTestCase
{
}
