<?php

declare(strict_types=1);

namespace Mickeywaugh\WorkermanServerBundle\Exception;

final class WorkermanServerException extends \Exception
{
    public static function phpExecutableNotFound(): self
    {
        return new self('PHP executable not found');
    }
}
