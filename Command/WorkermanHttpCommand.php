<?php

namespace WorkermanServerBundle\Command;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Psr7\ServerRequest;
use Workerman\Timer;
use Workerman\Worker;
use function Workerman\Psr7\response_to_string;

class WorkermanHttpCommand extends Command
{
    use CommonServiceTrait;

    protected static $defaultName = 'workerman:http';

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Connection
     */
    private $dbConnection;

    protected $httpFoundationFactory;

    protected $psrHttpFactory;

    public function __construct(
        Connection $dbConnection,
        string     $name = null
    )
    {
        parent::__construct($name);
        $this->kernel = new Kernel($_ENV['APP_ENV'], (bool)$_ENV['APP_DEBUG']);
        $this->dbConnection = $dbConnection;

        $this->httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $this->psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('启动Workerman-HTTP服务')
            ->addArgument('start')
            ->addArgument('stop')
            ->addArgument('restart')
            ->addArgument('status')
            ->addArgument('reload')
            ->addArgument('connections');
    }

    /**
     * 运行HTTP服务器
     *
     * @return void
     */
    protected function runHttpServer(InputInterface $input, OutputInterface $output): void
    {
        Http::requestClass(ServerRequest::class);
        $worker = new Worker('http://127.0.0.1:8080');
        $worker->name = 'symfony-http-server';
        // $worker->count = 4;
        $worker->onWorkerStart = function () use ($output) {
            $this->fileMonitorTimer();
            $this->resetServiceTimer($output);
        };
        $worker->onMessage = function (TcpConnection $connection, ServerRequest $psrRequest) use ($output) {
            $this->kernel->boot();

            // 将PSR规范的请求，转换为Symfony请求进行处理，最终再转换成PSR响应进行返回
            $symfonyRequest = $this->httpFoundationFactory->createRequest($psrRequest);
            $symfonyResponse = $this->kernel->handle($symfonyRequest);
            $psrResponse = $this->psrHttpFactory->createResponse($symfonyResponse);

            // 注意，下面的意思是直接格式化整个HTTP报文，做得很彻底喔
            $connection->send(response_to_string($psrResponse), true);

            // 这里做最终的环境变量收集和处理
            $this->kernel->terminate($symfonyRequest, $symfonyResponse);

            //设置单进程请求量达到额定时重启，防止代码写得不好产生OOM
            static $maxRequest;
            if (++$maxRequest > $this->maxRequest) {
                $output->writeln("max request {$maxRequest} reached and reload");
                // send SIGUSR1 signal to master process for reload
                posix_kill(posix_getppid(), SIGUSR1);
            }
        };
    }

    /**
     * 做服务保活
     *
     * @param OutputInterface $output
     * @return void
     */
    public function resetServiceTimer(OutputInterface $output)
    {
        // 定时连接mysql并执行一个命令，进行保活
        Timer::add(20, function () use ($output) {
            try {
                $this->dbConnection->executeQuery('SELECT 1');
            } catch (Throwable $e) {
                $output->writeln("dbal链接保活时发生错误：" . $e);
            }
        });
        // TODO 其实更加好的方法，是遍历所有服务，然后看哪个服务是支持reset的，直接reset
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Worker::$pidFile = "{$this->kernel->getBuildDir()}/workerman-http.pid";
        Worker::$logFile = "{$this->kernel->getBuildDir()}/workerman-http.log";

        // 运行worker
        $this->runHttpServer($input, $output);
        $this->runQueueConsumeServer($input, $output);
        Worker::runAll();

        return Command::SUCCESS;
    }
}
