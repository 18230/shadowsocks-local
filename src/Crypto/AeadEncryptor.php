<?php

declare(strict_types=1);

namespace SsLocal\Crypto;

use RuntimeException;

final class AeadEncryptor
{
    private readonly string $salt;

    private readonly string $subkey;

    private readonly NonceCounter $nonceCounter;

    private bool $saltSent = false;

    public function __construct(string $password)
    {
        $masterKey = KeyDeriver::deriveMasterKey($password);
        $this->salt = random_bytes(Aes256GcmMethod::SALT_LENGTH);
        $this->subkey = KeyDeriver::deriveSubkey($masterKey, $this->salt);
        $this->nonceCounter = new NonceCounter();
    }

    public function encryptChunk(string $plainText): string
    {
        $length = strlen($plainText);
        if ($length > Aes256GcmMethod::MAX_CHUNK_LENGTH) {
            throw new RuntimeException(sprintf('Chunk length %d exceeds the maximum %d bytes.', $length, Aes256GcmMethod::MAX_CHUNK_LENGTH));
        }

        $frame = '';
        if (!$this->saltSent) {
            $frame .= $this->salt;
            $this->saltSent = true;
        }

        $frame .= $this->seal(pack('n', $length));
        $frame .= $this->seal($plainText);

        return $frame;
    }

    private function seal(string $plainText): string
    {
        $nonce = $this->nonceCounter->next();
        $tag = '';
        $cipherText = openssl_encrypt(
            data: $plainText,
            cipher_algo: Aes256GcmMethod::OPENSSL_NAME,
            passphrase: $this->subkey,
            options: OPENSSL_RAW_DATA,
            iv: $nonce,
            tag: $tag,
            aad: '',
            tag_length: Aes256GcmMethod::TAG_LENGTH,
        );

        if ($cipherText === false) {
            throw new RuntimeException('OpenSSL failed to encrypt the Shadowsocks frame.');
        }

        return $cipherText . $tag;
    }
}
