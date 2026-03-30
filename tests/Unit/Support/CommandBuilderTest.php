<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SsLocal\Config\NodeConfig;
use SsLocal\Runtime\RunOptions;
use SsLocal\Support\Startup\CommandBuilder;

final class CommandBuilderTest extends TestCase
{
    public function testItBuildsCommandArguments(): void
    {
        $node = new NodeConfig(
            server: 'example.com',
            port: 18001,
            cipher: 'aes-256-gcm',
            password: 'secret',
            udp: false,
        );

        $options = new RunOptions(
            listenHost: '127.0.0.1',
            listenPort: 1080,
            workerCount: 2,
            maxConnections: 2048,
            allowIps: ['127.0.0.1', '10.0.0.0/8'],
            connectTimeout: 10,
            connectRetries: 2,
            retryDelayMs: 500,
            idleTimeout: 900,
            maxSendBufferSize: 4194304,
            statusFile: '/tmp/ss-local.status.json',
            statusInterval: 15,
            logFile: '/tmp/ss-local.log',
            pidFile: '/tmp/ss-local.pid',
            daemonize: true,
        );

        $builder = new CommandBuilder('/usr/bin/php', '/app/bin/ss-local');
        $command = $builder->build($node, $options, true);

        self::assertSame('/usr/bin/php', $command[0]);
        self::assertSame('/app/bin/ss-local', $command[1]);
        self::assertContains('--server=example.com', $command);
        self::assertContains('--worker-count=2', $command);
        self::assertContains('--max-connections=2048', $command);
        self::assertContains('--connect-retries=2', $command);
        self::assertContains('--retry-delay-ms=500', $command);
        self::assertContains('--status-file=/tmp/ss-local.status.json', $command);
        self::assertContains('--allow-ip=127.0.0.1', $command);
        self::assertContains('--verbose-log', $command);
    }
}
