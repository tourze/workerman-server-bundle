<?php

namespace Tourze\WorkermanServerBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;
use Workerman\Coroutine;

#[AsDecorator(decorates: ContextServiceInterface::class)]
class WorkermanContextService implements ContextServiceInterface
{
    public function getId(): string
    {
        Coroutine::getCurrent()->id();
    }

    public function defer(callable $callback): void
    {
        Coroutine::defer($callback);
    }

    public function supportCoroutine(): bool
    {
        return Coroutine::isCoroutine();
    }
}
