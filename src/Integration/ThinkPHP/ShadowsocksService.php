<?php

declare(strict_types=1);

namespace SsLocal\Integration\ThinkPHP;

use SsLocal\Support\ProxyEndpoint;
use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

class ShadowsocksService extends \think\Service
{
    public function register(): void
    {
        $this->app->bind(TlsSettings::class, function (): TlsSettings {
            return TlsSettings::fromArray((array) $this->app->config->get('ss_local.tls', []));
        });

        $this->app->bind(ProxyEndpoint::class, function (): ProxyEndpoint {
            return ProxyEndpoint::fromListenAddress((string) $this->app->config->get('ss_local.listen', '127.0.0.1:1080'));
        });

        $this->app->bind(ProxyService::class, function (): ProxyService {
            return new ProxyService(
                $this->app->make(ProxyEndpoint::class),
                $this->app->make(TlsSettings::class),
            );
        });
    }
}
