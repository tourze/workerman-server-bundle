<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use Psr\Http\Message\ServerRequestInterface;
use Tourze\WorkermanServerBundle\Exception\RequestProcessingException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * 测试用的请求工厂
 */
class TestPsrRequestFactory
{
    public function __construct(
        private readonly ?ServerRequestInterface $psrRequest = null,
        private readonly bool $shouldThrowException = false,
    ) {
    }

    public function create(TcpConnection $connection, WorkermanRequest $request): ?ServerRequestInterface
    {
        if ($this->shouldThrowException) {
            throw new RequestProcessingException('Test exception');
        }

        return $this->psrRequest;
    }
}
