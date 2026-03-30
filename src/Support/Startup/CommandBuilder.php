<?php

declare(strict_types=1);

namespace SsLocal\Support\Startup;

use SsLocal\Config\NodeConfig;
use SsLocal\Runtime\RunOptions;
use SsLocal\Runtime\Platform;

final readonly class CommandBuilder
{
    public function __construct(
        private string $phpBinary = 'php',
        private ?string $binaryPath = null
    ) {
    }

    /**
     * @return list<string>
     */
    public function build(NodeConfig $node, RunOptions $options, bool $verboseLog = false): array
    {
        $binaryPath = $this->binaryPath ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'ss-local';

        $command = [
            $this->phpBinary,
            $binaryPath,
            sprintf('--server=%s', $node->server),
            sprintf('--port=%d', $node->port),
            sprintf('--cipher=%s', $node->cipher),
            sprintf('--password=%s', $node->password),
            sprintf('--udp=%d', $node->udp ? 1 : 0),
            sprintf('--listen=%s:%d', $options->listenHost, $options->listenPort),
            sprintf('--worker-count=%d', $options->workerCount),
            sprintf('--max-connections=%d', $options->maxConnections),
            sprintf('--connect-timeout=%d', $options->connectTimeout),
            sprintf('--connect-retries=%d', $options->connectRetries),
            sprintf('--retry-delay-ms=%d', $options->retryDelayMs),
            sprintf('--idle-timeout=%d', $options->idleTimeout),
            sprintf('--max-send-buffer=%d', $options->maxSendBufferSize),
            sprintf('--status-interval=%d', $options->statusInterval),
        ];

        foreach ($options->allowIps as $allowIp) {
            $command[] = sprintf('--allow-ip=%s', $allowIp);
        }

        if ($options->statusFile !== null) {
            $command[] = sprintf('--status-file=%s', $options->statusFile);
        }

        if ($options->logFile !== null) {
            $command[] = sprintf('--log-file=%s', $options->logFile);
        }

        if ($options->pidFile !== null) {
            $command[] = sprintf('--pid-file=%s', $options->pidFile);
        }

        if ($options->daemonize && !Platform::isWindows()) {
            $command[] = '--daemon';
        }

        if ($verboseLog) {
            $command[] = '--verbose-log';
        }

        return $command;
    }

    public function toShellCommand(NodeConfig $node, RunOptions $options, bool $verboseLog = false, ?bool $windows = null): string
    {
        $windows ??= Platform::isWindows();

        return implode(' ', array_map(
            fn (string $argument): string => $this->quote($argument, $windows),
            $this->build($node, $options, $verboseLog)
        ));
    }

    private function quote(string $argument, bool $windows): string
    {
        if ($argument === '') {
            return $windows ? '""' : "''";
        }

        if ($windows) {
            return '"' . str_replace('"', '""', $argument) . '"';
        }

        return "'" . str_replace("'", "'\"'\"'", $argument) . "'";
    }
}
