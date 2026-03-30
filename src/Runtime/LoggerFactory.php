<?php

declare(strict_types=1);

namespace SsLocal\Runtime;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(bool $verbose = false, ?string $logFile = null): LoggerInterface
    {
        if ($logFile !== null && stream_is_local($logFile)) {
            $directory = dirname($logFile);
            if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        $handler = new StreamHandler($logFile ?? 'php://stdout', $verbose ? Level::Debug : Level::Info);
        $handler->setFormatter(new LineFormatter("[%datetime%] %level_name% %message% %context%\n", null, true, true));

        return new Logger('ss-local', [$handler]);
    }
}
