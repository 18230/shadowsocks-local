# 生产部署说明

## 推荐拓扑

建议把 `ss-local` 作为独立进程运行，让业务程序复用本地 SOCKS5 端口。

```text
应用 / curl / Guzzle -> 127.0.0.1:1080 (SOCKS5) -> ss-local -> Shadowsocks 节点 -> 目标网站
```

## 启动脚本

包里已经提供跨平台脚本：

- Windows PowerShell: [scripts/start-ss-local.ps1](../../scripts/start-ss-local.ps1)
- Windows CMD: [scripts/start-ss-local.bat](../../scripts/start-ss-local.bat)
- Linux/macOS: [scripts/start-ss-local.sh](../../scripts/start-ss-local.sh)

这些脚本读取的环境变量包括：

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
- `SS_DAEMON`，仅 `start-ss-local.sh`

环境变量示例见 [examples/ss-local.env.example](../../examples/ss-local.env.example)。

## Linux / macOS

### Shell 启动

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

### systemd 模板

- [resources/systemd/ss-local.service](../../resources/systemd/ss-local.service)

### supervisord 模板

- [resources/supervisor/ss-local.conf](../../resources/supervisor/ss-local.conf)

### macOS launchd 模板

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

## TLS / CA 证书

代理进程本身不需要 CA 证书，真正需要 CA 的是访问 `https://` 的 PHP 业务代码。

模板见：

- [resources/php/cacert.ini.example](../../resources/php/cacert.ini.example)

推荐做法：

1. 下载可信的 `cacert.pem`
2. 放在固定路径
3. 在 `php.ini` 里配置

例如：

```ini
curl.cainfo = "/absolute/path/to/cacert.pem"
openssl.cafile = "/absolute/path/to/cacert.pem"
```

如果不想改全局 `php.ini`，也可以在命令行追加：

```bash
php -d curl.cainfo=/absolute/path/to/cacert.pem -d openssl.cafile=/absolute/path/to/cacert.pem your-script.php
```

## 日志与稳定性建议

建议保留这些默认值：

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

运行建议：

- 让代理进程独立于 Web Worker 运行
- 本地监听地址尽量只绑定到回环地址
- 如果绑定到非回环地址，建议同时配置 `--allow-ip`
- 生产环境使用进程守护
- 如果 `worker_count > 1`，每个 worker 会各自写一个状态文件，文件名形如 `name.worker-<id>.json`
- 业务如果走 HTTPS，一定把 CA 配好
- 部署时建议执行 `php bin/ss-local doctor --config=/path/to/config.yaml`
