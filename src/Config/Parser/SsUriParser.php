<?php

declare(strict_types=1);

namespace SsLocal\Config\Parser;

use InvalidArgumentException;

final class SsUriParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $uri): array
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid ss:// URI.');
        }

        if (($parts['scheme'] ?? null) !== 'ss') {
            throw new InvalidArgumentException('Unsupported URI scheme.');
        }

        parse_str($parts['query'] ?? '', $query);
        if (isset($query['plugin'])) {
            throw new InvalidArgumentException('SIP003 plugins are not implemented in the current release.');
        }

        $fragment = isset($parts['fragment']) ? urldecode((string) $parts['fragment']) : null;

        if (isset($parts['user'], $parts['host'], $parts['port'])) {
            [$cipher, $password] = $this->splitCredentials($this->decodeBase64((string) $parts['user']));

            return [
                'type' => 'ss',
                'name' => $fragment,
                'server' => (string) $parts['host'],
                'port' => (int) $parts['port'],
                'cipher' => $cipher,
                'password' => $password,
                'udp' => false,
            ];
        }

        $authority = substr($uri, 5);
        $authority = preg_split('/[?#]/', $authority)[0] ?? '';
        $decoded = $this->decodeBase64($authority);

        if (!str_contains($decoded, '@')) {
            throw new InvalidArgumentException('The ss:// URI is missing host information.');
        }

        [$credentials, $endpoint] = explode('@', $decoded, 2);
        [$cipher, $password] = $this->splitCredentials($credentials);
        [$host, $port] = $this->splitHostPort($endpoint);

        return [
            'type' => 'ss',
            'name' => $fragment,
            'server' => $host,
            'port' => $port,
            'cipher' => $cipher,
            'password' => $password,
            'udp' => false,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitCredentials(string $credentials): array
    {
        $parts = explode(':', $credentials, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('The ss:// URI must contain method:password credentials.');
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function splitHostPort(string $endpoint): array
    {
        $position = strrpos($endpoint, ':');
        if ($position === false) {
            throw new InvalidArgumentException('The ss:// URI endpoint is missing the port.');
        }

        $host = substr($endpoint, 0, $position);
        $port = (int) substr($endpoint, $position + 1);

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('The ss:// URI endpoint is invalid.');
        }

        return [$host, $port];
    }

    private function decodeBase64(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Failed to decode base64 content from the ss:// URI.');
        }

        return $decoded;
    }
}
