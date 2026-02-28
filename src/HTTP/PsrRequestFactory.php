<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\HTTP;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Workerman\Connection\TcpConnection as WorkermanTcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;

final class PsrRequestFactory
{
    public function __construct(
        private readonly ServerRequestFactoryInterface $serverRequestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly UploadedFileFactoryInterface $uploadedFileFactory,
    ) {
    }

    public function create(WorkermanTcpConnection $workermanTcpConnection, WorkermanRequest $workermanRequest): ServerRequestInterface
    {
        $request = $this->serverRequestFactory->createServerRequest(
            $workermanRequest->method(),
            $workermanRequest->uri(),
            $this->createServerParams($workermanTcpConnection),
        );

        /** @var array<string, string> $headers */
        $headers = $workermanRequest->header();

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        /** @var array<string, string> $cookies */
        $cookies = $workermanRequest->cookie();

        $request = $request->withCookieParams($cookies);
        $queryParams = $workermanRequest->get();
        $request = $request->withQueryParams(is_array($queryParams) ? $queryParams : []);
        $parsedBody = $workermanRequest->post();
        $request = $request->withParsedBody(is_array($parsedBody) || is_object($parsedBody) ? $parsedBody : null);
        $files = $workermanRequest->file();
        if (null !== $files) {
            $fileArray = is_array($files) ? $files : [];
            /** @var array<string, mixed> $fileArray */
            $request = $request->withUploadedFiles($this->uploadedFiles($fileArray));
        }

        $request->getBody()->write($workermanRequest->rawBody());

        return $request;
    }

    /**
     * @return array<string, string>
     */
    private function createServerParams(WorkermanTcpConnection $workermanTcpConnection): array
    {
        return [
            'REMOTE_ADDR' => $workermanTcpConnection->getRemoteIp(),
            'REMOTE_PORT' => (string) $workermanTcpConnection->getRemotePort(),
        ];
    }

    /**
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    private function uploadedFiles(array $files): array
    {
        $uploadedFiles = [];
        foreach ($files as $key => $file) {
            $uploadedFiles[$key] = $this->processFileValue($file);
        }

        return $uploadedFiles;
    }

    /**
     * @param mixed $file
     *
     * @return mixed
     */
    private function processFileValue(mixed $file): mixed
    {
        if (!is_array($file)) {
            return null;
        }

        if ($this->isSingleFile($file)) {
            return $this->createUploadedFile($file);
        }

        /** @var array<string, mixed> $file */
        return $this->uploadedFiles($file);
    }

    /**
     * @param array<mixed, mixed> $file
     */
    private function isSingleFile(array $file): bool
    {
        return isset($file['tmp_name']) && is_string($file['tmp_name']);
    }

    /**
     * @param array<mixed, mixed> $file
     */
    private function createUploadedFile(array $file): UploadedFileInterface
    {
        $stream = $this->createStreamFromFile($file);
        $fileAttributes = $this->extractFileAttributes($file);

        return $this->uploadedFileFactory->createUploadedFile(
            $stream,
            $fileAttributes['size'],
            $fileAttributes['error'],
            $fileAttributes['name'],
            $fileAttributes['type']
        );
    }

    /**
     * @param array<mixed, mixed> $file
     */
    private function createStreamFromFile(array $file): StreamInterface
    {
        try {
            $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';

            return $this->streamFactory->createStreamFromFile($tmpName);
        } catch (\RuntimeException) {
            return $this->streamFactory->createStream();
        }
    }

    /**
     * @param array<mixed, mixed> $file
     *
     * @return array{size: int, error: int, name: string, type: string}
     */
    private function extractFileAttributes(array $file): array
    {
        return [
            'size' => isset($file['size']) && is_int($file['size']) ? $file['size'] : 0,
            'error' => isset($file['error']) && is_int($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE,
            'name' => isset($file['name']) && is_string($file['name']) ? $file['name'] : '',
            'type' => isset($file['type']) && is_string($file['type']) ? $file['type'] : '',
        ];
    }
}
