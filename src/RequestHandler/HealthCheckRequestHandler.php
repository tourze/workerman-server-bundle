<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\RequestHandler;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 健康检查请求处理器
 */
class HealthCheckRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ?RequestHandlerInterface $nextHandler = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // 处理健康检查请求
        if ('/health' === $path || '/health.php' === $path) {
            return new Response(200, body: strval(time()));
        }

        // 如果不是健康检查请求，则交给下一个处理器
        if ($this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }

        // 如果没有下一个处理器，则返回404
        return new Response(404, body: 'Not Found');
    }
}
