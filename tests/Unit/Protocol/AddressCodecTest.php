<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;
use SsLocal\Protocol\AddressCodec;

final class AddressCodecTest extends TestCase
{
    public function testItEncodesDomainAddressesForShadowsocks(): void
    {
        $encoded = AddressCodec::encodeShadowsocksAddress('example.com', 443);

        self::assertSame(0x03, ord($encoded[0]));
        self::assertSame('example.com', substr($encoded, 2, 11));
        self::assertSame(443, unpack('nport', substr($encoded, -2))['port']);
    }
}
