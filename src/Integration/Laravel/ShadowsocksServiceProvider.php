<?php

declare(strict_types=1);

namespace SsLocal\Integration\Laravel;

use SsLocal\Support\ProxyEndpoint;
use SsLocal\Support\ProxyService;
use SsLocal\Support\TlsSettings;

class ShadowsocksServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/laravel/ss-local.php', 'ss-local');

        $this->app->singleton(TlsSettings::class, function ($app): TlsSettings {
            return TlsSettings::fromArray((array) $app['config']->get('ss-local.tls', []));
        });

        $this->app->singleton(ProxyEndpoint::class, function ($app): ProxyEndpoint {
            return ProxyEndpoint::fromListenAddress((string) $app['config']->get('ss-local.listen', '127.0.0.1:1080'));
        });

        $this->app->singleton(ProxyService::class, function ($app): ProxyService {
            return new ProxyService(
                $app->make(ProxyEndpoint::class),
                $app->make(TlsSettings::class),
            );
        });

        $this->app->alias(ProxyService::class, 'ss-local.proxy');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/laravel/ss-local.php' => config_path('ss-local.php'),
            ], 'ss-local-config');
        }
    }
}
