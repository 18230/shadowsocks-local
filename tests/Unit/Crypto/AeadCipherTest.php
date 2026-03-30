<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Crypto;

use PHPUnit\Framework\TestCase;
use SsLocal\Crypto\AeadDecryptor;
use SsLocal\Crypto\AeadEncryptor;

final class AeadCipherTest extends TestCase
{
    public function testEncryptorAndDecryptorRoundTripPayloads(): void
    {
        $encryptor = new AeadEncryptor('secret');
        $decryptor = new AeadDecryptor('secret');

        $payload = $encryptor->encryptChunk("hello\nworld");
        $messages = $decryptor->push($payload);

        self::assertCount(1, $messages);
        self::assertSame("hello\nworld", $messages[0]);
    }
}
