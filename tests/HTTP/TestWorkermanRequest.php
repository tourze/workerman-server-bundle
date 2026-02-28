<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * 测试用 WorkermanRequest 子类，用于控制 connection header.
 *
 * @internal
 */
final class TestWorkermanRequest extends WorkermanRequest
{
    private string $connectionHeader = 'keep-alive';

    public function setConnectionHeader(string $header): void
    {
        $this->connectionHeader = $header;
    }

    public function header(?string $name = null, mixed $default = null): mixed
    {
        if ('connection' === $name) {
            return $this->connectionHeader;
        }

        return $default ?? '';
    }
}
