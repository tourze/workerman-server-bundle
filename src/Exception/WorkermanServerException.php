<?php

declare(strict_types=1);

namespace Tourze\WorkermanServerBundle\Exception;

final class WorkermanServerException extends \Exception
{
    public static function phpExecutableNotFound(): self
    {
        return new self('PHP executable not found');
    }
}
