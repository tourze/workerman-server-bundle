<?php

namespace Tourze\WorkermanServerBundle\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Timer;
use Workerman\Worker;

trait CommonServiceTrait
{
    public $maxRequest = 2000;

    /**
     * 运行CLI脚本
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function runQueueConsumeServer(InputInterface $input, OutputInterface $output): void
    {
        $worker = new Worker;
        $worker->name = 'symfony-queue-consume';
        //$worker->count = 4;

        try {
            $command = $this->getApplication()->find('messenger:consume');
        } catch (CommandNotFoundException $e) {
            $output->writeln('未安装 symfony/messager，不用自动启动消费进程');
            return;
        }
        $worker->onWorkerStart = function () use ($command, $output) {
            $arguments = [
                '-vvv' => true,
            ];

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            $output->writeln('自动启动队列消费者进程');
        };
    }

    /**
     * 定时检测文件变更
     *
     * @return void
     */
    public function fileMonitorTimer()
    {
        Timer::add(1, function ($monitor_dir) {
            // copy from https://github.com/walkor/workerman-filemonitor/blob/master/Applications/FileMonitor/start.php
            global $last_mtime;
            if (!$last_mtime) {
                $last_mtime = time();
                return;
            }

            // recursive traversal directory
            $dir_iterator = new RecursiveDirectoryIterator($monitor_dir);
            $iterator = new RecursiveIteratorIterator($dir_iterator);
            foreach ($iterator as $file) {
                // only check php files
                if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                    continue;
                }
                // check mtime
                if ($last_mtime < $file->getMTime()) {
                    echo $file . " update and reload\n";
                    // send SIGUSR1 signal to master process for reload
                    posix_kill(posix_getppid(), SIGUSR1);
                    $last_mtime = $file->getMTime();
                    break;
                }
            }
        }, [
            // 默认只监听这几个目录
            "{$this->kernel->getProjectDir()}/config",
            "{$this->kernel->getProjectDir()}/src",
            "{$this->kernel->getProjectDir()}/templates",
            "{$this->kernel->getProjectDir()}/translations",
        ]);
    }
}
