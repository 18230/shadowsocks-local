<?php

declare(strict_types=1);

namespace SsLocal\Support;

use InvalidArgumentException;

final readonly class ProxyEndpoint
{
    public function __construct(
        public string $host,
        public int $port
    ) {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(sprintf('Invalid proxy port %d.', $port));
        }
    }

    public static function fromListenAddress(string $listen): self
    {
        $listen = trim($listen);
        if ($listen === '') {
            throw new InvalidArgumentException('The listen address cannot be empty.');
        }

        if (preg_match('/^\[(.+)]:(\d+)$/', $listen, $matches) === 1) {
            return new self($matches[1], (int) $matches[2]);
        }

        $position = strrpos($listen, ':');
        if ($position === false) {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s". Expected host:port.', $listen));
        }

        $host = substr($listen, 0, $position);
        $port = (int) substr($listen, $position + 1);

        if ($host === '') {
            throw new InvalidArgumentException(sprintf('Invalid listen address "%s".', $listen));
        }

        return new self($host, $port);
    }

    public function authority(): string
    {
        if (filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return sprintf('[%s]:%d', $this->host, $this->port);
        }

        return sprintf('%s:%d', $this->host, $this->port);
    }

    public function uri(bool $remoteDns = true): string
    {
        return sprintf('socks5%s://%s', $remoteDns ? 'h' : '', $this->authority());
    }
}
