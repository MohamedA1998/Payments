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

    'default' => env('PAYMENT_DRIVER', 'stripe'),

    'drivers' => [
        'myfatoorah' => [
            'base_url' => env('MYFATOORAH_BASE_URL', 'https://api.myfatoorah.com/v2'),
            'bearer' => env('MYFATOORAH_TOKEN'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ],

        'paymob' => [
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
            'bearer' => env('PAYMOB_TOKEN'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
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
        ],

        'stripe' => [
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
            'bearer' => env('STRIPE_SECRET'),
            'headers' => [
                'Stripe-Version' => env('STRIPE_API_VERSION', '2024-06-20'),
            ],
            'timeout' => 30,
        ],
    ],
];
