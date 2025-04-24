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
        return $this->inner->getId();

        if (!Worker::isRunning()) {
            return $this->inner->getId();
        }
        return Coroutine::getCurrent()->id();
    }

    public function defer(callable $callback): void
    {
        $this->inner->defer($callback);
        return;

        if (!Worker::isRunning()) {
            $this->inner->defer($callback);
            return;
        }
        Coroutine::defer($callback);
    }

    public function supportCoroutine(): bool
    {
        return false;
        if (!Worker::isRunning()) {
            return $this->inner->supportCoroutine();
        }
        return Coroutine::isCoroutine();
    }

    public function reset(): void
    {
        $this->inner->reset();
    }
}
