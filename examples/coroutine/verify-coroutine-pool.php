<?php
/**
 * 验证Workerman v5 Fiber协程池和上下文管理测试脚本
 *
 * 使用方法：
 * 1. 从项目根目录运行: php packages/workerman-server-bundle/examples/coroutine/verify-coroutine-pool.php
 * 2. 测试将自动运行，无需手动触发
 */

// 引用根项目的autoload.php，而不是当前包的vendor目录
require_once __DIR__ . '/../../../../vendor/autoload.php';

use Tourze\Workerman\MasterKillber\MasterKiller;
use Tourze\Workerman\PsrLogger\WorkermanLogger;
use Workerman\Connection\TcpConnection;
use Workerman\Coroutine;
use Workerman\Coroutine\Context;
use Workerman\Coroutine\Pool;
use Workerman\Events\Fiber as FiberEvent;
use Workerman\Timer;
use Workerman\Worker;

// 使用Fiber作为事件循环
Worker::$eventLoopClass = FiberEvent::class;

// 创建TCP服务
$worker = new Worker('text://0.0.0.0:12346');
$worker->count = 1;
$worker->reloadable = false;

// 控制台输出函数
function cecho(string $message): void
{
    echo "\033[36m" . date('Y-m-d H:i:s') . " [INFO] " . $message . "\033[0m\n";
}

cecho("启动Workerman Fiber协程池上下文测试服务器在 text://0.0.0.0:12346");
cecho("测试将在工作进程启动后自动运行");

/**
 * 模拟Redis连接类
 */
class MockRedis
{
    private string $id;

    public function __construct()
    {
        $this->id = uniqid('redis_');
        cecho("创建新的MockRedis连接: {$this->id}");
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function connect(string $host, int $port): bool
    {
        cecho("MockRedis {$this->id} 连接到 {$host}:{$port}");
        return true;
    }

    public function set(string $key, string $value): bool
    {
        cecho("MockRedis {$this->id} 设置 {$key} = {$value}");
        return true;
    }

    public function get(string $key): ?string
    {
        $value = "value_for_{$key}_from_{$this->id}";
        cecho("MockRedis {$this->id} 获取 {$key} = {$value}");
        return $value;
    }

    public function close(): void
    {
        cecho("关闭MockRedis连接: {$this->id}");
    }

    public function ping(): bool
    {
        cecho("Ping MockRedis {$this->id}");
        return true;
    }
}

/**
 * 定义Redis连接池类
 */
class RedisPool
{
    private Pool $pool;

    public function __construct(string $host, int $port, int $maxConnections = 5)
    {
        $this->pool = new Pool($maxConnections);

        // 设置连接创建器
        $this->pool->setConnectionCreator(function () use ($host, $port) {
            $redis = new MockRedis();
            $redis->connect($host, $port);
            return $redis;
        });

        // 设置连接关闭器
        $this->pool->setConnectionCloser(function ($redis) {
            $redis->close();
        });

        // 设置心跳检查器
        $this->pool->setHeartbeatChecker(function ($redis) {
            return $redis->ping();
        });

        cecho("Redis连接池已创建，最大连接数: {$maxConnections}");
    }

    public function get(): MockRedis
    {
        $redis = $this->pool->get();
        cecho("从连接池获取Redis连接: {$redis->getId()}");
        return $redis;
    }

    public function put(MockRedis $redis): void
    {
        cecho("归还Redis连接到池: {$redis->getId()}");
        $this->pool->put($redis);
    }
}

/**
 * 自动管理Redis连接的静态类
 */
class Db
{
    private static ?RedisPool $pool = null;

    /**
     * 初始化连接池
     */
    private static function initializePool(): void
    {
        if (self::$pool === null) {
            self::$pool = new RedisPool('127.0.0.1', 6379, 3);
            cecho("Db类初始化了Redis连接池");
        }
    }

    /**
     * 静态方法拦截器
     */
    public static function __callStatic(string $name, array $arguments)
    {
        self::initializePool();

        // 从协程上下文获取连接
        // 这确保同一协程内使用相同的连接
        $redis = Context::get('redis');

        if (!$redis) {
            // 如果上下文中没有连接，从连接池获取一个
            $redis = self::$pool->get();
            Context::set('redis', $redis);

            // 当协程销毁时，将连接返回到池中
            Coroutine::defer(function () use ($redis) {
                self::$pool->put($redis);
                cecho("协程结束，已通过defer归还Redis连接");
            });
        }

        // 调用Redis方法
        return call_user_func_array([$redis, $name], $arguments);
    }
}

// Worker启动事件
$worker->onWorkerStart = function () {
    cecho("工作进程已启动");

    // 延迟2秒后直接触发测试，不依赖外部命令
    Timer::add(2, function () {
        cecho("自动触发测试...");

        try {
            // 创建一个与自身的连接来触发测试
            $client = stream_socket_client('tcp://127.0.0.1:12346');
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

            // 等待5秒后终止进程，确保所有输出都显示
            Timer::add(5, function () {
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

    // 设置协程上下文ID，以便跟踪
    Context::set('request_id', $requestId);

    $result = [
        'requestId' => $requestId,
        'mainFiberId' => spl_object_id(\Fiber::getCurrent()),
        'operations' => []
    ];

    // 测试1: 在主协程中使用Db类
    $key1 = "key1_{$requestId}";
    $value1 = Db::get($key1);
    $result['operations'][] = [
        'step' => 1,
        'fiberId' => spl_object_id(\Fiber::getCurrent()),
        'action' => "主协程获取 {$key1}",
        'value' => $value1,
        'redisId' => Context::get('redis')->getId()
    ];

    // 测试2: 在子协程中使用Db类
    Coroutine::create(function () use ($connection, $requestId, &$result) {
        // 验证协程上下文隔离
        $current_request_id = Context::get('request_id');

        $key2 = "key2_{$requestId}";
        $value2 = Db::get($key2); // 这将获取一个新的连接

        $result['operations'][] = [
            'step' => 2,
            'fiberId' => spl_object_id(\Fiber::getCurrent()),
            'action' => "子协程获取 {$key2}",
            'value' => $value2,
            'redisId' => Context::get('redis')->getId(),
            'requestIdInContext' => $current_request_id
        ];

        // 测试3: 再次使用相同的协程上下文
        $key3 = "key3_{$requestId}";
        $value3 = Db::get($key3); // 应该复用之前的连接

        $result['operations'][] = [
            'step' => 3,
            'fiberId' => spl_object_id(\Fiber::getCurrent()),
            'action' => "子协程再次获取 {$key3}",
            'value' => $value3,
            'redisId' => Context::get('redis')->getId()
        ];

        // 测试4: 嵌套协程
        Coroutine::create(function () use ($connection, $requestId, &$result) {
            $key4 = "key4_{$requestId}";
            $value4 = Db::get($key4); // 这将获取另一个新连接

            $result['operations'][] = [
                'step' => 4,
                'fiberId' => spl_object_id(\Fiber::getCurrent()),
                'action' => "嵌套协程获取 {$key4}",
                'value' => $value4,
                'redisId' => Context::get('redis')->getId(),
                'requestIdInContext' => Context::get('request_id')
            ];

            // 发送结果并验证
            $connection->send(json_encode($result, JSON_PRETTY_PRINT));

            // 验证上下文隔离
            $uniqueFiberIds = count(array_unique(array_column($result['operations'], 'fiberId')));
            $uniqueRedisIds = count(array_unique(array_column($result['operations'], 'redisId')));

            cecho("测试总结:");
            cecho("- 总操作数: " . count($result['operations']));
            cecho("- 不同Fiber ID数: {$uniqueFiberIds}");
            cecho("- 不同Redis连接数: {$uniqueRedisIds}");

            // 协程ID应该是唯一的
            if ($uniqueFiberIds === 3) { // 主协程、子协程、嵌套协程
                cecho("✅ 协程ID测试通过：每个协程有唯一的ID");
            } else {
                cecho("❌ 协程ID测试失败：协程ID不唯一");
            }

            // 检查连接池是否正常工作
            if ($uniqueRedisIds <= 3) { // 最多3个连接（连接池大小）
                cecho("✅ 连接池测试通过：连接池正常工作");
            } else {
                cecho("❌ 连接池测试失败：连接池未限制连接数");
            }

            // 检查requestId在不同协程之间是否正确隔离
            $requestIds = array_column(
                array_filter($result['operations'], function ($op) {
                    return isset($op['requestIdInContext']);
                }),
                'requestIdInContext'
            );

            $allNull = array_reduce($requestIds, function ($carry, $item) {
                return $carry && ($item === null);
            }, true);

            if ($allNull || count(array_unique($requestIds)) > 1) {
                cecho("✅ 上下文隔离测试通过：不同协程的上下文正确隔离");
            } else {
                cecho("❌ 上下文隔离测试失败：上下文在协程间泄漏");
            }
        });
    });
};

Worker::runAll();
