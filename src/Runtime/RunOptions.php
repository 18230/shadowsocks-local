<?php

declare(strict_types=1);

namespace SsLocal\Runtime;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

final readonly class RunOptions
{
    /**
     * @param list<string> $allowIps
     */
    public function __construct(
        public string $listenHost,
        public int $listenPort,
        public int $workerCount,
        public int $maxConnections,
        public array $allowIps,
        public int $connectTimeout,
        public int $connectRetries,
        public int $retryDelayMs,
        public int $idleTimeout,
        public int $maxSendBufferSize,
        public ?string $statusFile,
        public int $statusInterval,
        public ?string $logFile,
        public ?string $pidFile,
        public bool $daemonize
    ) {
    }

    /**
     * @param array<string, mixed> $baseConfig
     */
    public static function fromInput(InputInterface $input, array $baseConfig = []): self
    {
        $listen = self::resolveStringOption($input->getOption('listen'), $baseConfig['listen'] ?? '127.0.0.1:1080');
        [$listenHost, $listenPort] = self::parseListen($listen);

        $workerCount = max(1, self::resolveIntOption($input->getOption('worker-count'), $baseConfig['worker_count'] ?? 1));
        $maxConnections = max(0, self::resolveIntOption($input->getOption('max-connections'), $baseConfig['max_connections'] ?? 1024));
        $allowIps = self::resolveListOption($input->getOption('allow-ip'), $baseConfig['allow_ips'] ?? []);
        $connectTimeout = max(1, self::resolveIntOption($input->getOption('connect-timeout'), $baseConfig['connect_timeout'] ?? 10));
        $connectRetries = max(0, self::resolveIntOption($input->getOption('connect-retries'), $baseConfig['connect_retries'] ?? 1));
        $retryDelayMs = max(0, self::resolveIntOption($input->getOption('retry-delay-ms'), $baseConfig['retry_delay_ms'] ?? 250));
        $idleTimeout = max(30, self::resolveIntOption($input->getOption('idle-timeout'), $baseConfig['idle_timeout'] ?? 900));
        $maxSendBufferSize = max(65536, self::resolveIntOption($input->getOption('max-send-buffer'), $baseConfig['max_send_buffer'] ?? 4194304));
        $statusFile = self::resolveNullableStringOption($input->getOption('status-file'), $baseConfig['status_file'] ?? null);
        $statusInterval = max(5, self::resolveIntOption($input->getOption('status-interval'), $baseConfig['status_interval'] ?? 10));

        $logFile = self::resolveNullableStringOption($input->getOption('log-file'), $baseConfig['log_file'] ?? null);
        $pidFile = self::resolveNullableStringOption($input->getOption('pid-file'), $baseConfig['pid_file'] ?? null);

        return new self(
            listenHost: $listenHost,
            listenPort: $listenPort,
            workerCount: $workerCount,
            maxConnections: $maxConnections,
            allowIps: $allowIps,
            connectTimeout: $connectTimeout,
            connectRetries: $connectRetries,
            retryDelayMs: $retryDelayMs,
            idleTimeout: $idleTimeout,
            maxSendBufferSize: $maxSendBufferSize,
            statusFile: $statusFile,
            statusInterval: $statusInterval,
            logFile: $logFile,
            pidFile: $pidFile,
            daemonize: (bool) $input->getOption('daemon') || self::resolveBoolOption($baseConfig['daemon'] ?? $baseConfig['daemonize'] ?? false),
        );
    }

    private static function resolveStringOption(mixed $inputValue, mixed $baseValue): string
    {
        if (\is_string($inputValue) && $inputValue !== '') {
            return $inputValue;
        }

        if (\is_string($baseValue) && $baseValue !== '') {
            return $baseValue;
        }

        throw new InvalidArgumentException('A required string option is missing.');
    }

    private static function resolveNullableStringOption(mixed $inputValue, mixed $baseValue): ?string
    {
        if (\is_string($inputValue) && $inputValue !== '') {
            return $inputValue;
        }

        if (\is_string($baseValue) && trim($baseValue) !== '') {
            return trim($baseValue);
        }

        return null;
    }

    private static function resolveIntOption(mixed $inputValue, mixed $baseValue): int
    {
        if (\is_string($inputValue) && $inputValue !== '') {
            return (int) $inputValue;
        }

        if (\is_int($baseValue) || \is_float($baseValue) || (\is_string($baseValue) && $baseValue !== '')) {
            return (int) $baseValue;
        }

        return 0;
    }

    private static function resolveBoolOption(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (bool) $value;
        }

        if (\is_string($value) && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value === '1';
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function resolveListOption(mixed $inputValue, mixed $baseValue): array
    {
        $items = [];

        if (\is_array($baseValue)) {
            foreach ($baseValue as $value) {
                if (\is_string($value)) {
                    $items = array_merge($items, self::splitListValue($value));
                }
            }
        } elseif (\is_string($baseValue) && $baseValue !== '') {
            $items = array_merge($items, self::splitListValue($baseValue));
        }

        if (\is_array($inputValue) && $inputValue !== []) {
            $items = [];
            foreach ($inputValue as $value) {
                if (\is_string($value)) {
                    $items = array_merge($items, self::splitListValue($value));
                }
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $items), static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return list<string>
     */
    private static function splitListValue(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', $value);

        return $parts === false ? [] : array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function parseListen(string $listen): array
    {
        $position = strrpos($listen, ':');
        if ($position === false) {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s". Expected host:port.', $listen));
        }

        $host = substr($listen, 0, $position);
        $port = (int) substr($listen, $position + 1);

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s".', $listen));
        }

        return [$host, $port];
    }
}
