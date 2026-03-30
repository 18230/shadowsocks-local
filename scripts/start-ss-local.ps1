$ErrorActionPreference = 'Stop'

if (-not $env:PHP_BIN -or [string]::IsNullOrWhiteSpace($env:PHP_BIN)) {
    $env:PHP_BIN = 'php'
}

$root = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($env:SS_SERVER)) { throw 'SS_SERVER is required.' }
if ([string]::IsNullOrWhiteSpace($env:SS_PORT)) { throw 'SS_PORT is required.' }
if ([string]::IsNullOrWhiteSpace($env:SS_PASSWORD)) { throw 'SS_PASSWORD is required.' }
if ([string]::IsNullOrWhiteSpace($env:SS_CIPHER)) { $env:SS_CIPHER = 'aes-256-gcm' }
if ([string]::IsNullOrWhiteSpace($env:SS_UDP)) { $env:SS_UDP = '0' }
if ([string]::IsNullOrWhiteSpace($env:SS_LISTEN)) { $env:SS_LISTEN = '127.0.0.1:1080' }
if ([string]::IsNullOrWhiteSpace($env:SS_WORKER_COUNT)) { $env:SS_WORKER_COUNT = '1' }
if ([string]::IsNullOrWhiteSpace($env:SS_MAX_CONNECTIONS)) { $env:SS_MAX_CONNECTIONS = '1024' }
if ([string]::IsNullOrWhiteSpace($env:SS_CONNECT_TIMEOUT)) { $env:SS_CONNECT_TIMEOUT = '10' }
if ([string]::IsNullOrWhiteSpace($env:SS_CONNECT_RETRIES)) { $env:SS_CONNECT_RETRIES = '1' }
if ([string]::IsNullOrWhiteSpace($env:SS_RETRY_DELAY_MS)) { $env:SS_RETRY_DELAY_MS = '250' }
if ([string]::IsNullOrWhiteSpace($env:SS_IDLE_TIMEOUT)) { $env:SS_IDLE_TIMEOUT = '900' }
if ([string]::IsNullOrWhiteSpace($env:SS_MAX_SEND_BUFFER)) { $env:SS_MAX_SEND_BUFFER = '4194304' }
if ([string]::IsNullOrWhiteSpace($env:SS_STATUS_INTERVAL)) { $env:SS_STATUS_INTERVAL = '10' }
if ([string]::IsNullOrWhiteSpace($env:SS_VERBOSE_LOG)) { $env:SS_VERBOSE_LOG = '0' }

$arguments = @(
    (Join-Path $root 'bin\ss-local'),
    "--server=$($env:SS_SERVER)",
    "--port=$($env:SS_PORT)",
    "--cipher=$($env:SS_CIPHER)",
    "--password=$($env:SS_PASSWORD)",
    "--udp=$($env:SS_UDP)",
    "--listen=$($env:SS_LISTEN)",
    "--worker-count=$($env:SS_WORKER_COUNT)",
    "--max-connections=$($env:SS_MAX_CONNECTIONS)",
    "--connect-timeout=$($env:SS_CONNECT_TIMEOUT)",
    "--connect-retries=$($env:SS_CONNECT_RETRIES)",
    "--retry-delay-ms=$($env:SS_RETRY_DELAY_MS)",
    "--idle-timeout=$($env:SS_IDLE_TIMEOUT)",
    "--max-send-buffer=$($env:SS_MAX_SEND_BUFFER)",
    "--status-interval=$($env:SS_STATUS_INTERVAL)"
)

if (-not [string]::IsNullOrWhiteSpace($env:SS_ALLOW_IPS)) {
    $arguments += "--allow-ip=$($env:SS_ALLOW_IPS)"
}

if (-not [string]::IsNullOrWhiteSpace($env:SS_STATUS_FILE)) {
    $arguments += "--status-file=$($env:SS_STATUS_FILE)"
}

if (-not [string]::IsNullOrWhiteSpace($env:SS_LOG_FILE)) {
    $arguments += "--log-file=$($env:SS_LOG_FILE)"
}

if (-not [string]::IsNullOrWhiteSpace($env:SS_PID_FILE)) {
    $arguments += "--pid-file=$($env:SS_PID_FILE)"
}

if ($env:SS_VERBOSE_LOG -eq '1') {
    $arguments += '--verbose-log'
}

& $env:PHP_BIN @arguments
