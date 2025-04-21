<?php

namespace Tourze\WorkermanServerBundle\HTTP;

use Workerman\Psr7\Response;

/**
 * 这个文件响应是专门用来渲染静态文件的
 */
class WorkermanFileResponse extends Response
{
    private string $file;

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): void
    {
        $this->file = $file;
    }
}
