# Workerman Server Bundle

Fork from tourze/workerman-server-bundle, but more activity and fetures.

[English](https://github.com/tourze/workerman-server-bundle/blob/master/README.md) | [中文](https://github.com/tourze/workerman-server-bundle/blob/master/README.zh-CN.md)

## Installation

```bash
composer require mickeywaugh/workerman-httpserver
```

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Mickeywaugh\WorkermanServerBundle\WorkermanServerBundle::class => ['all' => true],
];
```

## Quick Start

### Starting the HTTP Server

```bash
# Start the server
php bin/console workerman:http start --port=8080 --host=127.0.0.1

# Start as daemon
php bin/console workerman:http start -d --port=8080 --host=127.0.0.1

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

**Options**:

- `--host`: The host address to bind to (default: 127.0.0.1). Also can be set via the environment variable `WORKERMAN_SERVER_HOST`.
- `--port`: The port number to listen on (default: 8080). Also can be set via the environment variable `WORKERMAN_SERVER_PORT`.
- `--daemonize or -d`: Run the server as a daemon process
  