<?php

declare(strict_types=1);

namespace SsLocal\Crypto;

final class Aes256GcmMethod
{
    public const KEY_LENGTH = 32;
    public const SALT_LENGTH = 32;
    public const NONCE_LENGTH = 12;
    public const TAG_LENGTH = 16;
    public const MAX_CHUNK_LENGTH = 0x3FFF;
    public const INFO = 'ss-subkey';
    public const OPENSSL_NAME = 'aes-256-gcm';
}
