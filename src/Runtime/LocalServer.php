<?php

declare(strict_types=1);

namespace SsLocal\Runtime;

use Psr\Log\LoggerInterface;
use SsLocal\Config\NodeConfig;
use SsLocal\Session\Socks5Session;
use SplObjectStorage;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

final class LocalServer
{
    /**
     * @var SplObjectStorage<TcpConnection, Socks5Session>
     */
    private SplObjectStorage $sessions;

    private readonly IpAccessList $accessList;

    private readonly ServerStats $stats;

    private ?StatusFileWriter $statusWriter = null;

    private int $statusTimerId = 0;

    public function __construct(
        private readonly NodeConfig $node,
        private readonly RunOptions $options,
        private readonly LoggerInterface $logger
    ) {
        $this->sessions = new SplObjectStorage();
        $this->accessList = IpAccessList::fromStrings($this->options->allowIps);
        $this->stats = new ServerStats();
    }

    public function run(): void
    {
        $this->installErrorHandlers();
        $this->configureWorkerman();
        $this->normalizeWorkermanArguments();

        $listen = sprintf('tcp://%s:%d', $this->options->listenHost, $this->options->listenPort);
        $worker = new Worker($listen);
        $worker->name = 'ss-local';
        $worker->count = Platform::isWindows() ? 1 : $this->options->workerCount;

        $worker->onWorkerStart = function (Worker $worker) use ($listen): void {
            $this->startStatusReporting($worker);
            $this->logger->info('SOCKS5 frontend started.', [
                'listen' => $listen,
                'server' => $this->node->server,
                'port' => $this->node->port,
                'cipher' => $this->node->cipher,
                'pid' => getmypid(),
                'worker_id' => isset($worker->id) ? (int) $worker->id : null,
                'max_connections' => $this->options->maxConnections,
                'allow_ips' => $this->accessList->entries(),
            ]);
        };

        $worker->onConnect = function (TcpConnection $connection): void {
            $remoteIp = $connection->getRemoteIp();

            if (!$this->accessList->allows($remoteIp)) {
                $this->stats->recordAccessDenied();
                $this->logger->warning('Rejected local client outside allow list.', [
                    'remote_address' => $remoteIp . ':' . $connection->getRemotePort(),
                ]);
                $connection->close();

                return;
            }

            if ($this->sessions->count() >= $this->options->maxConnections) {
                $this->stats->recordConnectionLimitRejected();
                $this->logger->warning('Rejected local client because the connection limit was reached.', [
                    'remote_address' => $remoteIp . ':' . $connection->getRemotePort(),
                    'max_connections' => $this->options->maxConnections,
                ]);
                $connection->close();

                return;
            }

            $connection->maxSendBufferSize = $this->options->maxSendBufferSize;
            $session = new Socks5Session($connection, $this->node, $this->options, $this->logger, $this->stats);
            $this->sessions->attach($connection, $session);
            $this->stats->recordAcceptedConnection();
        };

        $worker->onMessage = function (TcpConnection $connection, string $data): void {
            if (!$this->sessions->contains($connection)) {
                $connection->close();

                return;
            }

            $this->sessions[$connection]->handleClientData($data);
        };

        $worker->onClose = function (TcpConnection $connection): void {
            if (!$this->sessions->contains($connection)) {
                return;
            }

            $session = $this->sessions[$connection];
            $this->sessions->detach($connection);
            $this->stats->recordConnectionClosed();
            $session->closeClientSide();
        };

        $worker->onError = function (TcpConnection $connection, int $code, string $message): void {
            $this->logger->warning('Local client connection raised an error.', [
                'code' => $code,
                'message' => $message,
                'remote_address' => $connection->getRemoteIp() . ':' . $connection->getRemotePort(),
            ]);
        };

        $worker->onWorkerStop = function (Worker $worker): void {
            $this->flushStatus($worker);
            if ($this->statusTimerId !== 0) {
                Timer::del($this->statusTimerId);
                $this->statusTimerId = 0;
            }
        };

        Worker::runAll();
    }

    private function configureWorkerman(): void
    {
        if ($this->options->logFile !== null) {
            $this->ensureParentDirectory($this->options->logFile);
            Worker::$logFile = $this->options->logFile;
            Worker::$stdoutFile = $this->options->logFile;
        }

        if ($this->options->pidFile !== null && !Platform::isWindows()) {
            $this->ensureParentDirectory($this->options->pidFile);
            Worker::$pidFile = $this->options->pidFile;
        }

        if ($this->options->daemonize && !Platform::isWindows()) {
            Worker::$daemonize = true;
        }
    }

    private function startStatusReporting(Worker $worker): void
    {
        if ($this->options->statusFile === null) {
            return;
        }

        $this->ensureParentDirectory($this->options->statusFile);
        $this->statusWriter = new StatusFileWriter($this->options->statusFile);
        $this->flushStatus($worker);
        $this->statusTimerId = Timer::add($this->options->statusInterval, function () use ($worker): void {
            $this->flushStatus($worker);
        });
    }

    private function flushStatus(Worker $worker): void
    {
        if ($this->statusWriter === null) {
            return;
        }

        $workerId = $worker->count > 1 && isset($worker->id) ? (int) $worker->id : null;

        try {
            $path = $this->statusWriter->write([
                'updated_at' => date(DATE_ATOM),
                'pid' => getmypid(),
                'worker_id' => $workerId,
                'listen' => sprintf('%s:%d', $this->options->listenHost, $this->options->listenPort),
                'node' => [
                    'name' => $this->node->name,
                    'server' => $this->node->server,
                    'port' => $this->node->port,
                    'cipher' => $this->node->cipher,
                ],
                'runtime' => [
                    'worker_count' => $worker->count,
                    'max_connections' => $this->options->maxConnections,
                    'allow_ips' => $this->accessList->entries(),
                    'connect_timeout' => $this->options->connectTimeout,
                    'connect_retries' => $this->options->connectRetries,
                    'retry_delay_ms' => $this->options->retryDelayMs,
                    'idle_timeout' => $this->options->idleTimeout,
                    'max_send_buffer' => $this->options->maxSendBufferSize,
                ],
                'stats' => $this->stats->toArray(),
            ], $workerId);

            $this->logger->debug('Status snapshot written.', ['path' => $path]);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to write status snapshot.', [
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function installErrorHandlers(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            $this->logger->error('PHP runtime error.', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        set_exception_handler(function (\Throwable $throwable): void {
            $this->logger->critical('Unhandled exception.', [
                'type' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            $this->logger->critical('Fatal shutdown error.', $error);
        });
    }

    private function normalizeWorkermanArguments(): void
    {
        if (Platform::isWindows()) {
            return;
        }

        global $argv, $argc;

        $script = 'bin/ss-local';
        if (\is_array($argv) && isset($argv[0]) && \is_string($argv[0]) && $argv[0] !== '') {
            $script = $argv[0];
        }

        // Workerman expects a process control command such as "start" on Unix.
        $argv = [$script, 'start'];
        $argc = \count($argv);
    }

    private function ensureParentDirectory(string $path): void
    {
        if (!stream_is_local($path)) {
            return;
        }

        $directory = dirname($path);
        if ($directory === '' || $directory === '.' || is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }
}
