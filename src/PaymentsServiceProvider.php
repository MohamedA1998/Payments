<?php

namespace Payments;

use Illuminate\Support\ServiceProvider;
use Payments\Http\PaymentsHttpClient;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payments.php', 'payments');
        $this->app->singleton(PaymentsHttpClient::class, fn($app) => new PaymentsHttpClient($app['config']->get('payments', [])));
        $this->app->singleton('payments', fn($app) => new Payments($app->make(PaymentsHttpClient::class)));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/payments.php' => config_path('payments.php')], 'payments-config');
        $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'payments-migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
