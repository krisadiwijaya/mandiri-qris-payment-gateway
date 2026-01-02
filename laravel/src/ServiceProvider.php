<?php

namespace MandiriQris\Laravel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mandiri-qris.php', 'mandiri-qris'
        );

        $this->app->singleton('mandiri-qris', function ($app) {
            return new \MandiriQris\Laravel\Client(config('mandiri-qris'));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mandiri-qris.php' => config_path('mandiri-qris.php'),
            ], 'mandiri-qris-config');
        }

        // Register webhook route if enabled
        if (config('mandiri-qris.webhook.path')) {
            $this->loadRoutesFrom(__DIR__ . '/routes.php');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['mandiri-qris'];
    }
}
