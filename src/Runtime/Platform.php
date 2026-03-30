<?php

declare(strict_types=1);

namespace SsLocal\Runtime;

final class Platform
{
    public static function isWindows(): bool
    {
        return \DIRECTORY_SEPARATOR === '\\';
    }
}
