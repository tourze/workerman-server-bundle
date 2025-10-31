# Workerman Server Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![License](https://img.shields.io/packagist/l/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/workerman-server-bundle?style=flat-square)](https://codecov.io/gh/tourze/workerman-server-bundle)

使用 Workerman 为 Symfony 应用提供高性能 HTTP 服务器。通过最小的修改让您的 Symfony 应用运行得更快。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [快速开始](#快速开始)
- [命令](#命令)
- [架构](#架构)
- [性能优化](#性能优化)
- [开发模式](#开发模式)
- [生产部署](#生产部署)
- [高级用法](#高级用法)
- [故障排除](#故障排除)
- [贡献](#贡献)
- [许可证](#许可证)

## 功能特性

- **高性能**：基于 Workerman 的事件驱动架构
- **Symfony 集成**：完全支持 Symfony 的请求/响应生命周期
- **静态文件服务**：自动提供静态文件服务，支持 MIME 类型检测
- **长连接支持**：HTTP keep-alive 支持
- **进程管理**：内置进程管理（启动/停止/重启/重载）
- **自动重载**：开发模式下文件监控和自动重载
- **内存保护**：达到最大请求数后自动重启 worker
- **PSR 标准**：符合 PSR-7、PSR-15、PSR-17 标准

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- ext-pcntl
- ext-posix
- ext-fileinfo

## 安装

```bash
composer require tourze/workerman-server-bundle
```

在 `config/bundles.php` 中添加 bundle：

```php
return [
    // ...
    Tourze\WorkermanServerBundle\WorkermanServerBundle::class => ['all' => true],
];
```

## 快速开始

### 启动 HTTP 服务器

```bash
# 启动服务器
php bin/console workerman:http start

# 以守护进程方式启动
php bin/console workerman:http start -d

# 停止服务器
php bin/console workerman:http stop

# 重启服务器
php bin/console workerman:http restart

# 查看服务器状态
php bin/console workerman:http status

# 重载 worker（平滑重启）
php bin/console workerman:http reload

# 查看连接详情
php bin/console workerman:http connections
```

服务器默认会在 `http://127.0.0.1:8080` 上启动。

## 命令

### workerman:http

管理 Workerman HTTP 服务器的主要命令。

**描述**：启动 Workerman HTTP 服务器来运行您的 Symfony 应用

**用法**：
```bash
php bin/console workerman:http <action>
```

**可用操作**：
- `start`：启动服务器
- `stop`：停止服务器
- `restart`：重启服务器
- `status`：显示服务器状态
- `reload`：平滑重载所有 worker
- `connections`：显示当前连接

**选项**：
- `-d`：以守护进程方式运行（用于 start/restart 操作）

**功能特点**：
- 同时提供动态 Symfony 路由和静态文件服务
- 处理 10,000 个请求后自动重启 worker（可配置）
- 调试模式下文件监控和自动重载
- 集成 Messenger 消费者支持
- Keep-alive 连接支持

## 架构

### 请求流程

1. Workerman 接收 HTTP 请求
2. 请求转换为 PSR-7 ServerRequest
3. 静态文件检查（如果存在则从 `public/` 目录提供）
4. PSR-7 请求转换为 Symfony Request
5. Symfony 内核处理请求
6. Symfony Response 转换为 PSR-7 Response
7. 通过 Workerman 发送响应
8. 触发内核 terminate 事件

### 组件

- **WorkermanHttpCommand**：服务器管理的控制台命令
- **OnMessage**：核心请求处理器
- **PsrRequestFactory**：将 Workerman 请求转换为 PSR-7
- **WorkermanResponseEmitter**：向客户端发送 PSR-7 响应
- **ProperHeaderCasingResponseFactory**：确保正确的 HTTP 头部大小写

## 性能优化

1. **内存管理**：Worker 在处理 10,000 个请求后自动重启
2. **文件监控**：仅在调试模式下启用以避免开销
3. **静态文件服务**：直接文件服务绕过 Symfony 内核
4. **连接复用**：HTTP keep-alive 减少连接开销
5. **错误跟踪**：优化的堆栈跟踪，忽略 vendor 文件

## 开发模式

在开发模式下（`APP_ENV=dev`），该 bundle 提供：
- 自动监控以下目录的文件变化：
  - `config/`
  - `src/`
  - `templates/`
  - `translations/`
- PHP、YAML 文件变化时自动重载
- 详细的错误输出

## 生产部署

生产环境使用：

```bash
# 以守护进程方式启动，使用生产环境
APP_ENV=prod php bin/console workerman:http start -d

# 监控服务器状态
php bin/console workerman:http status

# 平滑重载，无停机时间
php bin/console workerman:http reload
```

## 高级用法

### 自定义请求处理器

您可以通过实现 PSR-15 中间件来扩展请求处理：

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 您的自定义逻辑
    }
}
```

### 与 Symfony Messenger 集成

该 bundle 在启动 HTTP 服务器时会自动启动一个 Messenger 消费者 worker，让您无需单独的进程就可以处理异步消息。

## 故障排除

### 服务器无法启动
- 检查 8080 端口是否已被占用
- 确保 PHP 已启用 pcntl 和 posix 扩展
- 检查 pid 和日志文件的文件权限

### 内存使用过高
- 减少每个 worker 的最大请求数
- 检查应用代码中的内存泄漏
- 使用 `workerman:http status` 监控

## 贡献

欢迎贡献！请随时提交 Pull Request。

## 许可证

MIT 许可证（MIT）。请参阅 [许可文件](LICENSE) 了解更多信息。
