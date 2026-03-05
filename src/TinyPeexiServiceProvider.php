<?php

namespace Tekkenking\TinyPeexi;

use Illuminate\Support\ServiceProvider;
use Tekkenking\TinyPeexi\Services\TinyPeexiClient;

class TinyPeexiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the configuration from the package with the application's published copy.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/tinypeexi.php',
            'tinypeexi'
        );

        // Bind the TinyPeexiClient singleton so it can be resolved via dependency injection
        // or the Facade.
        $this->app->singleton('tinypeexi', function ($app) {
            return new TinyPeexiClient(config('tinypeexi'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Allow developers to publish the config file using:
        // php artisan vendor:publish --provider="Tekkenking\TinyPeexi\TinyPeexiServiceProvider"
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tinypeexi.php' => config_path('tinypeexi.php'),
            ], 'tinypeexi-config');
        }
    }
}
