# Workerman Server Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![License](https://img.shields.io/packagist/l/tourze/workerman-server-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/workerman-server-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/workerman-server-bundle?style=flat-square)](https://codecov.io/gh/tourze/workerman-server-bundle)

A high-performance HTTP server for Symfony applications using Workerman. Make your Symfony application faster with minimal changes.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Commands](#commands)
- [Architecture](#architecture)
- [Performance Optimizations](#performance-optimizations)
- [Development Mode](#development-mode)
- [Production Deployment](#production-deployment)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Features

- **High Performance**: Built on Workerman's event-driven architecture
- **Symfony Integration**: Full support for Symfony's request/response lifecycle
- **Static File Serving**: Automatic static file serving with MIME type detection
- **Long-lived Connections**: HTTP keep-alive support
- **Process Management**: Built-in process management (start/stop/restart/reload)
- **Auto-reload**: File monitoring and auto-reload in development mode
- **Memory Protection**: Automatic worker restart after max requests
- **PSR Standards**: PSR-7, PSR-15, PSR-17 compliant

## Requirements

- PHP 8.1+
- Symfony 6.4+
- ext-pcntl
- ext-posix
- ext-fileinfo

## Installation

```bash
composer require tourze/workerman-server-bundle
```

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\WorkermanServerBundle\WorkermanServerBundle::class => ['all' => true],
];
```

## Quick Start

### Starting the HTTP Server

```bash
# Start the server
php bin/console workerman:http start

# Start as daemon
php bin/console workerman:http start -d

# Stop the server
php bin/console workerman:http stop

# Restart the server
php bin/console workerman:http restart

# Check server status
php bin/console workerman:http status

# Reload workers (graceful restart)
php bin/console workerman:http reload

# View connection details
php bin/console workerman:http connections
```

The server will start on `http://127.0.0.1:8080` by default.

## Commands

### workerman:http

The main command to manage the Workerman HTTP server.

**Description**: Start Workerman HTTP server for your Symfony application

**Usage**:
```bash
php bin/console workerman:http <action>
```

**Available actions**:
- `start`: Start the server
- `stop`: Stop the server
- `restart`: Restart the server
- `status`: Show server status
- `reload`: Gracefully reload all workers
- `connections`: Show current connections

**Options**:
- `-d`: Run as daemon (for start/restart actions)

**Features**:
- Serves both dynamic Symfony routes and static files
- Automatic worker restart after 10,000 requests (configurable)
- File monitoring in debug mode for auto-reload
- Integrated Messenger consumer support
- Keep-alive connection support

## Architecture

### Request Flow

1. Workerman receives HTTP request
2. Request is converted to PSR-7 ServerRequest
3. Static file check (serves from `public/` directory if exists)
4. PSR-7 request is converted to Symfony Request
5. Symfony kernel handles the request
6. Symfony Response is converted to PSR-7 Response
7. Response is sent back through Workerman
8. Kernel terminate event is triggered

### Components

- **WorkermanHttpCommand**: Console command for server management
- **OnMessage**: Core request handler
- **PsrRequestFactory**: Converts Workerman requests to PSR-7
- **WorkermanResponseEmitter**: Sends PSR-7 responses to clients
- **ProperHeaderCasingResponseFactory**: Ensures proper HTTP header casing

## Performance Optimizations

1. **Memory Management**: Workers automatically restart after processing 10,000 requests
2. **File Monitoring**: Only enabled in debug mode to avoid overhead
3. **Static File Serving**: Direct file serving bypasses Symfony kernel
4. **Connection Reuse**: HTTP keep-alive reduces connection overhead
5. **Error Tracking**: Optimized backtrace with ignored vendor files

## Development Mode

In development mode (`APP_ENV=dev`), the bundle provides:
- Automatic file monitoring for changes in:
  - `config/`
  - `src/`
  - `templates/`
  - `translations/`
- Auto-reload when PHP, YAML files change
- Detailed error output

## Production Deployment

For production use:

```bash
# Start as daemon with production environment
APP_ENV=prod php bin/console workerman:http start -d

# Monitor server status
php bin/console workerman:http status

# Graceful reload without downtime
php bin/console workerman:http reload
```

## Advanced Usage

### Custom Request Handlers

You can extend the request handling by implementing PSR-15 middleware:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Your custom logic here
    }
}
```

### Integration with Symfony Messenger

The bundle automatically starts a Messenger consumer worker when the HTTP server starts, allowing you to process async messages without separate processes.

## Troubleshooting

### Server won't start
- Check if port 8080 is already in use
- Ensure PHP has pcntl and posix extensions enabled
- Check file permissions for pid and log files

### High memory usage
- Reduce max request count per worker
- Check for memory leaks in your application code
- Monitor with `workerman:http status`

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
