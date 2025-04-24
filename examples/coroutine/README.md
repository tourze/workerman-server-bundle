# Workerman v5 Fiber Coroutine Tests

[English](README.md) | [中文](README.zh-CN.md)

This directory contains example scripts for testing Workerman v5 Fiber event loop, coroutine context isolation, and concurrency models.

## Test Scripts

### 1. Basic Context Isolation Test (verify-fiber-context.php)

Tests whether Workerman v5 Fiber coroutine context IDs are properly isolated.

```bash
# Run from the project root directory
php packages/workerman-server-bundle/examples/coroutine/verify-fiber-context.php

# The test will run automatically, no manual triggering needed
```

### 2. Coroutine Pool Context Test (verify-coroutine-pool.php)

Tests context isolation and connection reuse in a connection pool.

```bash
# Run from the project root directory
php packages/workerman-server-bundle/examples/coroutine/verify-coroutine-pool.php

# The test will run automatically, no manual triggering needed
```

### 3. Concurrency Model Test (verify-parallel-barrier.php)

Tests different concurrency models (Serial, Parallel, Barrier, Channel) for performance and context isolation.

```bash
# Run from the project root directory
php packages/workerman-server-bundle/examples/coroutine/verify-parallel-barrier.php

# The test will run automatically, no manual triggering needed
```

## Test Content

These test scripts verify the following:

1. **Coroutine Context Isolation**: Verifies that different coroutines have isolated contexts
2. **Connection Pool Mechanism**: Verifies proper resource allocation and recovery in coroutine pools
3. **Concurrency Model Performance**: Compares the efficiency of different concurrency models
4. **Coroutine ID Uniqueness**: Verifies that each coroutine has a unique Fiber ID

## Test Result Interpretation

Test results are output to the console and sent to client connections. Key metrics:

- **Context Isolation Test**: Verifies each coroutine has its own context that doesn't interfere with others
- **Coroutine ID Test**: Verifies each coroutine has a unique Fiber ID
- **Connection Pool Test**: Verifies the connection pool properly manages and reuses resources
- **Performance Comparison**: Compares efficiency and speed gains between different concurrency models

## Coroutine Feature Analysis

Verification of Workerman v5 Fiber event loop model features:

1. **Context Isolation**: Data between coroutines is isolated and passed through the Context class
2. **Resource Sharing**: Connection pools enable efficient resource sharing
3. **Concurrency Efficiency**: Parallel execution significantly improves IO-intensive task processing
