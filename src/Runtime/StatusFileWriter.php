<?php

declare(strict_types=1);

namespace SsLocal\Runtime;

use RuntimeException;

final readonly class StatusFileWriter
{
    public function __construct(
        private string $path
    ) {
    }

    public function resolvedPath(?int $workerId = null): string
    {
        if ($workerId === null) {
            return $this->path;
        }

        $info = pathinfo($this->path);
        $directory = ($info['dirname'] ?? '.') === '.' ? '' : ($info['dirname'] ?? '') . DIRECTORY_SEPARATOR;
        $filename = $info['filename'] ?? $info['basename'] ?? 'status';
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '.json';

        return $directory . $filename . '.worker-' . $workerId . $extension;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload, ?int $workerId = null): string
    {
        $path = $this->resolvedPath($workerId);
        $directory = dirname($path);

        if ($directory !== '' && $directory !== '.' && !is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create status directory "%s".', $directory));
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode the status payload as JSON.');
        }

        if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write status file "%s".', $path));
        }

        return $path;
    }
}
