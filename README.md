# Symfony + Workerman

Make symfony application faster, with less (or none) change.

## Available Request Handlers

### SymfonyRequestHandler

Handles requests by passing them to the Symfony HttpKernel.

### StaticFileRequestHandler

Serves static files from the public directory.

### HealthCheckRequestHandler

Provides a simple health check endpoint at `/health`.

### ChainRequestHandler

Chains multiple request handlers. If a handler returns a 404 response, the request is passed to the next handler in the chain.

Example:

```php
use Tourze\PSR15ChainRequestHandler\ChainRequestHandler;use Tourze\PSR15HealthCheckRequestHandler\HealthCheckRequestHandler;use Tourze\PSR15SymfonyRequestHandler\SymfonyRequestHandler;use Tourze\WorkermanServerBundle\RequestHandler\StaticFileRequestHandler;

// Create handlers
$healthCheckHandler = new HealthCheckRequestHandler();
$staticFileHandler = new StaticFileRequestHandler($kernel);
$symfonyHandler = new SymfonyRequestHandler($kernel, $foundationFactory, $messageFactory, $logger);

// Chain them together
$chainHandler = new ChainRequestHandler([
    $healthCheckHandler,
    $staticFileHandler,
    $symfonyHandler,
]);
```
