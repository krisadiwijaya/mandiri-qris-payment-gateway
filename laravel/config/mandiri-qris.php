<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mandiri QRIS Credentials
    |--------------------------------------------------------------------------
    |
    | Your Mandiri QRIS OAuth 2.0 credentials
    |
    */

    'client_id' => env('MANDIRI_CLIENT_ID', ''),
    'client_secret' => env('MANDIRI_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Base URL for Mandiri QRIS API
    |
    */

    'base_url' => env('MANDIRI_BASE_URL', 'https://api.mandiri.co.id'),
    'sandbox' => env('MANDIRI_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Merchant Configuration
    |--------------------------------------------------------------------------
    |
    | Your merchant and terminal identifiers
    |
    */

    'merchant_id' => env('MANDIRI_MERCHANT_ID', ''),
    'terminal_id' => env('MANDIRI_TERMINAL_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Webhook settings for payment notifications
    |
    */

    'webhook' => [
        'path' => env('MANDIRI_WEBHOOK_PATH', '/webhook/mandiri-qris'),
        'verify_signature' => env('MANDIRI_WEBHOOK_VERIFY_SIGNATURE', true),
    ],
];
