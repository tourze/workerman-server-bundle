<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\HTTP;

use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Worker;

final class OnMessage
{
    public function __construct(
        private readonly PsrRequestFactory $psrRequestFactory,
        private readonly WorkermanResponseEmitter $workermanResponseEmitter,
        private readonly RequestHandlerInterface $requestHandler,
        private readonly ?OutputInterface $output = null,
    ) {
    }

    public function __invoke(WorkermanTcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): void
    {
        try {
            $this->workermanResponseEmitter->emit(
                $workermanRequest,
                $this->requestHandler->handle($this->psrRequestFactory->create($workermanTcpConnection, $workermanRequest)),
                $workermanTcpConnection
            );
        } catch (\Throwable $exception) {
            $this->output?->writeln(strval($exception));
            // 停止了，让他重启一个进程
            Worker::stopAll();
        } finally {
            //            echo "conn - {$workermanTcpConnection->id} - {$workermanRequest->path()} response\n";
            // echo "\n\nstart:\n";
            // echo $workermanTcpConnection->getSendBuffer();
            // echo "\nend\n";
        }
    }
}
