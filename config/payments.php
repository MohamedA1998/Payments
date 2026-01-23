<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payments Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the payments package.
    |
    */

    'default' => env('PAYMENT_DRIVER', 'myfatoorah'),

    'drivers' => [
        'myfatoorah' => [
            // Test: https://apitest.myfatoorah.com
            // Live: https://api.myfatoorah.com
            'base_url' => env('MYFATOORAH_BASE_URL', 'https://apitest.myfatoorah.com'),
            'bearer' => env('MYFATOORAH_TOKEN'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'actions' => [
                'pay' => [
                    'method' => 'POST',
                    'path' => '/v2/ExecutePayment',
                ],
                'refund' => [
                    'method' => 'POST',
                    'path' => '/v2/MakeRefund',
                ],
                'status' => [
                    'method' => 'GET',
                    'path' => '/v2/GetPaymentStatus',
                ],
            ],
        ],

        'paymob' => [
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
            'bearer' => env('PAYMOB_TOKEN'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'actions' => [
                'pay' => [
                    'method' => 'POST',
                    'path' => '/acceptance/payment_keys',
                ],
                'refund' => [
                    'method' => 'POST',
                    'path' => '/acceptance/payments/refund',
                ],
                'status' => [
                    'method' => 'GET',
                    'path' => '/acceptance/transactions',
                ],
            ],
        ],

        'paypal' => [
            'base_url' => env('PAYPAL_BASE_URL', 'https://api.paypal.com'),
            'basic_auth' => [
                'username' => env('PAYPAL_CLIENT_ID'),
                'password' => env('PAYPAL_CLIENT_SECRET'),
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'actions' => [
                'pay' => [
                    'method' => 'POST',
                    'path' => '/v2/checkout/orders',
                ],
                'refund' => [
                    'method' => 'POST',
                    'path' => '/v2/payments/captures/{capture_id}/refund',
                    'placeholders' => [
                        'capture_id' => 'capture_id',
                    ],
                ],
                'status' => [
                    'method' => 'GET',
                    'path' => '/v2/checkout/orders/{order_id}',
                    'placeholders' => [
                        'order_id' => 'order_id',
                    ],
                ],
            ],
        ],

        'stripe' => [
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
            'bearer' => env('STRIPE_SECRET'),
            'headers' => [
                'Stripe-Version' => env('STRIPE_API_VERSION', '2024-06-20'),
            ],
            'timeout' => 30,
            'actions' => [
                'pay' => [
                    'method' => 'POST',
                    'path' => '/v1/payment_intents',
                ],
                'refund' => [
                    'method' => 'POST',
                    'path' => '/v1/refunds',
                ],
                'status' => [
                    'method' => 'GET',
                    'path' => '/v1/payment_intents/{id}',
                    'placeholders' => [
                        'id' => 'id',
                    ],
                ],
            ],
        ],
    ],
];
