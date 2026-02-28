<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\HTTP;

use Psr\Http\Message\ResponseInterface;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response;

final class WorkermanResponseEmitter
{
    public function emit(WorkermanRequest $request, ResponseInterface $response, WorkermanTcpConnection $connection): void
    {
        $wkResponse = (new Response())
            ->withStatus($response->getStatusCode(), $response->getReasonPhrase())
            ->withHeaders($response->getHeaders())
            ->withBody((string) $response->getBody())
        ;
        $this->sendResponse($request, $wkResponse, $connection);
    }

    private function sendResponse(WorkermanRequest $request, mixed $buffer, WorkermanTcpConnection $connection): void
    {
        $connectionHeader = $request->header('connection');
        if ('keep-alive' === strtolower(is_string($connectionHeader) ? $connectionHeader : '')) {
            $connection->send($buffer);

            return;
        }
        $connection->close($buffer);
    }
}
