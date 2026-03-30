<?php

declare(strict_types=1);

namespace SsLocal\Config\Parser;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class ConfigFileParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Config file not found: %s', $path));
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $contents = (string) file_get_contents($path);

        if ($extension === 'json') {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                throw new InvalidArgumentException('The JSON config must decode to an object.');
            }

            return $decoded;
        }

        $parsed = Yaml::parse($contents);
        if (!\is_array($parsed)) {
            throw new InvalidArgumentException('The YAML config must decode to a mapping.');
        }

        return $parsed;
    }
}
