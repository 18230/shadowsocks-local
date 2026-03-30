<?php

declare(strict_types=1);

namespace SsLocal\Protocol;

use InvalidArgumentException;

final class AddressCodec
{
    public const ATYP_IPV4 = 0x01;
    public const ATYP_DOMAIN = 0x03;
    public const ATYP_IPV6 = 0x04;

    /**
     * @return array{cmd: int, host: string, port: int, consumed: int}|null
     */
    public static function tryParseSocks5Request(string $buffer): ?array
    {
        if (strlen($buffer) < 4) {
            return null;
        }

        $version = ord($buffer[0]);
        if ($version !== 0x05) {
            throw new InvalidArgumentException(sprintf('Unsupported SOCKS version %d.', $version));
        }

        $command = ord($buffer[1]);
        $atyp = ord($buffer[3]);
        $offset = 4;
        $host = null;

        switch ($atyp) {
            case self::ATYP_IPV4:
                if (strlen($buffer) < $offset + 4 + 2) {
                    return null;
                }
                $host = inet_ntop(substr($buffer, $offset, 4));
                $offset += 4;
                break;

            case self::ATYP_DOMAIN:
                if (strlen($buffer) < $offset + 1) {
                    return null;
                }
                $length = ord($buffer[$offset]);
                $offset += 1;
                if (strlen($buffer) < $offset + $length + 2) {
                    return null;
                }
                $host = substr($buffer, $offset, $length);
                $offset += $length;
                break;

            case self::ATYP_IPV6:
                if (strlen($buffer) < $offset + 16 + 2) {
                    return null;
                }
                $host = inet_ntop(substr($buffer, $offset, 16));
                $offset += 16;
                break;

            default:
                throw new InvalidArgumentException(sprintf('Unsupported SOCKS address type %d.', $atyp));
        }

        if ($host === false || $host === null || $host === '') {
            throw new InvalidArgumentException('Failed to decode the SOCKS destination host.');
        }

        $port = unpack('nport', substr($buffer, $offset, 2))['port'];
        $offset += 2;

        return [
            'cmd' => $command,
            'host' => $host,
            'port' => $port,
            'consumed' => $offset,
        ];
    }

    public static function encodeShadowsocksAddress(string $host, int $port): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $packed = inet_pton($host);
            if ($packed === false) {
                throw new InvalidArgumentException(sprintf('Invalid IPv4 address "%s".', $host));
            }

            return chr(self::ATYP_IPV4) . $packed . pack('n', $port);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($host);
            if ($packed === false) {
                throw new InvalidArgumentException(sprintf('Invalid IPv6 address "%s".', $host));
            }

            return chr(self::ATYP_IPV6) . $packed . pack('n', $port);
        }

        $length = strlen($host);
        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('Domain names must be between 1 and 255 bytes.');
        }

        return chr(self::ATYP_DOMAIN) . chr($length) . $host . pack('n', $port);
    }

    public static function buildReply(int $replyCode): string
    {
        return "\x05" . chr($replyCode) . "\x00\x01\x00\x00\x00\x00\x00\x00";
    }
}
