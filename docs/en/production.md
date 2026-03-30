# Production Guide

## Recommended Topology

Run `ss-local` as a standalone process and let your application reuse the local SOCKS5 endpoint.

```text
App / curl / Guzzle -> 127.0.0.1:1080 (SOCKS5) -> ss-local -> Shadowsocks server -> target site
```

## Startup Scripts

Cross-platform scripts are included:

- Windows PowerShell: [scripts/start-ss-local.ps1](../../scripts/start-ss-local.ps1)
- Windows CMD: [scripts/start-ss-local.bat](../../scripts/start-ss-local.bat)
- Linux/macOS shell: [scripts/start-ss-local.sh](../../scripts/start-ss-local.sh)

All scripts read environment variables:

- `PHP_BIN`
- `SS_SERVER`
- `SS_PORT`
- `SS_CIPHER`
- `SS_PASSWORD`
- `SS_UDP`
- `SS_LISTEN`
- `SS_WORKER_COUNT`
- `SS_MAX_CONNECTIONS`
- `SS_ALLOW_IPS`
- `SS_CONNECT_TIMEOUT`
- `SS_CONNECT_RETRIES`
- `SS_RETRY_DELAY_MS`
- `SS_IDLE_TIMEOUT`
- `SS_MAX_SEND_BUFFER`
- `SS_STATUS_FILE`
- `SS_STATUS_INTERVAL`
- `SS_LOG_FILE`
- `SS_PID_FILE`
- `SS_VERBOSE_LOG`
- `SS_DAEMON` for `start-ss-local.sh`

See [examples/ss-local.env.example](../../examples/ss-local.env.example).

## Linux / macOS

### Shell Script

```bash
export PHP_BIN=/usr/bin/php
export SS_SERVER=your-node.example.com
export SS_PORT=18001
export SS_CIPHER=aes-256-gcm
export SS_PASSWORD=your-password
export SS_LISTEN=127.0.0.1:1080
export SS_WORKER_COUNT=1
export SS_MAX_CONNECTIONS=1024
export SS_ALLOW_IPS=127.0.0.1,::1
export SS_LOG_FILE=/var/log/ss-local.log
export SS_PID_FILE=/var/run/ss-local.pid
export SS_STATUS_FILE=/var/run/ss-local.status.json
export SS_DAEMON=1

./scripts/start-ss-local.sh
```

### systemd Example

Template:

- [resources/systemd/ss-local.service](../../resources/systemd/ss-local.service)

### supervisord Example

Template:

- [resources/supervisor/ss-local.conf](../../resources/supervisor/ss-local.conf)

### launchd Example for macOS

Template:

- [resources/launchd/com.ssphp.ss-local.plist](../../resources/launchd/com.ssphp.ss-local.plist)

## Windows

### PowerShell

```powershell
$env:PHP_BIN = 'E:\phpEnv\php\php-8.2\php.exe'
$env:SS_SERVER = 'your-node.example.com'
$env:SS_PORT = '18001'
$env:SS_CIPHER = 'aes-256-gcm'
$env:SS_PASSWORD = 'your-password'
$env:SS_LISTEN = '127.0.0.1:1080'
$env:SS_WORKER_COUNT = '1'
$env:SS_MAX_CONNECTIONS = '1024'
$env:SS_ALLOW_IPS = '127.0.0.1,::1'
$env:SS_LOG_FILE = 'E:\logs\ss-local.log'
$env:SS_STATUS_FILE = 'E:\logs\ss-local.status.json'

.\scripts\start-ss-local.ps1
```

### CMD

```bat
set PHP_BIN=E:\phpEnv\php\php-8.2\php.exe
set SS_SERVER=your-node.example.com
set SS_PORT=18001
set SS_CIPHER=aes-256-gcm
set SS_PASSWORD=your-password
set SS_LISTEN=127.0.0.1:1080
set SS_WORKER_COUNT=1
set SS_MAX_CONNECTIONS=1024
set SS_ALLOW_IPS=127.0.0.1,::1
set SS_LOG_FILE=E:\logs\ss-local.log
set SS_STATUS_FILE=E:\logs\ss-local.status.json

scripts\start-ss-local.bat
```

## TLS / CA Bundle

The proxy process itself does not need a CA bundle. Your PHP application needs one for `https://` requests.

Template:

- [resources/php/cacert.ini.example](../../resources/php/cacert.ini.example)

Recommended setup:

1. Download a trusted `cacert.pem`
2. Store it in a stable path
3. Configure `php.ini`

Example:

```ini
curl.cainfo = "/absolute/path/to/cacert.pem"
openssl.cafile = "/absolute/path/to/cacert.pem"
```

You can also pass it at runtime:

```bash
php -d curl.cainfo=/absolute/path/to/cacert.pem -d openssl.cafile=/absolute/path/to/cacert.pem your-script.php
```

## Logging and Stability

Recommended defaults:

- `--worker-count=1`
- `--max-connections=1024`
- `--connect-timeout=10`
- `--connect-retries=1`
- `--retry-delay-ms=250`
- `--idle-timeout=900`
- `--max-send-buffer=4194304`
- `--status-file=/var/run/ss-local.status.json`
- `--status-interval=10`
- `--log-file=/var/log/ss-local.log`

Operational notes:

- Run the proxy outside the web worker process
- Keep the local listen address bound to loopback unless you explicitly need remote access
- If you bind to a non-loopback address, configure `--allow-ip`
- Use a process supervisor in production
- If `worker_count > 1`, each worker writes its own status file as `name.worker-<id>.json`
- Configure CA files for PHP if your app uses HTTPS
- Run `php bin/ss-local doctor --config=/path/to/config.yaml` during deployment
