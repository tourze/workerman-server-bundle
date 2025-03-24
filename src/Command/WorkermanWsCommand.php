<?php

namespace Tourze\WorkermanServerBundle\Command;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Workerman\Worker;

class WorkermanWsCommand extends Command
{

    protected static $defaultName = 'workerman:ws';

    private KernelInterface $kernel;

    protected HttpFoundationFactory $httpFoundationFactory;

    protected PsrHttpFactory $psrHttpFactory;

    public function __construct()
    {
        parent::__construct();
        //$this->kernel = new Kernel($_ENV['APP_ENV'], (bool)$_ENV['APP_DEBUG']);

        $this->httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('启动Workerman-WS服务')
            ->addArgument('start')
            ->addArgument('stop')
            ->addArgument('restart')
            ->addArgument('status')
            ->addArgument('reload')
            ->addArgument('connections');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Worker::$pidFile = "{$this->kernel->getBuildDir()}/workerman-ws.pid";
        Worker::$logFile = "{$this->kernel->getBuildDir()}/workerman-ws.log";
        throw new \Exception('未实现');

        // 运行worker
        // TODO 几个Worker
        Worker::runAll();

        return Command::SUCCESS;
    }
}
