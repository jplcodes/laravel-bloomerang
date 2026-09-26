<?php

namespace JplCodes\Bloomerang;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use JplCodes\Bloomerang\Auth\ApiKey;

/**
 * Registers the Bloomerang client with the application container.
 */
final class BloomerangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bloomerang.php', 'bloomerang');

        $this->app->scoped(Bloomerang::class, function (Application $app): Bloomerang {
            $config = $app['config']->get('bloomerang', []);

            $baseUrl = $config['base_url'] ?? null;

            if ($baseUrl === null || $baseUrl === '') {
                $baseUrl = Bloomerang::DEFAULT_BASE_URL;
            }

            $channel = $config['logging']['channel'] ?? null;
            $logger = $channel !== null && $channel !== '' ? $app['log']->channel($channel) : $app['log'];

            return new Bloomerang(
                $app->make(Factory::class),
                $logger,
                $baseUrl,
                $config['request_mode'] ?? [],
                $config['job_mode'] ?? [],
                (bool) ($config['logging']['enabled'] ?? true),
                new ApiKey($config['api_key'] ?? null),
            );
        });

        $this->app->alias(Bloomerang::class, 'bloomerang');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/bloomerang.php' => config_path('bloomerang.php'),
        ], 'bloomerang-config');
    }
}
