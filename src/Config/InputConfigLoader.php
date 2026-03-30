<?php

declare(strict_types=1);

namespace SsLocal\Config;

use SsLocal\Config\Parser\ConfigFileParser;
use Symfony\Component\Console\Input\InputInterface;

final class InputConfigLoader
{
    public function load(InputInterface $input): InputConfig
    {
        $configPath = $input->getOption('config');
        if (!\is_string($configPath) || $configPath === '') {
            return new InputConfig([], []);
        }

        $parsed = (new ConfigFileParser())->parse($configPath);

        $node = isset($parsed['node']) && \is_array($parsed['node']) ? $parsed['node'] : $parsed;
        $runtime = isset($parsed['runtime']) && \is_array($parsed['runtime']) ? $parsed['runtime'] : $parsed;

        return new InputConfig($node, $runtime);
    }
}
