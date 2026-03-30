# 框架接入

这个包推荐采用下面的生产方式：

1. 把 `ss-local` 作为独立长驻进程运行
2. Laravel、ThinkPHP 或其他应用复用本地 SOCKS5 端口
3. 通过 `ProxyService` 把代理配置注入到业务代码

## 通用运行时对象

包里提供了这些适合框架接入的对象：

- `SsLocal\Support\ProxyEndpoint`
- `SsLocal\Support\TlsSettings`
- `SsLocal\Support\ProxyService`
- `SsLocal\Support\Startup\CommandBuilder`

## Laravel

### 安装

```bash
composer require ssphp/shadowsocks-local
```

Laravel 会通过自动发现注册：

- `SsLocal\Integration\Laravel\ShadowsocksServiceProvider`

### 发布配置

```bash
php artisan vendor:publish --tag=ss-local-config
```

发布后的配置文件：

- `config/ss-local.php`

模板来源：

- [config/laravel/ss-local.php](../../config/laravel/ss-local.php)

### 控制器示例

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

### Guzzle 示例

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

### 安装

```bash
composer require ssphp/shadowsocks-local
```

### 注册服务

在应用的 `provider.php` 中加入：

```php
<?php

return [
    SsLocal\Integration\ThinkPHP\ShadowsocksService::class,
];
```

配置模板：

- [config/thinkphp/ss_local.php](../../config/thinkphp/ss_local.php)

### 使用示例

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

## 代理进程什么时候启动

不要在 Web 请求里动态启动长驻代理进程。

推荐部署方式：

- 用 `systemd`、`supervisord`、`launchd`、容器入口脚本或 Windows 服务工具单独启动 `ss-local`
- 在业务代码里注入 `ProxyService`
- 框架里的请求逻辑只关心本地代理地址，例如 `127.0.0.1:1080`

## 启动命令生成

如果你希望在应用或部署工具里统一生成启动命令，可以使用 `CommandBuilder`：

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
