<?php

namespace Tourze\WorkermanServerBundle\Tests\HTTP;

use PHPUnit\Framework\TestCase;
use Tourze\WorkermanServerBundle\HTTP\ProperHeaderCasingResponseFactory;

class ProperHeaderCasingResponseFactoryTest extends TestCase
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
}
