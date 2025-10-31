<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

/**
 * 完全测试用的 OnMessage 实现，绕过原始类的类型检查限制
 */
class TestOnMessage
{
    public function __construct(
        private readonly mixed $psrRequestFactory,
        private readonly mixed $workermanResponseEmitter,
        private readonly RequestHandlerInterface $requestHandler,
        private readonly ?OutputInterface $output = null,
    ) {
    }

    public function __invoke(TcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): void
    {
        try {
            if (is_object($this->workermanResponseEmitter)
                && is_object($this->psrRequestFactory)
                && method_exists($this->workermanResponseEmitter, 'emit')
                && method_exists($this->psrRequestFactory, 'create')) {
                $psrRequest = $this->psrRequestFactory->create($workermanTcpConnection, $workermanRequest);
                if ($psrRequest instanceof ServerRequestInterface) {
                    $response = $this->requestHandler->handle($psrRequest);
                } else {
                    throw new \RuntimeException('Failed to create PSR request');
                }
                $this->workermanResponseEmitter->emit(
                    $workermanRequest,
                    $response,
                    $workermanTcpConnection
                );
            }
        } catch (\Throwable $exception) {
            $this->output?->writeln(strval($exception));
            // Worker::stopAll() 在测试中我们不实际调用，避免停止测试进程
        }
    }
}
