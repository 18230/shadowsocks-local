<?php

declare(strict_types=1);

namespace SsLocal\Command;

use SsLocal\Config\InputConfigLoader;
use SsLocal\Config\NodeConfigFactory;
use SsLocal\Runtime\IpAccessList;
use SsLocal\Runtime\Platform;
use SsLocal\Runtime\RunOptions;
use SsLocal\Support\TlsSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'doctor', description: 'Check runtime prerequisites and validate the current configuration.')]
final class DoctorCommand extends Command
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
            ->addOption('pid-file', null, InputOption::VALUE_REQUIRED, 'Optional PID file path.')
            ->addOption('daemon', null, InputOption::VALUE_NONE, 'Run as a daemon on platforms where Workerman supports it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $failures = 0;

        $io->title('ss-local doctor');

        foreach (['json', 'openssl', 'sockets'] as $extension) {
            $loaded = extension_loaded($extension);
            $loaded ? $io->writeln(sprintf('<info>OK</info> ext-%s loaded', $extension)) : $io->writeln(sprintf('<error>FAIL</error> ext-%s missing', $extension));
            $failures += $loaded ? 0 : 1;
        }

        $curlLoaded = extension_loaded('curl');
        $curlLoaded ? $io->writeln('<info>OK</info> ext-curl loaded') : $io->warning('ext-curl is missing. Proxy helpers for PHP curl will not be available.');

        version_compare(PHP_VERSION, '8.2.0', '>=') ?
            $io->writeln(sprintf('<info>OK</info> PHP %s', PHP_VERSION)) :
            ($io->writeln(sprintf('<error>FAIL</error> PHP %s, required >= 8.2', PHP_VERSION)) || $failures++);

        if (Platform::isWindows()) {
            $io->warning('Windows is supported, but long-running production deployments are still better suited to Linux.');
        }

        $tls = TlsSettings::fromIni();
        $tls->hasConfiguredCa() ?
            $io->writeln(sprintf('<info>OK</info> CA bundle detected (%s)', $tls->caFile ?? $tls->caPath)) :
            $io->warning('No CA bundle is configured in php.ini. HTTPS requests from PHP curl may fail certificate validation.');

        $options = null;
        try {
            $loadedConfig = (new InputConfigLoader())->load($input);
            $node = (new NodeConfigFactory())->fromInput($input, $loadedConfig->node);
            $options = RunOptions::fromInput($input, $loadedConfig->runtime);
            $allowList = IpAccessList::fromStrings($options->allowIps);

            $io->writeln(sprintf('<info>OK</info> Node config parsed for %s:%d using %s', $node->server, $node->port, $node->cipher));
            $io->writeln(sprintf('<info>OK</info> Local listen %s:%d, workers=%d, max_connections=%d', $options->listenHost, $options->listenPort, $options->workerCount, $options->maxConnections));
            $io->writeln(sprintf('<info>OK</info> Client IP policy: %s', $allowList->entries() === [] ? 'allow all' : implode(', ', $allowList->entries())));
            if ($options->statusFile !== null) {
                $io->writeln(sprintf('<info>OK</info> Status file configured at %s', $options->statusFile));
            }
        } catch (\Throwable $throwable) {
            $io->writeln(sprintf('<error>FAIL</error> %s', $throwable->getMessage()));
            $failures++;
        }

        if (!Platform::isWindows() && $options !== null) {
            foreach (['pcntl', 'posix'] as $extension) {
                $loaded = extension_loaded($extension);
                if ($loaded) {
                    $io->writeln(sprintf('<info>OK</info> ext-%s loaded', $extension));
                    continue;
                }

                if ($options->daemonize || $options->workerCount > 1) {
                    $io->writeln(sprintf('<error>FAIL</error> ext-%s missing but required for daemon or multi-worker mode on Unix platforms', $extension));
                    $failures++;
                    continue;
                }

                $io->warning(sprintf('ext-%s is not loaded. Single-process foreground mode is usually fine, but Unix production deployments are better with this extension available.', $extension));
            }
        }

        return $failures === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
