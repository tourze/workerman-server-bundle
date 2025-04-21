<?php

namespace Tourze\WorkermanServerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\WorkermanServerBundle\HTTP\OnMessage;
use Tourze\WorkermanServerBundle\HTTP\SymfonyRequestHandler;

class WorkermanServerBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();

        Backtrace::addProdIgnoreFiles((new \ReflectionClass(SymfonyRequestHandler::class))->getFileName());
        Backtrace::addProdIgnoreFiles((new \ReflectionClass(OnMessage::class))->getFileName());
    }
}
