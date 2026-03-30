<?php

declare(strict_types=1);

namespace SsLocal\Support;

final readonly class TlsSettings
{
    public function __construct(
        public bool $verifyPeer = true,
        public bool $verifyHost = true,
        public ?string $caFile = null,
        public ?string $caPath = null
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            verifyPeer: (bool) ($config['verify_peer'] ?? true),
            verifyHost: (bool) ($config['verify_host'] ?? true),
            caFile: self::normalizeString($config['ca_file'] ?? null),
            caPath: self::normalizeString($config['ca_path'] ?? null),
        );
    }

    public static function fromIni(): self
    {
        return new self(
            verifyPeer: true,
            verifyHost: true,
            caFile: self::normalizeString(ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: null),
            caPath: self::normalizeString(ini_get('openssl.capath') ?: null),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function curlOptions(): array
    {
        self::assertCurlAvailable();

        $options = [
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $this->verifyPeer && $this->verifyHost ? 2 : 0,
        ];

        if ($this->caFile !== null) {
            $options[CURLOPT_CAINFO] = $this->caFile;
        }

        if ($this->caPath !== null && defined('CURLOPT_CAPATH')) {
            $options[CURLOPT_CAPATH] = $this->caPath;
        }

        return $options;
    }

    public function guzzleVerify(): bool|string
    {
        if (!$this->verifyPeer) {
            return false;
        }

        return $this->caFile ?? true;
    }

    public function hasConfiguredCa(): bool
    {
        return $this->caFile !== null || $this->caPath !== null;
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function assertCurlAvailable(): void
    {
        if (!function_exists('curl_setopt_array')) {
            throw new \RuntimeException('ext-curl is required to build curl options.');
        }
    }
}
