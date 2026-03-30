<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SsLocal\Support\ProxyEndpoint;
use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

final class ProxyServiceTest extends TestCase
{
    public function testItBuildsCurlAndGuzzleOptions(): void
    {
        $service = new ProxyService(
            new ProxyEndpoint('127.0.0.1', 1080),
            new TlsSettings(verifyPeer: false, verifyHost: false)
        );

        $curlOptions = $service->curlOptions();
        self::assertSame('127.0.0.1:1080', $curlOptions[CURLOPT_PROXY]);
        self::assertSame(CURLPROXY_SOCKS5_HOSTNAME, $curlOptions[CURLOPT_PROXYTYPE]);
        self::assertFalse($curlOptions[CURLOPT_SSL_VERIFYPEER]);

        $guzzleOptions = $service->guzzleOptions();
        self::assertSame('socks5h://127.0.0.1:1080', $guzzleOptions['proxy']['https']);
        self::assertFalse($guzzleOptions['verify']);
    }
}
