<?php

namespace Mandiri\Qris;

use Illuminate\Support\ServiceProvider;

class MandiriQrisServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/mandiri-qris.php' => config_path('mandiri-qris.php'),
        ], 'mandiri-qris-config');

        // Publish migrations
        if (! class_exists('CreateMandiriQrisPaymentsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_mandiri_qris_payments_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_mandiri_qris_payments_table.php'),
            ], 'mandiri-qris-migrations');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/mandiri-qris.php', 'mandiri-qris'
        );

        // Register the main class
        $this->app->singleton('mandiri-qris', function ($app) {
            return new MandiriQrisClient(config('mandiri-qris'));
        });
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
