<?php

declare(strict_types=1);

namespace SsLocal\Crypto;

use RuntimeException;

final class AeadDecryptor
{
    private readonly string $masterKey;

    private string $buffer = '';

    private ?string $subkey = null;

    private readonly NonceCounter $nonceCounter;

    private ?int $expectedLength = null;

    public function __construct(string $password)
    {
        $this->masterKey = KeyDeriver::deriveMasterKey($password);
        $this->nonceCounter = new NonceCounter();
    }

    /**
     * @return list<string>
     */
    public function push(string $data): array
    {
        $this->buffer .= $data;
        $messages = [];

        if ($this->subkey === null) {
            if (strlen($this->buffer) < Aes256GcmMethod::SALT_LENGTH) {
                return [];
            }

            $salt = substr($this->buffer, 0, Aes256GcmMethod::SALT_LENGTH);
            $this->buffer = substr($this->buffer, Aes256GcmMethod::SALT_LENGTH);
            $this->subkey = KeyDeriver::deriveSubkey($this->masterKey, $salt);
        }

        while (true) {
            if ($this->expectedLength === null) {
                $lengthFrameSize = 2 + Aes256GcmMethod::TAG_LENGTH;
                if (strlen($this->buffer) < $lengthFrameSize) {
                    break;
                }

                $frame = substr($this->buffer, 0, $lengthFrameSize);
                $this->buffer = substr($this->buffer, $lengthFrameSize);
                $decryptedLength = $this->open($frame);
                if (strlen($decryptedLength) !== 2) {
                    throw new RuntimeException('Invalid decrypted length frame from the Shadowsocks server.');
                }

                $this->expectedLength = unpack('nlength', $decryptedLength)['length'];
                if ($this->expectedLength < 0 || $this->expectedLength > Aes256GcmMethod::MAX_CHUNK_LENGTH) {
                    throw new RuntimeException(sprintf('Invalid chunk length %d from the Shadowsocks server.', $this->expectedLength));
                }
            }

            $payloadFrameSize = $this->expectedLength + Aes256GcmMethod::TAG_LENGTH;
            if (strlen($this->buffer) < $payloadFrameSize) {
                break;
            }

            $frame = substr($this->buffer, 0, $payloadFrameSize);
            $this->buffer = substr($this->buffer, $payloadFrameSize);
            $messages[] = $this->open($frame);
            $this->expectedLength = null;
        }

        return $messages;
    }

    private function open(string $frame): string
    {
        if ($this->subkey === null) {
            throw new RuntimeException('The decryptor subkey is not initialized.');
        }

        $cipherText = substr($frame, 0, -Aes256GcmMethod::TAG_LENGTH);
        $tag = substr($frame, -Aes256GcmMethod::TAG_LENGTH);
        $nonce = $this->nonceCounter->next();

        $plainText = openssl_decrypt(
            data: $cipherText,
            cipher_algo: Aes256GcmMethod::OPENSSL_NAME,
            passphrase: $this->subkey,
            options: OPENSSL_RAW_DATA,
            iv: $nonce,
            tag: $tag,
            aad: '',
        );

        if ($plainText === false) {
            throw new RuntimeException('Failed to decrypt a Shadowsocks AEAD frame.');
        }

        return $plainText;
    }
}
