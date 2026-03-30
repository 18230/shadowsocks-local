<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use SsLocal\Config\NodeConfigFactory;

final class NodeConfigFactoryTest extends TestCase
{
    public function testItBuildsNodeConfigFromArray(): void
    {
        $node = (new NodeConfigFactory())->fromArray([
            'server' => 'example.com',
            'port' => '8388',
            'cipher' => 'aes-256-gcm',
            'password' => 'secret',
            'udp' => true,
        ]);

        self::assertSame('example.com', $node->server);
        self::assertSame(8388, $node->port);
        self::assertSame('aes-256-gcm', $node->cipher);
        self::assertTrue($node->udp);
    }
}
