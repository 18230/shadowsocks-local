<?php

declare(strict_types=1);

namespace SsLocal\Session;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SsLocal\Config\NodeConfig;
use SsLocal\Crypto\AeadDecryptor;
use SsLocal\Crypto\AeadEncryptor;
use SsLocal\Crypto\Aes256GcmMethod;
use SsLocal\Protocol\AddressCodec;
use SsLocal\Runtime\RunOptions;
use SsLocal\Runtime\ServerStats;
use Throwable;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

final class Socks5Session
{
    private const STATE_GREETING = 'greeting';
    private const STATE_REQUEST = 'request';
    private const STATE_CONNECTING = 'connecting';
    private const STATE_RELAY = 'relay';
    private const STATE_CLOSED = 'closed';

    private string $state = self::STATE_GREETING;

    private string $handshakeBuffer = '';

    private string $pendingClientPayload = '';

    private ?AsyncTcpConnection $remote = null;

    private ?AeadEncryptor $encryptor = null;

    private ?AeadDecryptor $decryptor = null;

    private ?string $targetHost = null;

    private ?int $targetPort = null;

    private bool $closing = false;

    private int $lastActivityAt;

    private int $connectTimerId = 0;

    private int $retryTimerId = 0;

    private int $connectAttempts = 0;

    private int $idleTimerId = 0;

    public function __construct(
        private readonly TcpConnection $client,
        private readonly NodeConfig $node,
        private readonly RunOptions $options,
        private readonly LoggerInterface $logger,
        private readonly ServerStats $stats
    ) {
        $this->lastActivityAt = time();
        $this->idleTimerId = Timer::add(
            max(5.0, min(60.0, $this->options->idleTimeout / 3)),
            function (): void {
                if ($this->state !== self::STATE_CLOSED && $this->isIdle()) {
                    $this->logger->info('Closing idle tunnel.', [
                        'target' => $this->targetHost !== null && $this->targetPort !== null
                            ? sprintf('%s:%d', $this->targetHost, $this->targetPort)
                            : null,
                    ]);
                    $this->closeBothSides();
                }
            }
        );
    }

    public function handleClientData(string $data): void
    {
        if ($this->state === self::STATE_CLOSED) {
            return;
        }

        $this->touch();

        try {
            switch ($this->state) {
                case self::STATE_GREETING:
                case self::STATE_REQUEST:
                    $this->handshakeBuffer .= $data;
                    $this->drainHandshake();
                    break;

                case self::STATE_CONNECTING:
                    $this->bufferClientPayload($data);
                    break;

                case self::STATE_RELAY:
                    $this->forwardPlainPayload($data);
                    break;
            }
        } catch (Throwable $throwable) {
            $this->logger->warning('The local SOCKS5 session failed.', [
                'state' => $this->state,
                'message' => $throwable->getMessage(),
            ]);

            if ($this->state === self::STATE_GREETING) {
                $this->safeSend("\x05\xff");
            } elseif ($this->state === self::STATE_REQUEST) {
                $this->safeSend(AddressCodec::buildReply(0x01));
            }

            $this->closeBothSides();
        }
    }

    public function closeClientSide(): void
    {
        if ($this->closing) {
            return;
        }

        $this->closing = true;
        $this->state = self::STATE_CLOSED;
        $this->clearTimers();

        if ($this->remote !== null) {
            $this->remote->close();
            $this->remote = null;
        }
    }

    private function drainHandshake(): void
    {
        while (true) {
            if ($this->state === self::STATE_GREETING) {
                if (strlen($this->handshakeBuffer) < 2) {
                    return;
                }

                $methodCount = ord($this->handshakeBuffer[1]);
                $required = 2 + $methodCount;
                if (strlen($this->handshakeBuffer) < $required) {
                    return;
                }

                $greeting = substr($this->handshakeBuffer, 0, $required);
                $this->handshakeBuffer = substr($this->handshakeBuffer, $required);

                if (ord($greeting[0]) !== 0x05) {
                    throw new RuntimeException('Unsupported SOCKS protocol version.');
                }

                $methods = substr($greeting, 2);
                if (!str_contains($methods, "\x00")) {
                    $this->safeSend("\x05\xff");
                    $this->closeBothSides();

                    return;
                }

                $this->safeSend("\x05\x00");
                $this->state = self::STATE_REQUEST;
                continue;
            }

            if ($this->state !== self::STATE_REQUEST) {
                return;
            }

            $request = AddressCodec::tryParseSocks5Request($this->handshakeBuffer);
            if ($request === null) {
                return;
            }

            $this->handshakeBuffer = substr($this->handshakeBuffer, $request['consumed']);

            if ($request['cmd'] !== 0x01) {
                $this->safeSend(AddressCodec::buildReply(0x07));
                $this->closeBothSides();

                return;
            }

            $this->targetHost = $request['host'];
            $this->targetPort = $request['port'];
            $this->state = self::STATE_CONNECTING;
            $this->pauseClientRecv();

            if ($this->handshakeBuffer !== '') {
                $this->bufferClientPayload($this->handshakeBuffer);
                $this->handshakeBuffer = '';
            }

            $this->connectRemote();

            return;
        }
    }

    private function connectRemote(): void
    {
        $this->connectAttempts++;

        $remote = new AsyncTcpConnection(
            sprintf('tcp://%s:%d', $this->node->server, $this->node->port),
            [
                'socket' => [
                    'tcp_nodelay' => true,
                    'so_keepalive' => true,
                ],
            ]
        );
        $this->remote = $remote;
        $remote->maxSendBufferSize = $this->options->maxSendBufferSize;
        $this->connectTimerId = Timer::add($this->options->connectTimeout, function () use ($remote): void {
            if ($this->state !== self::STATE_CONNECTING || $this->remote !== $remote) {
                return;
            }

            $this->discardRemote($remote);
            $this->handleConnectFailure('timed out');
        }, [], false);

        $remote->onConnect = function () use ($remote): void {
            if ($this->state !== self::STATE_CONNECTING || $this->remote !== $remote) {
                $remote->close();

                return;
            }

            $this->clearConnectTimer();
            $this->encryptor = new AeadEncryptor($this->node->password);
            $this->decryptor = new AeadDecryptor($this->node->password);

            $initialPayload = AddressCodec::encodeShadowsocksAddress((string) $this->targetHost, (int) $this->targetPort);
            if ($this->pendingClientPayload !== '') {
                $available = Aes256GcmMethod::MAX_CHUNK_LENGTH - strlen($initialPayload);
                $available = max(0, $available);
                $initialPayload .= substr($this->pendingClientPayload, 0, $available);
                $this->pendingClientPayload = substr($this->pendingClientPayload, $available);
            }

            $remote->send($this->encryptor->encryptChunk($initialPayload));
            $this->state = self::STATE_RELAY;
            $this->safeSend(AddressCodec::buildReply(0x00));
            $this->resumeClientRecv();
            $this->flushBufferedClientPayload();
            $this->stats->recordRemoteConnectSuccess();

            $this->logger->info('SOCKS5 tunnel established.', [
                'target' => sprintf('%s:%d', $this->targetHost, $this->targetPort),
                'server' => sprintf('%s:%d', $this->node->server, $this->node->port),
                'attempt' => $this->connectAttempts,
            ]);
        };

        $remote->onMessage = function (AsyncTcpConnection $connection, string $data): void {
            if ($this->remote !== $connection) {
                return;
            }

            $this->touch();

            if ($this->decryptor === null) {
                throw new RuntimeException('The decryptor is not initialized.');
            }

            foreach ($this->decryptor->push($data) as $plainText) {
                $this->safeSend($plainText);
            }
        };

        $remote->onClose = function () use ($remote): void {
            if ($this->remote !== $remote) {
                return;
            }

            $this->clearConnectTimer();
            if ($this->state === self::STATE_CONNECTING) {
                $this->discardRemote($remote);
                $this->handleConnectFailure('closed before handshake completed');

                return;
            }

            $this->closeBothSides();
        };

        $remote->onError = function (AsyncTcpConnection $connection, int $code, string $message): void {
            if ($this->remote !== $connection) {
                return;
            }

            $this->clearConnectTimer();
            $this->discardRemote($connection);
            $this->handleConnectFailure($message, ['code' => $code]);
        };

        $this->wireBackpressure($remote);
        $remote->connect();
    }

    private function wireBackpressure(AsyncTcpConnection $remote): void
    {
        $this->client->onBufferFull = function () use ($remote): void {
            if (method_exists($remote, 'pauseRecv')) {
                $remote->pauseRecv();
            }
        };

        $this->client->onBufferDrain = function () use ($remote): void {
            if (method_exists($remote, 'resumeRecv')) {
                $remote->resumeRecv();
            }
        };

        $remote->onBufferFull = function (): void {
            $this->pauseClientRecv();
            $this->logger->debug('Remote send buffer is full.');
        };

        $remote->onBufferDrain = function (): void {
            $this->resumeClientRecv();
            $this->logger->debug('Remote send buffer drained.');
        };
    }

    private function forwardPlainPayload(string $data): void
    {
        if ($this->remote === null || $this->encryptor === null) {
            throw new RuntimeException('The remote relay path is not initialized.');
        }

        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $chunk = substr($data, $offset, Aes256GcmMethod::MAX_CHUNK_LENGTH);
            $offset += strlen($chunk);
            $this->remote->send($this->encryptor->encryptChunk($chunk));
        }
    }

    private function flushBufferedClientPayload(): void
    {
        if ($this->pendingClientPayload === '') {
            return;
        }

        $payload = $this->pendingClientPayload;
        $this->pendingClientPayload = '';
        $this->forwardPlainPayload($payload);
    }

    private function bufferClientPayload(string $data): void
    {
        $this->pendingClientPayload .= $data;

        if (strlen($this->pendingClientPayload) > $this->options->maxSendBufferSize) {
            throw new RuntimeException('Buffered client payload exceeded the configured max send buffer.');
        }
    }

    private function touch(): void
    {
        $this->lastActivityAt = time();
    }

    private function isIdle(): bool
    {
        return (time() - $this->lastActivityAt) >= $this->options->idleTimeout;
    }

    private function safeSend(string $data): void
    {
        if ($this->state === self::STATE_CLOSED) {
            return;
        }

        $this->client->send($data);
    }

    private function closeBothSides(): void
    {
        if ($this->closing) {
            return;
        }

        $this->closing = true;
        $this->state = self::STATE_CLOSED;
        $this->clearTimers();

        if ($this->remote !== null) {
            $remote = $this->remote;
            $this->remote = null;
            $remote->close();
        }

        $this->client->close();
    }

    private function pauseClientRecv(): void
    {
        $this->client->pauseRecv();
    }

    private function resumeClientRecv(): void
    {
        if ($this->state !== self::STATE_CLOSED) {
            $this->client->resumeRecv();
        }
    }

    private function clearTimers(): void
    {
        $this->clearConnectTimer();
        $this->clearRetryTimer();

        if ($this->idleTimerId !== 0) {
            Timer::del($this->idleTimerId);
            $this->idleTimerId = 0;
        }
    }

    private function clearConnectTimer(): void
    {
        if ($this->connectTimerId !== 0) {
            Timer::del($this->connectTimerId);
            $this->connectTimerId = 0;
        }
    }

    private function clearRetryTimer(): void
    {
        if ($this->retryTimerId !== 0) {
            Timer::del($this->retryTimerId);
            $this->retryTimerId = 0;
        }
    }

    /**
     * @param array<string, int|string> $extra
     */
    private function handleConnectFailure(string $message, array $extra = []): void
    {
        if ($this->state !== self::STATE_CONNECTING || $this->closing) {
            return;
        }

        $this->stats->recordRemoteConnectFailure();
        $context = $extra + [
            'message' => $message,
            'target' => sprintf('%s:%d', $this->targetHost, $this->targetPort),
            'server' => sprintf('%s:%d', $this->node->server, $this->node->port),
            'attempt' => $this->connectAttempts,
        ];

        if (($this->connectAttempts - 1) < $this->options->connectRetries) {
            $this->stats->recordRemoteConnectRetry();
            $delaySeconds = max(0.0, $this->options->retryDelayMs / 1000);
            $this->logger->warning('Remote Shadowsocks connection failed, scheduling retry.', $context + [
                'retry_delay_ms' => $this->options->retryDelayMs,
            ]);

            $this->retryTimerId = Timer::add($delaySeconds, function (): void {
                $this->retryTimerId = 0;

                if ($this->state !== self::STATE_CONNECTING || $this->closing) {
                    return;
                }

                $this->connectRemote();
            }, [], false);

            return;
        }

        $this->logger->warning('Remote Shadowsocks connection failed.', $context);
        $this->safeSend(AddressCodec::buildReply(0x05));
        $this->closeBothSides();
    }

    private function discardRemote(AsyncTcpConnection $remote): void
    {
        if ($this->remote !== $remote) {
            return;
        }

        $this->remote = null;
        $remote->close();
    }
}
