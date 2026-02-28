<?php

namespace Mickeywaugh\WorkermanServerBundle\Command;

use Fidry\CpuCoreCounter\CpuCoreCounter;
use Fidry\CpuCoreCounter\Finder\DummyCpuCoreFinder;
use Fidry\CpuCoreCounter\Finder\FinderRegistry;
use League\MimeTypeDetection\MimeTypeDetector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\Workerman\ProcessWorker\ProcessWorker;
use Mickeywaugh\WorkermanServerBundle\Exception\WorkermanServerException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

#[AsCommand(
    name: self::NAME,
    description: '启动Workerman-HTTP服务'
)]
#[Autoconfigure(public: true)]
final class WorkermanHttpCommand extends Command
{
    public const NAME = 'workerman:http';

    protected HttpFoundationFactory $httpFoundationFactory;

    protected PsrHttpFactory $psrHttpFactory;

    private int $maxRequest = 10000;

    private CpuCoreCounter $coreCounter;

    public function __construct(
        private readonly KernelInterface $kernel,
        #[Autowire(param: 'kernel.project_dir')] private readonly string $projectDir,
        #[Autowire(service: 'workerman-server.mime-detector')] private readonly MimeTypeDetector $mimeTypeDetector,
    ) {
        parent::__construct();
        // $this->kernel = new Kernel($_ENV['APP_ENV'], (bool)$_ENV['APP_DEBUG']);
        // $this->dbConnection = $dbConnection;

        $this->httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        // 使用 fidry/cpu-core-counter 获取 CPU 核心数
        $this->coreCounter = new CpuCoreCounter([
            ...FinderRegistry::getDefaultLogicalFinders(),
            new DummyCpuCoreFinder(1), // 默认值
        ]);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('start')
            ->addArgument('stop')
            ->addArgument('restart')
            ->addArgument('status')
            ->addArgument('reload')
            ->addArgument('connections')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to listen on', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen on', 8080)
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run in daemon mode')
        ;
    }

    /**
     * 运行HTTP服务器
     */
    protected function runHttpServer(InputInterface $input, OutputInterface $output): void
    {
        $host = $_ENV['WORKERMAN_SERVER_HOST'] ?? $input->getOption('host');
        $port = (int) $_ENV['WORKERMAN_SERVER_PORT'] ?? $input->getOption('port');
        $isDaemon = $input->getOption('daemon');
        $worker = new Worker(sprintf('http://%s:%d', $host, $port));
        $worker->name = 'symfony-http-server';
        $envCount = $_ENV['WORKERMAN_SERVER_PROCESS_COUNT'] ?? null;
        $worker->count = is_numeric($envCount) ? (int) $envCount : max(2, (int) ($this->coreCounter->getCount() / 2));
        if ($isDaemon) {
            Worker::$daemonize = true;
        }
        $worker->onWorkerStart = function () use ($output): void {
            $this->resetServiceTimer($output);
            //            if ($this->kernel->isDebug()) {
            //                $this->createFileMonitor();
            //            }
        };

        $worker->onMessage = function (TcpConnection $connection, Request $request) use ($output): void {
            if ($this->handleStaticFile($connection, $request)) {
                return;
            }

            $this->handleDynamicRequest($connection, $request, $output);
        };
    }

    private function handleStaticFile(TcpConnection $connection, Request $request): bool
    {
        $checkFile = "{$this->projectDir}/public{$request->path()}";
        $checkFile = str_replace('..', '/', $checkFile);

        if (!is_file($checkFile)) {
            return false;
        }

        $code = file_get_contents($checkFile);
        if (false === $code) {
            $code = '';
        }
        $mtime = filemtime($checkFile);
        if (false === $mtime) {
            $mtime = time();
        }

        $workermanResponse = new Http\Response(
            200,
            [
                'Content-Type' => $this->mimeTypeDetector->detectMimeType($checkFile, $code),
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            ],
            $code
        );

        $connection->send($workermanResponse);

        return true;
    }

    private function handleDynamicRequest(TcpConnection $connection, Request $request, OutputInterface $output): void
    {
        $this->clearOutputBuffers();

        $symfonyRequest = null;
        $symfonyResponse = null;

        try {
            $symfonyRequest = $this->createSymfonyRequest($request);

            ob_start();
            $symfonyResponse = $this->kernel->handle($symfonyRequest);
            ob_get_clean();

            $this->sendSymfonyResponse($connection, $symfonyResponse);
        } catch (\Throwable $e) {
            $this->clearOutputBuffers();
            $connection->send("HTTP/1.1 500 Internal Server Error\r\n\r\nError: " . $e->getMessage());
        }

        $this->terminateKernel($symfonyRequest, $symfonyResponse);
        $this->resetServices($output);
        $this->handleRequestLimit($output);
    }

    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function terminateKernel(?\Symfony\Component\HttpFoundation\Request $request, ?Response $response): void
    {
        if ($this->kernel instanceof TerminableInterface && null !== $request && null !== $response) {
            $this->kernel->terminate($request, $response);
        }
    }

    private function handleRequestLimit(OutputInterface $output): void
    {
        /** @var int $requestCount */
        static $requestCount = 0;
        ++$requestCount;
        if ($requestCount > $this->maxRequest) {
            $output->writeln("max request {$requestCount} reached and reload");
            posix_kill(posix_getppid(), SIGUSR1);
        }
    }

    private function createSymfonyRequest(Request $request): \Symfony\Component\HttpFoundation\Request
    {
        $query = $request->get();
        $post = $request->post();
        $files = $request->file() ?? [];
        $cookies = $request->cookie() ?? [];
        $server = [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'SERVER_NAME' => $request->host(),
            'QUERY_STRING' => $request->queryString(),
            'PHP_SAPI' => 'cli-server',
        ];

        $headers = $request->header();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name)) {
                    $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
                }
            }
        }

        return new \Symfony\Component\HttpFoundation\Request(
            is_array($query) ? $query : [],
            is_array($post) ? $post : [],
            [],
            is_array($cookies) ? $cookies : [],
            $files,
            $server,
            $request->rawBody()
        );
    }

    private function sendSymfonyResponse(TcpConnection $connection, Response $response): void
    {
        // 使用Workerman的HTTP响应对象来避免重复头问题
        $workermanResponse = new Http\Response(
            $response->getStatusCode(),
            $response->headers->all(),
            false !== $response->getContent() ? $response->getContent() : ''
        );

        $connection->send($workermanResponse);
    }

    /**
     * 做服务保活
     */
    public function resetServiceTimer(OutputInterface $output): void
    {
        // Worker启动时的初始化逻辑
    }

    private function resetServices(OutputInterface $output): void
    {
        // 强制垃圾回收，防止内存泄露
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // 重置关键服务
        $container = $this->kernel->getContainer();

        $resetableServices = [
            'session.factory',
            'doctrine.dbal.default_connection',
            'doctrine.orm.default_entity_manager',
        ];

        foreach ($resetableServices as $serviceId) {
            if ($container->has($serviceId)) {
                try {
                    $service = $container->get($serviceId);
                    if ($service instanceof ResetInterface) {
                        $service->reset();
                    }
                } catch (\Throwable $e) {
                    // 忽略重置失败
                }
            }
        }
    }

    private function createMessenger(KernelInterface $kernel): void
    {
        $finder = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        if (false === $phpExecutable) {
            throw WorkermanServerException::phpExecutableNotFound();
        }
        $phpExecutable = escapeshellarg($phpExecutable);

        // 启动 messenger
        $worker = new ProcessWorker("{$phpExecutable} {$kernel->getProjectDir()}/bin/console messenger:consume async --memory-limit=512M --time-limit=600 --no-debug");
        $worker->name = 'AsyncMessenger';
        $worker->onProcessStart = function (): void {
            $_ENV['WORKER_NAME'] = 'async-messenger';
        };
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Worker::$pidFile = "{$this->kernel->getBuildDir()}/workerman-http.pid";
        Worker::$logFile = "{$this->kernel->getBuildDir()}/workerman-http.log";

        // 运行worker
        $this->runHttpServer($input, $output);
        $this->createMessenger($this->kernel);

        Worker::runAll();

        return Command::SUCCESS;
    }
}
