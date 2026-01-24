<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Payments\Http\Controllers\PaymentCallbackController;
use Payments\Http\Controllers\PaymentWebhookController;

// Callback Routes
Route::prefix('payments/callback')->name('payments.callback.')->group(function () {
    Route::get('/{driver}/success', [PaymentCallbackController::class, 'handle'])->defaults('status', 'success')->name('success');
    Route::get('/{driver}/error', [PaymentCallbackController::class, 'handle'])->defaults('status', 'error')->name('error');
    Route::get('/{driver}/cancel', [PaymentCallbackController::class, 'handle'])->defaults('status', 'cancel')->name('cancel');
    Route::get('/{driver}/{status}', [PaymentCallbackController::class, 'handle'])->name('status');
});

// Webhook Routes - Default routes (إذا لم يكن هناك route مخصص)
Route::prefix(config('payments.webhook.route_prefix', 'payments/webhook'))->name('payments.webhook.')->group(function () {
    Route::post('/{driver}', [PaymentWebhookController::class, 'handle'])->name('handle');
    Route::get('/{driver}', [PaymentWebhookController::class, 'handle'])->name('handle.get');
});

// Webhook Routes - Custom routes لكل gateway (إذا كان محدد في config)
$drivers = config('payments.drivers', []);
foreach ($drivers as $driverName => $driverConfig) {
    if (isset($driverConfig['webhook_route']) && $driverConfig['webhook_route']) {
        $webhookRoute = $driverConfig['webhook_route'];
        $webhookMethods = $driverConfig['webhook_methods'] ?? ['POST', 'GET'];
        $controller = PaymentWebhookController::class;
        
        // إنشاء routes لكل HTTP method
        if (in_array('POST', $webhookMethods)) {
            Route::post($webhookRoute, [$controller, 'handle'])->defaults('driver', $driverName)->name("payments.webhook.{$driverName}.post");
        }
        if (in_array('GET', $webhookMethods)) {
            Route::get($webhookRoute, [$controller, 'handle'])->defaults('driver', $driverName)->name("payments.webhook.{$driverName}.get");
        }
        if (in_array('PUT', $webhookMethods)) {
            Route::put($webhookRoute, [$controller, 'handle'])->defaults('driver', $driverName)->name("payments.webhook.{$driverName}.put");
        }
    }
}
