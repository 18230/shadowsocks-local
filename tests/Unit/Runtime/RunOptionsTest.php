<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use SsLocal\Runtime\RunOptions;
use Symfony\Component\Console\Input\InputInterface;

final class RunOptionsTest extends TestCase
{
    public function testItBuildsOptionsFromBaseConfigWhenCliValuesAreMissing(): void
    {
        $input = $this->stubInput([
            'listen' => null,
            'worker-count' => null,
            'max-connections' => null,
            'allow-ip' => [],
            'connect-timeout' => null,
            'connect-retries' => null,
            'retry-delay-ms' => null,
            'idle-timeout' => null,
            'max-send-buffer' => null,
            'status-file' => null,
            'status-interval' => null,
            'log-file' => null,
            'pid-file' => null,
            'daemon' => false,
        ]);

        $options = RunOptions::fromInput($input, [
            'listen' => '127.0.0.1:2080',
            'worker_count' => 2,
            'max_connections' => 512,
            'allow_ips' => ['127.0.0.1', '::1'],
            'connect_timeout' => 15,
            'connect_retries' => 2,
            'retry_delay_ms' => 750,
            'idle_timeout' => 1200,
            'max_send_buffer' => 2097152,
            'status_file' => 'runtime/status.json',
            'status_interval' => 30,
            'log_file' => 'runtime/ss-local.log',
            'pid_file' => 'runtime/ss-local.pid',
            'daemon' => true,
        ]);

        self::assertSame('127.0.0.1', $options->listenHost);
        self::assertSame(2080, $options->listenPort);
        self::assertSame(2, $options->workerCount);
        self::assertSame(512, $options->maxConnections);
        self::assertSame(['127.0.0.1', '::1'], $options->allowIps);
        self::assertSame(15, $options->connectTimeout);
        self::assertSame(2, $options->connectRetries);
        self::assertSame(750, $options->retryDelayMs);
        self::assertSame(1200, $options->idleTimeout);
        self::assertSame(2097152, $options->maxSendBufferSize);
        self::assertSame('runtime/status.json', $options->statusFile);
        self::assertSame(30, $options->statusInterval);
        self::assertTrue($options->daemonize);
    }

    public function testCliValuesOverrideBaseConfig(): void
    {
        $input = $this->stubInput([
            'listen' => '0.0.0.0:1081',
            'worker-count' => '3',
            'max-connections' => '2048',
            'allow-ip' => ['127.0.0.1, 10.0.0.0/8'],
            'connect-timeout' => '20',
            'connect-retries' => '4',
            'retry-delay-ms' => '500',
            'idle-timeout' => '1800',
            'max-send-buffer' => '8388608',
            'status-file' => '/tmp/status.json',
            'status-interval' => '25',
            'log-file' => '/tmp/ss-local.log',
            'pid-file' => '/tmp/ss-local.pid',
            'daemon' => true,
        ]);

        $options = RunOptions::fromInput($input, [
            'listen' => '127.0.0.1:1080',
            'allow_ips' => ['::1'],
        ]);

        self::assertSame('0.0.0.0', $options->listenHost);
        self::assertSame(1081, $options->listenPort);
        self::assertSame(3, $options->workerCount);
        self::assertSame(2048, $options->maxConnections);
        self::assertSame(['127.0.0.1', '10.0.0.0/8'], $options->allowIps);
        self::assertSame(20, $options->connectTimeout);
        self::assertSame(4, $options->connectRetries);
        self::assertSame(500, $options->retryDelayMs);
        self::assertSame(1800, $options->idleTimeout);
        self::assertSame(8388608, $options->maxSendBufferSize);
        self::assertSame('/tmp/status.json', $options->statusFile);
        self::assertSame(25, $options->statusInterval);
        self::assertSame('/tmp/ss-local.log', $options->logFile);
        self::assertSame('/tmp/ss-local.pid', $options->pidFile);
        self::assertTrue($options->daemonize);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function stubInput(array $options): InputInterface
    {
        $input = $this->createStub(InputInterface::class);
        $input
            ->method('getOption')
            ->willReturnCallback(static fn (string $name): mixed => $options[$name] ?? null);

        return $input;
    }
}
