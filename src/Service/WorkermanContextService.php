<?php

namespace Tourze\WorkermanServerBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Tourze\Symfony\RuntimeContextBundle\Service\ContextServiceInterface;
use Workerman\Coroutine;
use Workerman\Worker;

#[AsDecorator(decorates: ContextServiceInterface::class)]
class WorkermanContextService implements ContextServiceInterface
{
    public function __construct(
        #[AutowireDecorated] private readonly ContextServiceInterface $inner,
    )
    {
    }

    public function getId(): string
    {
        if (!Worker::isRunning()) {
            return $this->inner->getId();
        }
        return Coroutine::getCurrent()->id();
    }

    public function defer(callable $callback): void
    {
        if (!Worker::isRunning()) {
            $this->inner->defer($callback);
            return;
        }
        Coroutine::defer($callback);
    }

    public function supportCoroutine(): bool
    {
        if (!Worker::isRunning()) {
            return $this->inner->supportCoroutine();
        }
        return Coroutine::isCoroutine();
    }
}
