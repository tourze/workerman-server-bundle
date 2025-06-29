<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\HTTP;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Tourze\BacktraceHelper\ExceptionPrinter;
use Tourze\PSR15ChainRequestHandler\ChainRequestHandler;
use Tourze\PSR15SymfonyRequestHandler\SymfonyRequestHandler;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Worker;

class OnMessage
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly PsrRequestFactory $psrRequestFactory,
        private readonly WorkermanResponseEmitter $workermanResponseEmitter,
        private readonly RequestHandlerInterface $requestHandler,
        private readonly LoggerInterface $logger,
    ) {
        if ($this->requestHandler instanceof ChainRequestHandler) {
            foreach ($this->requestHandler->getHandlers() as $handler) {
                if ($handler instanceof SymfonyRequestHandler) {
                    $this->sfRequestHandler = $handler;
                    break;
                }
            }
        }
    }

    private ?SymfonyRequestHandler $sfRequestHandler = null;

    public function __invoke(WorkermanTcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): void
    {
        try {
            if ($this->sfRequestHandler !== null) {
                $this->sfRequestHandler->setRequest(null);
                $this->sfRequestHandler->setResponse(null);
            }

            $this->workermanResponseEmitter->emit(
                $workermanRequest,
                $this->requestHandler->handle($this->psrRequestFactory->create($workermanTcpConnection, $workermanRequest)),
                $workermanTcpConnection,
            );

            // 在这里处理 terminal 逻辑
            if ($this->kernel instanceof TerminableInterface && $this->sfRequestHandler !== null) {
                $request = $this->sfRequestHandler->getRequest();
                $response = $this->sfRequestHandler->getResponse();
                if ($request !== null && $response !== null) {
                    $this->kernel->terminate($request, $response);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('处理WorkermanHTTP请求时发生异常', [
                'exception' => ExceptionPrinter::exception($exception),
            ]);
            // 停止了，让他重启一个进程
            Worker::stopAll();
        }
    }
}
