<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\Tests\RequestHandler;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Tourze\PSR15ChainRequestHandler\ChainRequestHandler;

class ChainRequestHandlerTest extends TestCase
{
    public function testEmptyHandlersReturns404(): void
    {
        $chainHandler = new ChainRequestHandler();
        $request = new ServerRequest('GET', '/test');

        $response = $chainHandler->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('No handlers available', (string)$response->getBody());
    }

    public function testFirstHandlerNon404Response(): void
    {
        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler1 */
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn(new Response(200, body: 'Success'));

        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler2 */
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->expects($this->never())->method('handle');

        $chainHandler = new ChainRequestHandler([$handler1, $handler2]);
        $request = new ServerRequest('GET', '/test');

        $response = $chainHandler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', (string)$response->getBody());
    }

    public function testSkips404AndUseSecondHandler(): void
    {
        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler1 */
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn(new Response(404));

        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler2 */
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->method('handle')->willReturn(new Response(200, body: 'Found in second handler'));

        $chainHandler = new ChainRequestHandler([$handler1, $handler2]);
        $request = new ServerRequest('GET', '/test');

        $response = $chainHandler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Found in second handler', (string)$response->getBody());
    }

    public function testAllHandlersReturn404(): void
    {
        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler1 */
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn(new Response(404));

        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler2 */
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->method('handle')->willReturn(new Response(404));

        $chainHandler = new ChainRequestHandler([$handler1, $handler2]);
        $request = new ServerRequest('GET', '/test');

        $response = $chainHandler->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', (string)$response->getBody());
    }

    public function testAddHandler(): void
    {
        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler1 */
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn(new Response(404));

        /** @var RequestHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $handler2 */
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->method('handle')->willReturn(new Response(200, body: 'Added handler'));

        $chainHandler = new ChainRequestHandler([$handler1]);
        $chainHandler->addHandler($handler2);

        $request = new ServerRequest('GET', '/test');
        $response = $chainHandler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Added handler', (string)$response->getBody());
    }
}
