<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\HTTP;

use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

final class ProperHeaderCasingResponseFactory extends PsrHttpFactory
{
    /** @var array<string, string> */
    private static array $cache = [
        'server' => 'Server',
        'connection' => 'Connection',
        'content-type' => 'Content-Type',
        'content-disposition' => 'Content-Disposition',
        'last-modified' => 'Last-Modified',
        'transfer-encoding' => 'Transfer-Encoding',
    ];

    public function createResponse(Response $symfonyResponse): ResponseInterface
    {
        $response = parent::createResponse($symfonyResponse);

        $headers = $response->getHeaders();

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($this->convertHeaderName($key), $value);
        }

        return $response;
    }

    private function convertHeaderName(string $key): string
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        return $key;
    }
}
