<?php

declare(strict_types=1);

namespace SsLocal\Command;

use SsLocal\Config\InputConfigLoader;
use SsLocal\Config\NodeConfigFactory;
use SsLocal\Runtime\LocalServer;
use SsLocal\Runtime\LoggerFactory;
use SsLocal\Runtime\Platform;
use SsLocal\Runtime\RunOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'run', description: 'Start the local SOCKS5 server and relay traffic through a Shadowsocks node.')]
final class RunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('node', null, InputOption::VALUE_REQUIRED, 'Inline node config in YAML/JSON or an ss:// URI.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a YAML or JSON file that contains the node config.')
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Shadowsocks server hostname.')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Shadowsocks server port.')
            ->addOption('cipher', null, InputOption::VALUE_REQUIRED, 'Cipher method, currently aes-256-gcm.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Server password.')
            ->addOption('udp', null, InputOption::VALUE_OPTIONAL, 'Whether the source node enables UDP.')
            ->addOption('listen', null, InputOption::VALUE_REQUIRED, 'Local SOCKS5 listen endpoint.')
            ->addOption('worker-count', null, InputOption::VALUE_REQUIRED, 'Number of worker processes.')
            ->addOption('max-connections', null, InputOption::VALUE_REQUIRED, 'Maximum concurrent client connections per worker.')
            ->addOption('allow-ip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Allowed client IP or CIDR, may be passed multiple times.')
            ->addOption('connect-timeout', null, InputOption::VALUE_REQUIRED, 'Remote connect timeout in seconds.')
            ->addOption('connect-retries', null, InputOption::VALUE_REQUIRED, 'Number of retries before the connect phase fails.')
            ->addOption('retry-delay-ms', null, InputOption::VALUE_REQUIRED, 'Delay between connect retries in milliseconds.')
            ->addOption('idle-timeout', null, InputOption::VALUE_REQUIRED, 'Idle timeout in seconds for both local and remote connections.')
            ->addOption('max-send-buffer', null, InputOption::VALUE_REQUIRED, 'Maximum send buffer size per connection in bytes.')
            ->addOption('status-file', null, InputOption::VALUE_REQUIRED, 'Optional status file path.')
            ->addOption('status-interval', null, InputOption::VALUE_REQUIRED, 'Status file flush interval in seconds.')
            ->addOption('log-file', null, InputOption::VALUE_REQUIRED, 'Optional log file path.')
            ->addOption('pid-file', null, InputOption::VALUE_REQUIRED, 'Optional PID file path, effective on non-Windows platforms.')
            ->addOption('daemon', null, InputOption::VALUE_NONE, 'Run as a daemon on platforms where Workerman supports it.')
            ->addOption('verbose-log', null, InputOption::VALUE_NONE, 'Emit debug-level logs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $loadedConfig = (new InputConfigLoader())->load($input);
        $node = (new NodeConfigFactory())->fromInput($input, $loadedConfig->node);

        if ($node->udp) {
            $io->warning('The node advertises UDP support, but this package currently implements the TCP relay path only.');
        }

        $options = RunOptions::fromInput($input, $loadedConfig->runtime);
        if ($options->daemonize && Platform::isWindows()) {
            $io->warning('Workerman daemon mode is not available on Windows. The server will continue in foreground mode.');
        }
        if ($options->workerCount > 1 && Platform::isWindows()) {
            $io->warning('Windows does not support multi-process worker counts in Workerman. The server will continue with a single worker.');
        }

        $logger = LoggerFactory::create(
            verbose: (bool) $input->getOption('verbose-log'),
            logFile: $options->logFile
        );

        $server = new LocalServer($node, $options, $logger);
        $server->run();

        return Command::SUCCESS;
    }
}
