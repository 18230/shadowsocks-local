<?php

declare(strict_types=1);

namespace SsLocal\Config;

final readonly class InputConfig
{
    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $runtime
     */
    public function __construct(
        public array $node,
        public array $runtime
    ) {
    }
}
