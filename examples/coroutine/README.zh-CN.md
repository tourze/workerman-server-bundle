# Workerman v5 Fiber协程上下文测试

[English](README.md) | [中文](README.zh-CN.md)

这个目录包含了用于测试Workerman v5中Fiber事件循环下协程上下文隔离和并发模型的示例脚本。

## 测试脚本

### 1. 基础上下文隔离测试 (verify-fiber-context.php)

测试Workerman v5 Fiber协程的上下文ID是否正确隔离。

```bash
# 从项目根目录运行
php packages/workerman-server-bundle/examples/coroutine/verify-fiber-context.php

# 测试将自动运行，无需手动触发
```

### 2. 协程池上下文测试 (verify-coroutine-pool.php)

测试在使用连接池时，协程上下文的隔离和连接复用。

```bash
# 从项目根目录运行
php packages/workerman-server-bundle/examples/coroutine/verify-coroutine-pool.php

# 测试将自动运行，无需手动触发
```

### 3. 并发模型测试 (verify-parallel-barrier.php)

测试不同并发模型（串行、Parallel、Barrier、Channel）的性能和上下文隔离。

```bash
# 从项目根目录运行
php packages/workerman-server-bundle/examples/coroutine/verify-parallel-barrier.php

# 测试将自动运行，无需手动触发
```

## 测试内容

这些测试脚本验证以下内容：

1. **协程上下文ID隔离**：验证不同协程的上下文是否正确隔离，互不影响
2. **连接池机制**：验证协程池中的资源正确分配和回收
3. **并发模型性能**：对比不同并发模型的执行效率和速度提升
4. **协程ID唯一性**：验证每个协程都有唯一的Fiber ID

## 测试结果解读

测试结果会输出到控制台和发送给客户端连接。关键指标：

- **上下文隔离测试**：验证每个协程都有独立的上下文，不会相互干扰
- **协程ID测试**：验证每个协程都有唯一的Fiber ID
- **连接池测试**：验证连接池能够正确管理和复用资源
- **性能对比**：对比不同并发模型的效率和加速比

## 协程特性分析

验证Workerman v5 Fiber事件循环模型下的特性：

1. **上下文隔离**：协程间数据是隔离的，通过Context类进行传递
2. **资源共享**：使用连接池实现资源的高效共享
3. **并发效率**：并行执行可显著提高IO密集型任务的处理效率
