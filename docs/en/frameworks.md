# Framework Integration

This package is designed around a simple production model:

1. Run `ss-local` as a dedicated long-running process.
2. Let Laravel, ThinkPHP, or any other application reuse the local SOCKS5 endpoint.
3. Apply the local proxy settings through `ProxyService`.

## Shared Runtime Objects

The package provides these framework-friendly helpers:

- `SsLocal\Support\ProxyEndpoint`
- `SsLocal\Support\TlsSettings`
- `SsLocal\Support\ProxyService`
- `SsLocal\Support\Startup\CommandBuilder`

## Laravel

### Installation

```bash
composer require 18230/shadowsocks-local
```

Laravel auto-discovery will register:

- `SsLocal\Integration\Laravel\ShadowsocksServiceProvider`

### Publish Configuration

```bash
php artisan vendor:publish --tag=ss-local-config
```

Published config file:

- `config/ss-local.php`

Source template:

- [config/laravel/ss-local.php](../../config/laravel/ss-local.php)

### Example Controller

```php
<?php

namespace App\Http\Controllers;

use SsLocal\Support\ProxyService;

final class DemoController
{
    public function __invoke(ProxyService $proxy): array
    {
        $ch = curl_init('https://api.ipify.org?format=json');
        $proxy->applyToCurlHandle($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'result' => $result,
            'error' => $error,
        ];
    }
}
```

### Guzzle Example

```php
<?php

use GuzzleHttp\Client;
use SsLocal\Support\ProxyService;

final class ApiService
{
    public function __construct(
        private readonly ProxyService $proxy
    ) {
    }

    public function request(): string
    {
        $client = new Client($this->proxy->guzzleOptions());
        return $client->get('https://api.ipify.org?format=json')->getBody()->getContents();
    }
}
```

## ThinkPHP

### Installation

```bash
composer require 18230/shadowsocks-local
```

### Register the Service

Add the service provider to your application's `provider.php`:

```php
<?php

return [
    SsLocal\Integration\ThinkPHP\ShadowsocksService::class,
];
```

Config template:

- [config/thinkphp/ss_local.php](../../config/thinkphp/ss_local.php)

### Example Usage

```php
<?php

use SsLocal\Support\ProxyService;

$proxy = app(ProxyService::class);

$ch = curl_init('https://api.ipify.org?format=json');
$proxy->applyToCurlHandle($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);
```

## When to Start the Proxy Process

Do not boot the long-running proxy process inside a web request.

Recommended deployment layout:

- Start `ss-local` separately via `systemd`, `supervisord`, `launchd`, a container entrypoint, or Windows service tooling
- Inject `ProxyService` into application code
- Keep framework requests stateless and only point them to `127.0.0.1:1080`

## Command Generation

If you want your application or deployment tooling to build the exact startup command, use `CommandBuilder`:

```php
<?php

use SsLocal\Config\NodeConfig;
use SsLocal\Runtime\RunOptions;
use SsLocal\Support\Startup\CommandBuilder;

$builder = new CommandBuilder('/usr/bin/php', '/app/vendor/bin/ss-local');

$command = $builder->toShellCommand(
    new NodeConfig(
        server: 'your-node.example.com',
        port: 18001,
        cipher: 'aes-256-gcm',
        password: 'your-password',
        udp: false,
    ),
    new RunOptions(
        listenHost: '127.0.0.1',
        listenPort: 1080,
        workerCount: 1,
        maxConnections: 1024,
        allowIps: ['127.0.0.1', '::1'],
        connectTimeout: 10,
        connectRetries: 1,
        retryDelayMs: 250,
        idleTimeout: 900,
        maxSendBufferSize: 4194304,
        statusFile: '/var/run/ss-local.status.json',
        statusInterval: 10,
        logFile: '/var/log/ss-local.log',
        pidFile: '/var/run/ss-local.pid',
        daemonize: true,
    ),
    verboseLog: false,
);
```
