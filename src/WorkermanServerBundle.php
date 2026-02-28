<?php

namespace Mickeywaugh\WorkermanServerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\PSR15SymfonyRequestHandler\SymfonyRequestHandler;
use Mickeywaugh\WorkermanServerBundle\HTTP\OnMessage;

class WorkermanServerBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();

        $symfonyRequestHandlerFile = (new \ReflectionClass(SymfonyRequestHandler::class))->getFileName();
        if (false !== $symfonyRequestHandlerFile) {
            Backtrace::addProdIgnoreFiles($symfonyRequestHandlerFile);
        }

        $onMessageFile = (new \ReflectionClass(OnMessage::class))->getFileName();
        if (false !== $onMessageFile) {
            Backtrace::addProdIgnoreFiles($onMessageFile);
        }
    }
}
