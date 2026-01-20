<?php

namespace Payments;

use Illuminate\Support\ServiceProvider;
use Payments\Http\PaymentsHttpClient;

class PaymentsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payments.php',
            'payments'
        );

        // Singleton HTTP client shared by all drivers
        $this->app->singleton(PaymentsHttpClient::class, function ($app) {
            return new PaymentsHttpClient($app['config']->get('payments', []));
        });

        // Register the Payments facade root as a singleton
        $this->app->singleton('payments', function ($app) {
            return new Payments($app->make(PaymentsHttpClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/payments.php' => config_path('payments.php'),
        ], 'payments-config');
    }
}
