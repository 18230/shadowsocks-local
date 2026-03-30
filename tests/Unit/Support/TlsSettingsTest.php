<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SsLocal\Support\TlsSettings;

final class TlsSettingsTest extends TestCase
{
    public function testItBuildsGuzzleVerifyValue(): void
    {
        $settings = TlsSettings::fromArray([
            'verify_peer' => true,
            'verify_host' => true,
            'ca_file' => '/tmp/cacert.pem',
        ]);

        self::assertSame('/tmp/cacert.pem', $settings->guzzleVerify());
        self::assertTrue($settings->hasConfiguredCa());
    }
}
