<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SsLocal\Support\ProxyEndpoint;

final class ProxyEndpointTest extends TestCase
{
    public function testItBuildsAuthorityAndUri(): void
    {
        $endpoint = ProxyEndpoint::fromListenAddress('127.0.0.1:1080');

        self::assertSame('127.0.0.1:1080', $endpoint->authority());
        self::assertSame('socks5h://127.0.0.1:1080', $endpoint->uri());
        self::assertSame('socks5://127.0.0.1:1080', $endpoint->uri(false));
    }
}
