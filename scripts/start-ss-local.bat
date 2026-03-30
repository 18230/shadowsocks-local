@echo off
setlocal

if "%PHP_BIN%"=="" set "PHP_BIN=php"
if "%SS_SERVER%"=="" (
  echo SS_SERVER is required.
  exit /b 1
)
if "%SS_PORT%"=="" (
  echo SS_PORT is required.
  exit /b 1
)
if "%SS_PASSWORD%"=="" (
  echo SS_PASSWORD is required.
  exit /b 1
)
if "%SS_CIPHER%"=="" set "SS_CIPHER=aes-256-gcm"
if "%SS_UDP%"=="" set "SS_UDP=0"
if "%SS_LISTEN%"=="" set "SS_LISTEN=127.0.0.1:1080"
if "%SS_WORKER_COUNT%"=="" set "SS_WORKER_COUNT=1"
if "%SS_MAX_CONNECTIONS%"=="" set "SS_MAX_CONNECTIONS=1024"
if "%SS_CONNECT_TIMEOUT%"=="" set "SS_CONNECT_TIMEOUT=10"
if "%SS_CONNECT_RETRIES%"=="" set "SS_CONNECT_RETRIES=1"
if "%SS_RETRY_DELAY_MS%"=="" set "SS_RETRY_DELAY_MS=250"
if "%SS_IDLE_TIMEOUT%"=="" set "SS_IDLE_TIMEOUT=900"
if "%SS_MAX_SEND_BUFFER%"=="" set "SS_MAX_SEND_BUFFER=4194304"
if "%SS_STATUS_INTERVAL%"=="" set "SS_STATUS_INTERVAL=10"
if "%SS_VERBOSE_LOG%"=="" set "SS_VERBOSE_LOG=0"

set "BIN=%~dp0..\bin\ss-local"
set "ALLOW_IP_ARG="
set "STATUS_ARG="
set "LOG_ARG="
set "PID_ARG="
set "VERBOSE_ARG="

if not "%SS_ALLOW_IPS%"=="" set "ALLOW_IP_ARG=--allow-ip=%SS_ALLOW_IPS%"
if not "%SS_STATUS_FILE%"=="" set "STATUS_ARG=--status-file=%SS_STATUS_FILE%"
if not "%SS_LOG_FILE%"=="" set "LOG_ARG=--log-file=%SS_LOG_FILE%"
if not "%SS_PID_FILE%"=="" set "PID_ARG=--pid-file=%SS_PID_FILE%"
if "%SS_VERBOSE_LOG%"=="1" set "VERBOSE_ARG=--verbose-log"

"%PHP_BIN%" "%BIN%" "--server=%SS_SERVER%" "--port=%SS_PORT%" "--cipher=%SS_CIPHER%" "--password=%SS_PASSWORD%" "--udp=%SS_UDP%" "--listen=%SS_LISTEN%" "--worker-count=%SS_WORKER_COUNT%" "--max-connections=%SS_MAX_CONNECTIONS%" "--connect-timeout=%SS_CONNECT_TIMEOUT%" "--connect-retries=%SS_CONNECT_RETRIES%" "--retry-delay-ms=%SS_RETRY_DELAY_MS%" "--idle-timeout=%SS_IDLE_TIMEOUT%" "--max-send-buffer=%SS_MAX_SEND_BUFFER%" "--status-interval=%SS_STATUS_INTERVAL%" %ALLOW_IP_ARG% %STATUS_ARG% %LOG_ARG% %PID_ARG% %VERBOSE_ARG%
