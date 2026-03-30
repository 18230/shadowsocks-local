# ss-local

[![Ubuntu CI](https://img.shields.io/github/actions/workflow/status/18230/shadowsocks-local/ubuntu-ci.yml?branch=main&label=ubuntu%20ci)](https://github.com/18230/shadowsocks-local/actions/workflows/ubuntu-ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/shadowsocks-local?label=release)](https://github.com/18230/shadowsocks-local/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/shadowsocks-local?label=packagist)](https://packagist.org/packages/18230/shadowsocks-local)
[![Packagist Downloads](https://img.shields.io/packagist/dt/18230/shadowsocks-local?label=downloads)](https://packagist.org/packages/18230/shadowsocks-local)
[![License](https://img.shields.io/github/license/18230/shadowsocks-local)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)

[English](README.md)

`ss-local` 是一个纯 PHP 的 Shadowsocks 本地客户端 Composer 包。它会在本地暴露一个 SOCKS5 代理端口，并通过 Workerman 长驻进程把 TCP 流量中继到远端 Shadowsocks 节点。

## 功能特性

- PHP 8.2+
- 支持 Windows、Linux、macOS
- Composer 包，带 CLI 启动入口
- 纯 PHP 实现 SOCKS5 前端和 Shadowsocks TCP 中继
- 支持 `aes-256-gcm`
- 支持 YAML、JSON、`ss://` 三种节点输入方式
- 提供业务层可直接使用的 `ProxyService`
- 提供 Laravel / ThinkPHP 集成入口
- 提供跨平台启动脚本
- 提供 GitHub Actions Ubuntu CI 工作流，便于 Linux 侧验证

## 当前范围

已实现：

- SOCKS5 `CONNECT`
- Shadowsocks TCP relay
- CLI 启动命令
- PHP `curl` / Guzzle 的 TLS 帮助类
- 日志、超时、基础稳定性保护

暂未实现：

- UDP relay
- SIP003 插件
- AEAD-2022
- `aes-256-gcm` 之外的其他算法

## 安装

从 Packagist 安装：

```bash
composer require 18230/shadowsocks-local
```

如果你是在当前仓库本地开发：

```bash
composer install
```

## 快速开始

使用显式参数启动：

```bash
php bin/ss-local \
  --server=your-node.example.com \
  --port=18001 \
  --cipher=aes-256-gcm \
  --password=your-password \
  --listen=127.0.0.1:1080
```

使用内联 YAML 启动：

```bash
php bin/ss-local --node="{ name: 'SG 01', type: ss, server: your-node.example.com, port: 18001, cipher: aes-256-gcm, password: your-password, udp: true }"
```

使用配置文件启动：

```bash
php bin/ss-local --config=examples/node.example.yaml
```

启动前先做一次环境和配置自检：

```bash
php bin/ss-local doctor --config=examples/node.example.yaml
```

查看全部参数：

```bash
php bin/ss-local --help
```

## 业务代码接入

### PHP curl

```php
<?php

use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

$proxy = ProxyService::fromListenAddress(
    '127.0.0.1:1080',
    TlsSettings::fromIni()
);

$ch = curl_init('https://api.ipify.org?format=json');
$proxy->applyToCurlHandle($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$result = curl_exec($ch);
curl_close($ch);
```

### Guzzle

```php
<?php

use GuzzleHttp\Client;
use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

$proxy = ProxyService::fromListenAddress('127.0.0.1:1080', TlsSettings::fromIni());
$client = new Client($proxy->guzzleOptions());

$response = $client->get('https://api.ipify.org?format=json');
echo $response->getBody()->getContents();
```

### 命令行 curl

```bash
curl --proxy socks5h://127.0.0.1:1080 https://api.ipify.org?format=json
```

## 配置方式

CLI 支持以下输入来源：

- `--server`、`--port`、`--cipher`、`--password`
- `--node`，支持内联 YAML、JSON、`ss://`
- `--config`，支持 YAML / JSON 文件

推荐的配置文件结构：

```yaml
node:
  name: "SG 01"
  type: ss
  server: your-node.example.com
  port: 18001
  cipher: aes-256-gcm
  password: your-password
  udp: true

runtime:
  listen: 127.0.0.1:1080
  worker_count: 1
  max_connections: 1024
  allow_ips:
    - 127.0.0.1
    - ::1
  connect_timeout: 10
  connect_retries: 1
  retry_delay_ms: 250
  idle_timeout: 900
  max_send_buffer: 4194304
  status_file: runtime/ss-local.status.json
  status_interval: 10
  log_file: runtime/ss-local.log
  pid_file: runtime/ss-local.pid
  daemon: false
```

常用运行参数：

- `--listen=127.0.0.1:1080`
- `--worker-count=1`
- `--max-connections=1024`
- `--allow-ip=127.0.0.1,::1`
- `--connect-timeout=10`
- `--connect-retries=1`
- `--retry-delay-ms=250`
- `--idle-timeout=900`
- `--max-send-buffer=4194304`
- `--status-file=/path/to/ss-local.status.json`
- `--status-interval=10`
- `--log-file=/path/to/ss-local.log`
- `--pid-file=/path/to/ss-local.pid`
- `--daemon`，仅非 Windows 平台
- `--verbose-log`

这些偏生产环境的运行参数分别负责：

- `worker_count`：Unix 类平台上的 Workerman worker 进程数，Windows 固定单 worker
- `max_connections`：每个 worker 允许的活跃客户端连接上限
- `allow_ips`：可选的客户端 IP 白名单，支持精确 IP 和 CIDR
- `connect_retries` / `retry_delay_ms`：上游 Shadowsocks 建连阶段的简单重试策略
- `status_file`：周期性输出 JSON 状态文件，便于运维或监控
- `status_interval`：状态文件刷新周期，单位秒

文件配置示例见 [node.example.yaml](examples/node.example.yaml)。

## TLS / CA 证书

代理本身不需要 CA 证书；真正需要 CA 的是你的 PHP 业务程序在访问 `https://` 地址时的证书校验。

如果 `curl.cainfo` 和 `openssl.cafile` 没配，代理明明是通的，PHP `curl` 依然可能报证书错误。

模板见：

- [resources/php/cacert.ini.example](resources/php/cacert.ini.example)

## 文档导航

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)
- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)
- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [更新日志](CHANGELOG.md)

建议的最小生产配置姿势：

- 本地监听尽量只绑定到回环地址
- 如果必须绑定到内网地址，配合 `--allow-ip` 一起使用
- 部署前先执行一次 `php bin/ss-local doctor ...`
- 在没有压测结论前，优先保持 `worker_count=1`
- 建议同时打开 `status_file` 和 `log_file`

## 示例

- [节点配置示例](examples/node.example.yaml)
- [环境变量示例](examples/ss-local.env.example)
- [PHP curl 示例](examples/curl-demo.php)
- [Guzzle 示例](examples/guzzle-demo.php)

## 测试

```bash
composer test
```

## 发包前提示

- 当前仓库对应的 Packagist 包名为 `18230/shadowsocks-local`。
- 发版前建议运行 `composer validate --strict` 和 `composer test`。
- 如果想让每次 push 后都自动通知 Packagist，请在 GitHub 仓库里添加 `PACKAGIST_API_TOKEN` secret，并按需添加 `PACKAGIST_USERNAME` variable。
- 如果想消掉 Packagist 页面上的“not auto-updated”提示，再执行一次原生 GitHub hook 脚本：`scripts/setup-packagist-github-hook.ps1` 或 `scripts/setup-packagist-github-hook.sh`。
