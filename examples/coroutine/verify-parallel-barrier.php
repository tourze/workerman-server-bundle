<?php
/**
 * 验证Workerman v5 Fiber协程并发模型（Parallel和Barrier）测试脚本
 *
 * 使用方法：
 * 1. 从项目根目录运行: php packages/workerman-server-bundle/examples/coroutine/verify-parallel-barrier.php
 * 2. 测试将自动运行，无需手动触发
 */

// 引用根项目的autoload.php，而不是当前包的vendor目录
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Tourze\Workerman\MasterKillber\MasterKiller;
use Tourze\Workerman\PsrLogger\WorkermanLogger;
use Workerman\Connection\TcpConnection;
use Workerman\Coroutine;
use Workerman\Coroutine\Barrier;
use Workerman\Coroutine\Channel;
use Workerman\Coroutine\Context;
use Workerman\Coroutine\Parallel;
use Workerman\Events\Fiber as FiberEvent;
use Workerman\Timer;
use Workerman\Worker;

// 使用Fiber作为事件循环
Worker::$eventLoopClass = FiberEvent::class;

// 创建TCP服务
$worker = new Worker('text://0.0.0.0:12347');
$worker->count = 1;
$worker->reloadable = false;

// 控制台输出函数
function cecho(string $message): void
{
    echo "\033[36m" . date('Y-m-d H:i:s') . " [INFO] " . $message . "\033[0m\n";
}

cecho("启动Workerman Fiber并发模型测试服务器在 text://0.0.0.0:12347");
cecho("测试将在工作进程启动后自动运行");

/**
 * 模拟异步任务
 *
 * @param string $taskId 任务ID
 * @param float $duration 任务持续时间（秒）
 * @return array 任务结果
 */
function asyncTask(string $taskId, float $duration = 0.5): array
{
    $fiberId = spl_object_id(\Fiber::getCurrent());
    $startTime = microtime(true);

    cecho("任务 {$taskId} 开始 (Fiber ID: {$fiberId})");

    // 使用Timer模拟延迟
    $wait = new Channel();
    Timer::add($duration, function () use ($wait) {
        $wait->push(1);
    }, [], false);
    $wait->pop();

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    cecho("任务 {$taskId} 完成 (耗时: {$executionTime}秒)");

    return [
        'taskId' => $taskId,
        'fiberId' => $fiberId,
        'startTime' => $startTime,
        'endTime' => $endTime,
        'executionTime' => $executionTime,
        'contextId' => Context::get('request_id') ?? null
    ];
}

// Worker启动事件
$worker->onWorkerStart = function () {
    cecho("工作进程已启动");

    // 延迟2秒后直接触发测试，不依赖外部命令
    Timer::add(2, function () {
        cecho("自动触发测试...");

        try {
            // 创建一个与自身的连接来触发测试
            $client = stream_socket_client('tcp://127.0.0.1:12347');
            if (!$client) {
                echo "\033[31m" . date('Y-m-d H:i:s') . " [ERROR] 无法连接到测试服务器\033[0m\n";
                Worker::stopAll();
                return;
            }

            // 发送测试数据
            fwrite($client, "test\n");
            cecho("已发送测试数据");

            // 非阻塞模式读取响应
            stream_set_blocking($client, false);

            // 等待8秒后终止进程，确保所有输出都显示
            Timer::add(8, function () {
                cecho("测试完成，退出进程");
                (new MasterKiller(new WorkermanLogger()))->killMaster();
            }, [], false);
        } catch (\Exception $e) {
            echo "\033[31m" . date('Y-m-d H:i:s') . " [ERROR] 触发测试失败: " . $e->getMessage() . "\033[0m\n";
            (new MasterKiller(new WorkermanLogger()))->killMaster();
        }
    }, [], false);
};

// Worker接收消息事件
$worker->onMessage = function (TcpConnection $connection, $data) {
    $requestId = uniqid('req_');
    cecho("收到连接请求: $requestId");

    // 设置协程上下文ID
    Context::set('request_id', $requestId);

    $result = [
        'requestId' => $requestId,
        'mainFiberId' => spl_object_id(\Fiber::getCurrent()),
        'tests' => []
    ];

    // 测试1: 串行执行任务
    $serialStart = microtime(true);
    cecho("开始串行测试...");

    $serialResults = [];
    $serialResults[] = asyncTask("serial_1", 0.5);
    $serialResults[] = asyncTask("serial_2", 0.5);
    $serialResults[] = asyncTask("serial_3", 0.5);

    $serialEnd = microtime(true);
    $serialTime = $serialEnd - $serialStart;

    cecho("串行测试完成，总耗时: {$serialTime}秒");

    $result['tests']['serial'] = [
        'totalTime' => $serialTime,
        'tasks' => $serialResults
    ];

    // 测试2: 使用Parallel并行执行任务
    $parallelStart = microtime(true);
    cecho("开始Parallel并行测试...");

    $parallel = new Parallel(3);
    $parallel->add(function () {
        return asyncTask("parallel_1", 0.5);
    });
    $parallel->add(function () {
        return asyncTask("parallel_2", 0.5);
    });
    $parallel->add(function () {
        return asyncTask("parallel_3", 0.5);
    });

    $parallelResults = $parallel->wait();

    $parallelEnd = microtime(true);
    $parallelTime = $parallelEnd - $parallelStart;

    cecho("Parallel并行测试完成，总耗时: {$parallelTime}秒");

    $result['tests']['parallel'] = [
        'totalTime' => $parallelTime,
        'tasks' => $parallelResults
    ];

    // 测试3: 使用Barrier并行执行任务
    $barrierStart = microtime(true);
    cecho("开始Barrier并行测试...");

    $barrier = new Barrier();

    // 启动三个协程
    $task1Channel = new Channel();
    $task2Channel = new Channel();
    $task3Channel = new Channel();

    Coroutine::create(function () use ($task1Channel) {
        $result = asyncTask("barrier_1", 0.5);
        $task1Channel->push($result);
    });

    Coroutine::create(function () use ($task2Channel) {
        $result = asyncTask("barrier_2", 0.8);
        $task2Channel->push($result);
    });

    Coroutine::create(function () use ($task3Channel) {
        $result = asyncTask("barrier_3", 0.3);
        $task3Channel->push($result);
    });

    // 等待所有任务完成
    $barrierResults = [
        'task1' => $task1Channel->pop(),
        'task2' => $task2Channel->pop(),
        'task3' => $task3Channel->pop()
    ];

    $barrierEnd = microtime(true);
    $barrierTime = $barrierEnd - $barrierStart;

    cecho("Barrier并行测试完成，总耗时: {$barrierTime}秒");

    $result['tests']['barrier'] = [
        'totalTime' => $barrierTime,
        'tasks' => $barrierResults
    ];

    // 测试4: 使用Channel进行并行任务
    cecho("开始Channel测试...");
    $channelStart = microtime(true);

    $channel = new Channel(3);

    Coroutine::create(function () use ($channel) {
        $result = asyncTask("channel_1", 0.7);
        $channel->push($result);
    });

    Coroutine::create(function () use ($channel) {
        $result = asyncTask("channel_2", 0.4);
        $channel->push($result);
    });

    Coroutine::create(function () use ($channel) {
        $result = asyncTask("channel_3", 0.6);
        $channel->push($result);
    });

    // 获取所有结果
    $channelResults = [];
    for ($i = 0; $i < 3; $i++) {
        $channelResults[] = $channel->pop();
    }

    $channelEnd = microtime(true);
    $channelTime = $channelEnd - $channelStart;

    cecho("Channel测试完成，总耗时: {$channelTime}秒");

    $result['tests']['channel'] = [
        'totalTime' => $channelTime,
        'tasks' => $channelResults
    ];

    // 验证并发模型特性
    $result['summary'] = [
        'serial_vs_parallel' => [
            'speedup' => $serialTime / $parallelTime,
            'efficiency' => ($serialTime / $parallelTime) / 3
        ],
        'serial_vs_barrier' => [
            'speedup' => $serialTime / $barrierTime,
            'efficiency' => ($serialTime / $barrierTime) / 3
        ],
        'serial_vs_channel' => [
            'speedup' => $serialTime / $channelTime,
            'efficiency' => ($serialTime / $channelTime) / 3
        ]
    ];

    // 收集所有任务的FiberID和上下文ID
    $fiberIds = [];
    $contextIds = [];

    foreach ($result['tests'] as $testType => $testData) {
        foreach ($testData['tasks'] as $task) {
            if (isset($task['fiberId'])) {
                $fiberIds[] = $task['fiberId'];
            }
            if (isset($task['contextId'])) {
                $contextIds[] = $task['contextId'];
            }
        }
    }

    // 检查Fiber ID是否唯一
    $uniqueFiberIds = count(array_unique($fiberIds));

    // 计算预期的Fiber ID数量
    // 串行任务应该共享同一个Fiber ID，而并行任务应该各有自己的Fiber ID
    $serialTasks = count($result['tests']['serial']['tasks'] ?? []);
    $parallelTasks = count($result['tests']['parallel']['tasks'] ?? []) +
        count($result['tests']['barrier']['tasks'] ?? []) +
        count($result['tests']['channel']['tasks'] ?? []);

    // 预期的不同Fiber ID数量：串行使用1个，并行每个任务1个
    $expectedUniqueFiberIds = ($serialTasks > 0 ? 1 : 0) + $parallelTasks;

    $result['validation'] = [
        'unique_fiber_ids' => $uniqueFiberIds,
        'total_fiber_ids' => count($fiberIds),
        'expected_unique_fiber_ids' => $expectedUniqueFiberIds,
        'context_isolation' => count(array_filter($contextIds, function ($id) use ($requestId) {
                return $id === $requestId;
            })) === 0
    ];

    // 输出验证结果
    cecho("并发测试验证结果:");
    cecho("- 总协程数: " . count($fiberIds));
    cecho("- 不同Fiber ID数: {$uniqueFiberIds}");
    cecho("- 预期不同Fiber ID数: {$expectedUniqueFiberIds} (串行任务共享1个ID，并行任务各自1个ID)");

    // 打印所有Fiber ID详细信息
    cecho("所有Fiber ID详细信息:");
    $fiberIdCounts = array_count_values($fiberIds);
    $detailedInfo = [];

    // 收集每个测试的Fiber ID详情
    foreach ($result['tests'] as $testType => $testData) {
        $detailedInfo[$testType] = [];
        foreach ($testData['tasks'] as $taskIndex => $task) {
            if (isset($task['fiberId'])) {
                $taskId = $task['taskId'] ?? "task_$taskIndex";
                $detailedInfo[$testType][$taskId] = $task['fiberId'];
            }
        }
    }

    // 输出各测试的Fiber ID
    foreach ($detailedInfo as $testType => $tasks) {
        cecho("- {$testType} 测试:");
        foreach ($tasks as $taskId => $fiberId) {
            $count = $fiberIdCounts[$fiberId];
            $duplicateTag = $count > 1 ? "【重复{$count}次】" : "";
            $expectation = $testType === 'serial' ? "【正常，串行共享ID】" : "";
            cecho("  - {$taskId}: Fiber ID {$fiberId} {$duplicateTag} {$expectation}");
        }
    }

    // 输出重复的Fiber ID
    $duplicateFiberIds = array_filter($fiberIdCounts, function ($count) {
        return $count > 1;
    });
    if (!empty($duplicateFiberIds)) {
        cecho("重复的Fiber ID:");
        foreach ($duplicateFiberIds as $fiberId => $count) {
            $isSerial = false;
            foreach ($detailedInfo['serial'] ?? [] as $taskId => $id) {
                if ($id === $fiberId) {
                    $isSerial = true;
                    break;
                }
            }
            $normalityTag = $isSerial ? "【正常，串行任务】" : "【异常，并行任务不应共享ID】";
            cecho("- Fiber ID {$fiberId} 出现了 {$count} 次 {$normalityTag}");
        }
    }

    // 验证Fiber ID数量是否符合预期
    if ($uniqueFiberIds === $expectedUniqueFiberIds) {
        cecho("✅ 协程ID测试通过：Fiber ID数量符合预期特性");
    } else {
        cecho("❌ 协程ID测试失败：Fiber ID数量不符合预期，应有 {$expectedUniqueFiberIds} 个不同ID，实际有 {$uniqueFiberIds} 个");
    }

    // 验证并行任务之间的上下文隔离
    $parallelContextLeak = false;

    // 仅在并行测试中检查上下文隔离
    foreach (['parallel', 'barrier', 'channel'] as $testType) {
        if (isset($result['tests'][$testType])) {
            $testData = $result['tests'][$testType];
            $testFiberIds = [];
            $testContextIds = [];

            foreach ($testData['tasks'] as $task) {
                if (isset($task['fiberId'])) {
                    $testFiberIds[$task['fiberId']] = true;
                }
                if (isset($task['contextId']) && $task['contextId'] !== null) {
                    $testContextIds[$task['contextId']] = true;
                }
            }

            // 如果并行测试中有上下文ID等于请求ID，则有泄漏
            foreach ($testContextIds as $contextId => $_) {
                if ($contextId === $requestId) {
                    $parallelContextLeak = true;
                    break;
                }
            }
        }
    }

    if (!$parallelContextLeak) {
        cecho("✅ 并行上下文隔离测试通过：并行任务中没有主协程上下文泄漏");
    } else {
        cecho("❌ 并行上下文隔离测试失败：并行任务中存在主协程上下文泄漏");
    }

    // 发送结果
    $connection->send(json_encode($result, JSON_PRETTY_PRINT));
};

Worker::runAll();
