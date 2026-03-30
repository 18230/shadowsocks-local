<?php

declare(strict_types=1);

namespace SsLocal\Config;

final readonly class NodeConfig
{
    public function __construct(
        public string $server,
        public int $port,
        public string $cipher,
        public string $password,
        public bool $udp = false,
        public ?string $name = null
    ) {
    }
}
