<?php
/**
 * 验证Workerman v5 Fiber Event Loop上下文ID测试脚本
 *
 * 使用方法：
 * 1. 从项目根目录运行: php packages/workerman-server-bundle/examples/coroutine/verify-fiber-context.php
 * 2. 测试将自动运行，无需手动触发
 */

// 解析引用根项目的autoload.php，而不是当前包的vendor目录
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Tourze\Workerman\MasterKillber\MasterKiller;
use Tourze\Workerman\PsrLogger\WorkermanLogger;
use Workerman\Connection\TcpConnection;
use Workerman\Coroutine;
use Workerman\Coroutine\Context;
use Workerman\Events\Fiber as FiberEvent;
use Workerman\Timer;
use Workerman\Worker;

// 使用Fiber作为事件循环
Worker::$eventLoopClass = FiberEvent::class;

// 创建TCP服务
$worker = new Worker('text://0.0.0.0:12345');
$worker->count = 1;
$worker->reloadable = false;

// 控制台输出函数
function cecho(string $message): void
{
    echo "\033[36m" . date('Y-m-d H:i:s') . " [INFO] " . $message . "\033[0m\n";
}

function cerror(string $message): void
{
    echo "\033[31m" . date('Y-m-d H:i:s') . " [ERROR] " . $message . "\033[0m\n";
}

cecho("启动Workerman Fiber上下文ID测试服务器在 text://0.0.0.0:12345");
cecho("测试将在工作进程启动后自动运行");

// 记录所有协程的上下文ID和值
$results = [];

$worker->onMessage = function (TcpConnection $connection, $data) use (&$results) {
    $requestId = uniqid('req_');
    cecho("收到连接请求: $requestId, 数据: " . trim($data));

    // 获取当前协程ID和信息
    $fiber = \Fiber::getCurrent();
    $fiberId = spl_object_id($fiber);

    // 设置上下文并存储信息
    Context::set('test_key', "main_value_$requestId");
    $mainValue = Context::get('test_key');

    cecho("主协程 Fiber ID: $fiberId, 值: $mainValue");

    $contextInfo = [
        'requestId' => $requestId,
        'fibers' => [
            [
                'id' => $fiberId,
                'name' => 'main',
                'value' => $mainValue
            ]
        ]
    ];

    // 创建子协程并验证上下文隔离
    Coroutine::create(function () use ($connection, $requestId, &$contextInfo) {
        $childFiber = \Fiber::getCurrent();
        $childFiberId = spl_object_id($childFiber);

        // 在子协程中获取上下文值（应该是隔离的）
        $originalValue = Context::get('test_key');

        // 在子协程中设置新的上下文值
        Context::set('test_key', "child_value_$requestId");
        $childValue = Context::get('test_key');

        cecho("子协程 Fiber ID: $childFiberId, 原始值: " . ($originalValue ?? 'null') . ", 新值: $childValue");

        // 存储子协程信息
        $contextInfo['fibers'][] = [
            'id' => $childFiberId,
            'name' => 'child',
            'originalValue' => $originalValue,
            'value' => $childValue
        ];

        // 创建嵌套子协程
        Coroutine::create(function () use ($connection, $requestId, &$contextInfo) {
            $nestedFiber = \Fiber::getCurrent();
            $nestedFiberId = spl_object_id($nestedFiber);

            // 在嵌套子协程中获取上下文值
            $originalValue = Context::get('test_key');

            // 在嵌套子协程中设置新的上下文值
            Context::set('test_key', "nested_value_$requestId");
            $nestedValue = Context::get('test_key');

            cecho("嵌套协程 Fiber ID: $nestedFiberId, 原始值: " . ($originalValue ?? 'null') . ", 新值: $nestedValue");

            // 存储嵌套子协程信息
            $contextInfo['fibers'][] = [
                'id' => $nestedFiberId,
                'name' => 'nested',
                'originalValue' => $originalValue,
                'value' => $nestedValue
            ];

            // 发送完整上下文信息
            $connection->send(json_encode($contextInfo, JSON_PRETTY_PRINT));

            // 验证结果
            $fibers = $contextInfo['fibers'];
            $context_isolation = true;

            // 检查是否所有协程的ID都不同
            $ids = array_column($fibers, 'id');
            if (count($ids) !== count(array_unique($ids))) {
                $context_isolation = false;
                cecho("警告：有重复的Fiber ID");
            }

            // 检查是否每个新协程都未继承前一个协程的上下文
            for ($i = 1; $i < count($fibers); $i++) {
                if (isset($fibers[$i]['originalValue']) && $fibers[$i]['originalValue'] !== null) {
                    $context_isolation = false;
                    cecho("警告：协程上下文未正确隔离");
                }
            }

            if ($context_isolation) {
                cecho("✅ 验证通过：所有协程的上下文ID都不同，上下文正确隔离");
            } else {
                cecho("❌ 验证失败：协程上下文未正确隔离");
            }
        });
    });
};

$worker->onWorkerStart = function () {
    cecho("工作进程已启动");

    // 延迟2秒后直接触发测试，不依赖外部命令
    Timer::add(2, function () use (&$worker) {
        cecho("自动触发测试...");

        try {
            // 创建一个与自身的连接来触发测试
            $client = stream_socket_client('tcp://127.0.0.1:12345');
            if (!$client) {
                cerror("无法连接到测试服务器");
                Worker::stopAll();
                return;
            }

            // 发送测试数据
            fwrite($client, "test\n");
            cecho("已发送测试数据");

            // 非阻塞模式读取响应
            stream_set_blocking($client, false);

            // 等待5秒后终止进程，确保所有输出都显示
            Timer::add(5, function () {
                cecho("测试完成，退出进程");
                (new MasterKiller(new WorkermanLogger()))->killMaster();
            }, [], false);
        } catch (\Exception $e) {
            cerror("触发测试失败: " . $e->getMessage());
            (new MasterKiller(new WorkermanLogger()))->killMaster();
        }
    }, [], false);
};

Worker::runAll();
