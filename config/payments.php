<?php

return [
    'default' => env('PAYMENT_DRIVER', 'myfatoorah'),

    'callback' => [
        'success_url' => env('PAYMENT_CALLBACK_SUCCESS', '/payment/success'),
        'error_url' => env('PAYMENT_CALLBACK_ERROR', '/payment/error'),
        'cancel_url' => env('PAYMENT_CALLBACK_CANCEL', '/payment/cancel'),
    ],

    'webhook' => [
        'enabled' => env('PAYMENT_WEBHOOK_ENABLED', true),
        'token' => env('PAYMENT_WEBHOOK_TOKEN'), // Token عام (يُستخدم إذا لم يكن هناك token خاص للـ gateway)
        'route_prefix' => env('PAYMENT_WEBHOOK_PREFIX', 'payments/webhook'),
    ],

    'drivers' => [
        'myfatoorah' => [
            'base_url' => env('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
            'bearer' => env('MYFATOORAH_TOKEN'),
            'webhook_token' => env('MYFATOORAH_WEBHOOK_TOKEN'),
            'webhook_route' => env('MYFATOORAH_WEBHOOK_ROUTE', null), // مثال: 'myfatoorah-webhook' أو 'payments/myfatoorah/webhook'
            'webhook_methods' => ['POST', 'GET'], // HTTP methods المقبولة
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
            'actions' => [
                'pay' => ['method' => 'POST', 'path' => '/v2/ExecutePayment'],
                'refund' => ['method' => 'POST', 'path' => '/v2/MakeRefund'],
                'status' => ['method' => 'GET', 'path' => '/v2/GetPaymentStatus'],
            ],
        ],
        'paymob' => [
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
            'bearer' => env('PAYMOB_TOKEN'),
            'webhook_token' => env('PAYMOB_WEBHOOK_TOKEN'),
            'webhook_route' => env('PAYMOB_WEBHOOK_ROUTE', null), // مثال: 'paymob-webhook' أو 'payments/paymob/webhook'
            'webhook_methods' => ['POST'], // Paymob يستخدم POST فقط
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
            'actions' => [
                'pay' => ['method' => 'POST', 'path' => '/acceptance/payment_keys'],
                'refund' => ['method' => 'POST', 'path' => '/acceptance/payments/refund'],
                'status' => ['method' => 'GET', 'path' => '/acceptance/transactions'],
            ],
        ],
    ],
];
