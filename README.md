# ss-local

[![Ubuntu CI](https://img.shields.io/github/actions/workflow/status/18230/shadowsocks-local/ubuntu-ci.yml?branch=main&label=ubuntu%20ci)](https://github.com/18230/shadowsocks-local/actions/workflows/ubuntu-ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/shadowsocks-local?label=release)](https://github.com/18230/shadowsocks-local/tags)
[![License](https://img.shields.io/github/license/18230/shadowsocks-local)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)

[中文文档](README.zh-CN.md)

`ss-local` is a pure-PHP Shadowsocks local client package. It exposes a local SOCKS5 endpoint and relays TCP traffic to a remote Shadowsocks server by using Workerman as the long-running runtime.

## Features

- PHP 8.2+
- Windows, Linux, and macOS
- Composer package with a CLI entrypoint
- Pure PHP SOCKS5 frontend and Shadowsocks TCP relay
- `aes-256-gcm`
- YAML, JSON, and `ss://` node parsing
- Reusable `ProxyService` helpers for application code
- Laravel and ThinkPHP integration entry points
- Cross-platform startup scripts for development and production
- GitHub Actions Ubuntu CI workflow for Linux validation

## Current Scope

Implemented:

- SOCKS5 `CONNECT`
- Shadowsocks TCP relay
- CLI startup command
- TLS helper objects for PHP `curl` and Guzzle
- Structured logging and basic runtime guards

Not implemented yet:

- UDP relay
- SIP003 plugins
- AEAD-2022 methods
- Additional ciphers beyond `aes-256-gcm`

## Installation

Install from Packagist:

```bash
composer require 18230/shadowsocks-local
```

For local development in this repository:

```bash
composer install
```

## Quick Start

Start with explicit options:

```bash
php bin/ss-local \
  --server=your-node.example.com \
  --port=18001 \
  --cipher=aes-256-gcm \
  --password=your-password \
  --listen=127.0.0.1:1080
```

Start with inline YAML:

```bash
php bin/ss-local --node="{ name: 'SG 01', type: ss, server: your-node.example.com, port: 18001, cipher: aes-256-gcm, password: your-password, udp: true }"
```

Start with a config file:

```bash
php bin/ss-local --config=examples/node.example.yaml
```

Validate the runtime and configuration before you start:

```bash
php bin/ss-local doctor --config=examples/node.example.yaml
```

Check available options:

```bash
php bin/ss-local --help
```

## Application Usage

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

### CLI curl

```bash
curl --proxy socks5h://127.0.0.1:1080 https://api.ipify.org?format=json
```

## Configuration

The CLI accepts configuration from:

- `--server`, `--port`, `--cipher`, `--password`
- `--node` with inline YAML, JSON, or `ss://`
- `--config` with YAML or JSON files

Recommended config file structure:

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

Useful runtime options:

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
- `--daemon` on non-Windows platforms
- `--verbose-log`

What these production-oriented runtime options do:

- `worker_count`: number of Workerman worker processes on Unix-like platforms. Windows always runs a single worker.
- `max_connections`: per-worker cap for active client sessions. New clients are rejected after the cap is reached.
- `allow_ips`: optional client IP allowlist. Supports exact IPs and CIDR ranges.
- `connect_retries` and `retry_delay_ms`: simple retry policy for the initial upstream Shadowsocks connect phase.
- `status_file`: periodic JSON status snapshot for ops checks or sidecar monitoring.
- `status_interval`: write interval for the status snapshot in seconds.

See [node.example.yaml](examples/node.example.yaml) for a file-based example.

## TLS / CA Configuration

The proxy itself does not need a CA file. Your PHP application needs one when it accesses `https://` endpoints and wants certificate verification to succeed.

If `curl.cainfo` and `openssl.cafile` are not configured, PHP `curl` can fail even when the proxy works correctly.

Template:

- [resources/php/cacert.ini.example](resources/php/cacert.ini.example)

## Framework Integration

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)

## Production Deployment

- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)

Recommended minimum production posture:

- Keep the local listen address on loopback unless you explicitly need LAN access
- Use `--allow-ip` if you bind beyond loopback
- Run `php bin/ss-local doctor ...` before deploying
- Keep `worker_count=1` unless you have validated your workload on Unix
- Enable `status_file` and `log_file` so operational failures are visible

## Release Notes

- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [Changelog](CHANGELOG.md)

## Examples

- [Node config example](examples/node.example.yaml)
- [Environment example](examples/ss-local.env.example)
- [PHP curl example](examples/curl-demo.php)
- [Guzzle example](examples/guzzle-demo.php)

## Testing

```bash
composer test
```

## Notes for Publishing

- The Packagist package name for this repository is `18230/shadowsocks-local`.
- Run `composer validate --strict` and `composer test` before tagging a release.
