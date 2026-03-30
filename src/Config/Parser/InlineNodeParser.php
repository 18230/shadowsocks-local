<?php

declare(strict_types=1);

namespace SsLocal\Config\Parser;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

final class InlineNodeParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $node): array
    {
        $node = trim($node);
        if ($node === '') {
            throw new InvalidArgumentException('The inline node config cannot be empty.');
        }

        if (str_starts_with($node, 'ss://')) {
            return (new SsUriParser())->parse($node);
        }

        $json = json_decode($node, true);
        if (\is_array($json)) {
            return $json;
        }

        $parsed = Yaml::parse($node);
        if (!\is_array($parsed)) {
            throw new InvalidArgumentException('The inline node config must decode to a mapping.');
        }

        return $parsed;
    }
}
