<?php

declare(strict_types=1);

namespace SsLocal\Crypto;

final class KeyDeriver
{
    public static function deriveMasterKey(string $password): string
    {
        $result = '';
        $previous = '';

        while (strlen($result) < Aes256GcmMethod::KEY_LENGTH) {
            $previous = md5($previous . $password, true);
            $result .= $previous;
        }

        return substr($result, 0, Aes256GcmMethod::KEY_LENGTH);
    }

    public static function deriveSubkey(string $masterKey, string $salt): string
    {
        return hash_hkdf(
            algo: 'sha1',
            key: $masterKey,
            length: Aes256GcmMethod::KEY_LENGTH,
            info: Aes256GcmMethod::INFO,
            salt: $salt,
        );
    }
}
