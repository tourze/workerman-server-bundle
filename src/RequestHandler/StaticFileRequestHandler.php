<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\RequestHandler;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\WorkermanServerBundle\HTTP\WorkermanFileResponse;

/**
 * 专门用于处理静态资源文件的请求处理器
 */
class StaticFileRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?RequestHandlerInterface $nextHandler = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // 静态文件的支持
        $checkFile = "{$this->kernel->getProjectDir()}/public{$path}";
        $checkFile = str_replace('..', '/', $checkFile);

        // 兼容访问目录
        if (is_dir($checkFile)) {
            $checkFile = rtrim($checkFile, '/');
            if (is_file("{$checkFile}/index.htm")) {
                $checkFile = "{$checkFile}/index.htm";
            }
            if (is_file("{$checkFile}/index.html")) {
                $checkFile = "{$checkFile}/index.html";
            }
        }

        // 只处理存在的静态文件，不处理PHP文件
        if (is_file($checkFile) && !str_contains($checkFile, '.php')) {
            // 检查if-modified-since头判断文件是否修改过
            if (!empty($if_modified_since = $request->getHeaderLine('if-modified-since'))) {
                $modified_time = date('D, d M Y H:i:s', filemtime($checkFile)) . ' ' . \date_default_timezone_get();
                // 文件未修改则返回304
                if ($modified_time === $if_modified_since) {
                    return new Response(304);
                }
            }

            // 文件修改过或者没有if-modified-since头则发送文件
            $response = new WorkermanFileResponse();
            $response->setFile($checkFile);

            return $response;
        }

        // 如果不是静态文件或者静态文件不存在，则交给下一个处理器
        if ($this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }

        // 如果没有下一个处理器且不是静态文件，则返回404
        return new Response(404, body: 'File not found');
    }
}
