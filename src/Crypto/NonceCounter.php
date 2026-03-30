<?php

declare(strict_types=1);

namespace SsLocal\Crypto;

final class NonceCounter
{
    private string $nonce;

    public function __construct()
    {
        $this->nonce = str_repeat("\x00", Aes256GcmMethod::NONCE_LENGTH);
    }

    public function next(): string
    {
        $current = $this->nonce;

        for ($index = 0; $index < Aes256GcmMethod::NONCE_LENGTH; $index++) {
            $value = ord($this->nonce[$index]);
            $value = ($value + 1) & 0xFF;
            $this->nonce[$index] = chr($value);

            if ($value !== 0) {
                break;
            }
        }

        return $current;
    }
}
