<?php

declare(strict_types=1);

namespace SsLocal\Config;

use InvalidArgumentException;
use SsLocal\Config\Parser\ConfigFileParser;
use SsLocal\Config\Parser\InlineNodeParser;
use Symfony\Component\Console\Input\InputInterface;

final class NodeConfigFactory
{
    /**
     * @param array<string, mixed> $baseConfig
     */
    public function fromInput(InputInterface $input, array $baseConfig = []): NodeConfig
    {
        $config = $baseConfig;

        $inlineNode = $input->getOption('node');
        if (\is_string($inlineNode) && $inlineNode !== '') {
            $config = array_replace($config, (new InlineNodeParser())->parse($inlineNode));
        }

        foreach (['server', 'port', 'cipher', 'password'] as $key) {
            $value = $input->getOption($key);
            if (\is_string($value) && $value !== '') {
                $config[$key] = $value;
            }
        }

        $udpValue = $input->getOption('udp');
        if (\is_string($udpValue) && $udpValue !== '') {
            $config['udp'] = filter_var($udpValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $udpValue === '1';
        }

        return $this->fromArray($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): NodeConfig
    {
        $type = isset($config['type']) ? strtolower((string) $config['type']) : 'ss';
        if ($type !== 'ss') {
            throw new InvalidArgumentException(sprintf('Unsupported node type "%s". Only "ss" is supported.', $type));
        }

        $server = trim((string) ($config['server'] ?? ''));
        if ($server === '') {
            throw new InvalidArgumentException('The node config must include a non-empty "server".');
        }

        $port = (int) ($config['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('The node config must include a valid "port".');
        }

        $cipher = strtolower(trim((string) ($config['cipher'] ?? '')));
        if ($cipher !== 'aes-256-gcm') {
            throw new InvalidArgumentException(sprintf('Unsupported cipher "%s". The current release supports only aes-256-gcm.', $cipher));
        }

        $password = (string) ($config['password'] ?? '');
        if ($password === '') {
            throw new InvalidArgumentException('The node config must include a non-empty "password".');
        }

        $udp = (bool) ($config['udp'] ?? false);
        $name = isset($config['name']) ? (string) $config['name'] : null;

        return new NodeConfig(
            server: $server,
            port: $port,
            cipher: $cipher,
            password: $password,
            udp: $udp,
            name: $name,
        );
    }
}
