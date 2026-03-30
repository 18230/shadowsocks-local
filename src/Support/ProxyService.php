<?php

declare(strict_types=1);

namespace SsLocal\Support;

final readonly class ProxyService
{
    public function __construct(
        private ProxyEndpoint $endpoint,
        private TlsSettings $tlsSettings = new TlsSettings()
    ) {
    }

    public static function fromListenAddress(string $listen, ?TlsSettings $tlsSettings = null): self
    {
        return new self(
            ProxyEndpoint::fromListenAddress($listen),
            $tlsSettings ?? new TlsSettings()
        );
    }

    public function endpoint(): ProxyEndpoint
    {
        return $this->endpoint;
    }

    public function tlsSettings(): TlsSettings
    {
        return $this->tlsSettings;
    }

    public function proxyUri(bool $remoteDns = true): string
    {
        return $this->endpoint->uri($remoteDns);
    }

    /**
     * @param array<int, mixed> $overrides
     * @return array<int, mixed>
     */
    public function curlOptions(array $overrides = [], bool $remoteDns = true): array
    {
        $this->assertCurlAvailable();

        $options = [
            CURLOPT_PROXY => $this->endpoint->authority(),
            CURLOPT_PROXYTYPE => $remoteDns ? CURLPROXY_SOCKS5_HOSTNAME : CURLPROXY_SOCKS5,
        ] + $this->tlsSettings->curlOptions();

        foreach ($overrides as $key => $value) {
            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * @param \CurlHandle|resource $handle
     * @param array<int, mixed> $overrides
     */
    public function applyToCurlHandle($handle, array $overrides = [], bool $remoteDns = true): void
    {
        $this->assertCurlAvailable();
        curl_setopt_array($handle, $this->curlOptions($overrides, $remoteDns));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function guzzleOptions(array $overrides = [], bool $remoteDns = true): array
    {
        $proxyUri = $this->proxyUri($remoteDns);
        $options = [
            'proxy' => [
                'http' => $proxyUri,
                'https' => $proxyUri,
            ],
            'verify' => $this->tlsSettings->guzzleVerify(),
        ];

        return array_replace_recursive($options, $overrides);
    }

    private function assertCurlAvailable(): void
    {
        if (!function_exists('curl_setopt_array') || !defined('CURLOPT_PROXY') || !defined('CURLPROXY_SOCKS5_HOSTNAME')) {
            throw new \RuntimeException('ext-curl is required to use curl proxy helpers.');
        }
    }
}
