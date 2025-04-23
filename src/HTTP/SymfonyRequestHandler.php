<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\HTTP;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Tourze\BacktraceHelper\ExceptionPrinter;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;

/**
 * 统一的HTTP请求处理
 */
class SymfonyRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly HttpKernelInterface|KernelInterface $kernel,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ('/health' === $request->getUri()->getPath()) {
            return new Response(200, body: strval(time()));
        }
        if ('/health.php' === $request->getUri()->getPath()) {
            return new Response(200, body: strval(time()));
        }

        $_GET = $request->getQueryParams();
        $_REQUEST = [...$_GET];

        // Cookie重新格式化写入
        \parse_str(\str_replace('; ', '&', $request->getHeaderLine('cookie')), $_COOKIE);

        // $this->output->writeln("<comment>{$this->worker->name}-{$this->worker->id}</comment> <info>{$request->getMethod()}</info> {$request->getUri()->getPath()}");

        // 静态文件的支持
        $checkFile = "{$this->kernel->getProjectDir()}/public{$request->getUri()->getPath()}";
        $checkFile = str_replace('..', '/', $checkFile);
        // $output->writeln("正在处理：{$checkFile}");
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

        $sfRequest = $this->httpFoundationFactory->createRequest($request);

        // 如果是Nginx ssl代理转发过来的话，我们需要声明一下我们是HTTPS
        if ($request->hasHeader('Force-Https') && $request->getHeaderLine('Force-Https')) {
            $sfRequest->server->set('HTTPS', 'on');
        }
        // TODO 更多负载均衡规则

        // TODO 真实IP透传，要注意这个可能会有漏洞
        if ($request->hasHeader('X-Real-IP')) {
            $sfRequest->server->set('REMOTE_ADDR', $request->getHeaderLine('X-Real-IP'));
        }

        $appendHeaders = [];

        // 默认情况下，symfony没对header中的Authorization做处理，貌似是依赖了nginx、php-fpm他们的处理，我们需要做一次兜底处理咯
        if ($request->hasHeader('Authorization')) {
            $authorizationHeader = $request->getHeaderLine('Authorization');
            if ($authorizationHeader) {
                // copy from vendor/symfony/http-foundation/ServerBag.php
                if (0 === stripos($authorizationHeader, 'basic ')) {
                    // Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
                    $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
                    if (2 == \count($exploded)) {
                        [$appendHeaders['PHP_AUTH_USER'], $appendHeaders['PHP_AUTH_PW']] = $exploded;
                    }
                } elseif (empty($this->parameters['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader, 'digest '))) {
                    // In some circumstances PHP_AUTH_DIGEST needs to be set
                    $appendHeaders['PHP_AUTH_DIGEST'] = $authorizationHeader;
                } elseif (0 === stripos($authorizationHeader, 'bearer ')) {
                    /*
                     * XXX: Since there is no PHP_AUTH_BEARER in PHP predefined variables,
                     *      I'll just set $headers['AUTHORIZATION'] here.
                     *      https://php.net/reserved.variables.server
                     */
                    $appendHeaders['AUTHORIZATION'] = $authorizationHeader;
                }
            }
        }

        // 在一些业务中，我们实际是需要在请求发生前，就生成一个 request id，这种情况才能更加好地打印整个日志
        // $appendHeaders['Request-Id'] = Uuid::v4()->toRfc4122();

        foreach ($appendHeaders as $k => $v) {
            $sfRequest->headers->set($k, $v);
        }

        if ($sfRequest->query->get('debug-dump-header')) {
            return new Response(200, body: var_export($sfRequest->headers->all(), true));
        }
        if ($sfRequest->query->get('debug-dump-server')) {
            return new Response(200, body: var_export($_SERVER, true));
        }
        if ($sfRequest->query->get('debug-dump-env')) {
            return new Response(200, body: var_export($_ENV, true));
        }

        try {
            $sfResponse = $this->kernel->handle($sfRequest);
        } catch (\Throwable $exception) {
            $fe = ExceptionPrinter::exception($exception);
            $this->logger?->error('执行请求时发生未被捕捉的异常', [
                'exception' => $fe,
            ]);
            $sfResponse = new \Symfony\Component\HttpFoundation\Response($fe);
        }

        // 尽可能将事务丢到异步去进行，这样前端响应会快点
        if ($this->kernel instanceof TerminableInterface) {
            // 这里延迟了一点才执行，是希望这个业务代码尽可能晚点执行。参考 https://www.workerman.net/doc/workerman/timer/add.html
            $this->kernel->getContainer()->get(ContextServiceInterface::class)->defer(function () use ($sfRequest, $sfResponse) {
                try {
                    $this->kernel->terminate($sfRequest, $sfResponse);
                } catch (\Throwable $exception) {
                    $v = ExceptionPrinter::exception($exception);
                    echo sprintf("真正结束请求时发生错误：%s\n", $v);
                }

                // 是否开启 meminfo 分析
                $openMeminfo = 'true' === $sfRequest->query->get('__meminfo');
                // 注销请求和响应对象
                unset($sfRequest);
                if ($openMeminfo && function_exists('meminfo_dump')) {
                    meminfo_dump(fopen($this->kernel->getProjectDir() . '/var/php_mem_dump_' . time() . '.json', 'w'));
                }
            });
        }

        // TODO 类似 http_cache 这种服务，应该需要再处理的，详细看 \Symfony\Component\HttpKernel\HttpCache\HttpCache::__construct

        return $this->httpMessageFactory->createResponse($sfResponse);
    }
}
