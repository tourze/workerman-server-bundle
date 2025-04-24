<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\HTTP;

use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response;

final class WorkermanResponseEmitter
{
    public function emit(WorkermanRequest $request, ResponseInterface $response, WorkermanTcpConnection $connection): void
    {
        // 特殊的文件渲染
        if ($response instanceof WorkermanFileResponse) {
            $wkResponse = (new Response())->withFile($response->getFile());
            $this->sendResponse($request, $wkResponse, $connection);

            return;
        }

        $wkResponse = (new Response())
            ->withStatus($response->getStatusCode(), $response->getReasonPhrase())
            ->withHeaders($response->getHeaders())
            ->withBody((string) $response->getBody());
        $this->sendResponse($request, $wkResponse, $connection);
    }

    private function sendResponse(WorkermanRequest $request, mixed $buffer, WorkermanTcpConnection $connection): void
    {
        if ('keep-alive' === strtolower($request->header('connection'))) {
            $connection->send($buffer);

            return;
        }
        $connection->close($buffer);
    }
}
