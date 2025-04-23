<?php

namespace Tourze\WorkermanServerBundle\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\WorkermanServerBundle\WorkermanServerBundle;

class WorkermanServerBundleTest extends TestCase
{
    public function testBoot(): void
    {
        // 创建一个模拟的反射类来测试 addProdIgnoreFiles 方法是否被调用
        $bundle = new WorkermanServerBundle();
        
        // 由于 Backtrace::addProdIgnoreFiles 是静态方法，我们可以使用 PHP 原生的方法来测试
        // 我们可以通过检查是否没有抛出异常来验证 boot 方法的行为
        $bundle->boot();
        
        // 断言执行到此处，表示方法执行没有错误
        $this->assertTrue(true);
    }
}
