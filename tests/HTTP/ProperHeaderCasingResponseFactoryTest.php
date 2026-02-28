<?php

namespace Mickeywaugh\WorkermanServerBundle\Tests\HTTP;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Mickeywaugh\WorkermanServerBundle\HTTP\ProperHeaderCasingResponseFactory;

/**
 * @internal
 */
#[CoversClass(ProperHeaderCasingResponseFactory::class)]
final class ProperHeaderCasingResponseFactoryTest extends TestCase
{
    /**
     * 测试缓存静态属性
     */
    public function testCacheStaticProperty(): void
    {
        // 使用反射来访问私有的静态属性
        $reflection = new \ReflectionClass(ProperHeaderCasingResponseFactory::class);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cache = $cacheProperty->getValue();

        // 验证缓存包含预期的头部转换规则
        $this->assertIsArray($cache);
        $this->assertArrayHasKey('server', $cache);
        $this->assertEquals('Server', $cache['server']);
        $this->assertArrayHasKey('connection', $cache);
        $this->assertEquals('Connection', $cache['connection']);
        $this->assertArrayHasKey('content-type', $cache);
        $this->assertEquals('Content-Type', $cache['content-type']);
        $this->assertArrayHasKey('content-disposition', $cache);
        $this->assertEquals('Content-Disposition', $cache['content-disposition']);
        $this->assertArrayHasKey('last-modified', $cache);
        $this->assertEquals('Last-Modified', $cache['last-modified']);
        $this->assertArrayHasKey('transfer-encoding', $cache);
        $this->assertEquals('Transfer-Encoding', $cache['transfer-encoding']);
    }

    /**
     * 测试 createResponse 方法
     */
    public function testCreateResponse(): void
    {
        $psr17Factory = new Psr17Factory();
        $factory = new ProperHeaderCasingResponseFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        // 创建一个带有小写头部的 Symfony Response
        $symfonyResponse = new Response('Test content', 200, [
            'content-type' => 'text/html',
            'server' => 'nginx',
            'connection' => 'keep-alive',
        ]);

        // 转换为 PSR Response
        $psrResponse = $factory->createResponse($symfonyResponse);

        // 验证头部大小写转换
        $this->assertTrue($psrResponse->hasHeader('Content-Type'));
        $this->assertEquals(['text/html'], $psrResponse->getHeader('Content-Type'));

        $this->assertTrue($psrResponse->hasHeader('Server'));
        $this->assertEquals(['nginx'], $psrResponse->getHeader('Server'));

        $this->assertTrue($psrResponse->hasHeader('Connection'));
        $this->assertEquals(['keep-alive'], $psrResponse->getHeader('Connection'));

        // 验证响应体和状态码
        $this->assertEquals('Test content', (string) $psrResponse->getBody());
        $this->assertEquals(200, $psrResponse->getStatusCode());
    }
}
