#!/usr/bin/env sh
set -eu

PHP_BIN="${PHP_BIN:-php}"
APP_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

: "${SS_SERVER:?SS_SERVER is required}"
: "${SS_PORT:?SS_PORT is required}"
: "${SS_CIPHER:=aes-256-gcm}"
: "${SS_PASSWORD:?SS_PASSWORD is required}"

SS_UDP="${SS_UDP:-0}"
SS_LISTEN="${SS_LISTEN:-127.0.0.1:1080}"
SS_WORKER_COUNT="${SS_WORKER_COUNT:-1}"
SS_MAX_CONNECTIONS="${SS_MAX_CONNECTIONS:-1024}"
SS_CONNECT_TIMEOUT="${SS_CONNECT_TIMEOUT:-10}"
SS_CONNECT_RETRIES="${SS_CONNECT_RETRIES:-1}"
SS_RETRY_DELAY_MS="${SS_RETRY_DELAY_MS:-250}"
SS_IDLE_TIMEOUT="${SS_IDLE_TIMEOUT:-900}"
SS_MAX_SEND_BUFFER="${SS_MAX_SEND_BUFFER:-4194304}"
SS_ALLOW_IPS="${SS_ALLOW_IPS:-}"
SS_STATUS_FILE="${SS_STATUS_FILE:-}"
SS_STATUS_INTERVAL="${SS_STATUS_INTERVAL:-10}"
SS_LOG_FILE="${SS_LOG_FILE:-}"
SS_PID_FILE="${SS_PID_FILE:-}"
SS_VERBOSE_LOG="${SS_VERBOSE_LOG:-0}"
SS_DAEMON="${SS_DAEMON:-0}"

set -- \
  "$PHP_BIN" "$APP_ROOT/bin/ss-local" \
  "--server=$SS_SERVER" \
  "--port=$SS_PORT" \
  "--cipher=$SS_CIPHER" \
  "--password=$SS_PASSWORD" \
  "--udp=$SS_UDP" \
  "--listen=$SS_LISTEN" \
  "--worker-count=$SS_WORKER_COUNT" \
  "--max-connections=$SS_MAX_CONNECTIONS" \
  "--connect-timeout=$SS_CONNECT_TIMEOUT" \
  "--connect-retries=$SS_CONNECT_RETRIES" \
  "--retry-delay-ms=$SS_RETRY_DELAY_MS" \
  "--idle-timeout=$SS_IDLE_TIMEOUT" \
  "--max-send-buffer=$SS_MAX_SEND_BUFFER" \
  "--status-interval=$SS_STATUS_INTERVAL"

if [ -n "$SS_ALLOW_IPS" ]; then
  set -- "$@" "--allow-ip=$SS_ALLOW_IPS"
fi

if [ -n "$SS_STATUS_FILE" ]; then
  set -- "$@" "--status-file=$SS_STATUS_FILE"
fi

if [ -n "$SS_LOG_FILE" ]; then
  set -- "$@" "--log-file=$SS_LOG_FILE"
fi

if [ -n "$SS_PID_FILE" ]; then
  set -- "$@" "--pid-file=$SS_PID_FILE"
fi

if [ "$SS_DAEMON" = "1" ]; then
  set -- "$@" "--daemon"
fi

if [ "$SS_VERBOSE_LOG" = "1" ]; then
  set -- "$@" "--verbose-log"
fi

exec "$@"
