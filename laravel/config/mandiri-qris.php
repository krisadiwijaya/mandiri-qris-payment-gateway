<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mandiri QRIS Environment
    |--------------------------------------------------------------------------
    |
    | This value determines which Mandiri API environment to use.
    | Options: 'sandbox' or 'production'
    |
    */

    'environment' => env('MANDIRI_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Client Credentials
    |--------------------------------------------------------------------------
    |
    | Your Mandiri API client credentials (Client ID and Secret)
    |
    */

    'client_id' => env('MANDIRI_CLIENT_ID'),
    'client_secret' => env('MANDIRI_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Information
    |--------------------------------------------------------------------------
    |
    | Your merchant NMID and display information
    |
    */

    'merchant_nmid' => env('MANDIRI_MERCHANT_NMID'),
    'merchant_name' => env('MANDIRI_MERCHANT_NAME', 'Toko Online'),
    'merchant_city' => env('MANDIRI_MERCHANT_CITY', 'Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | QRIS Configuration
    |--------------------------------------------------------------------------
    |
    | QRIS expiry time in minutes and HTTP timeout in seconds
    |
    */

    'qris_expiry_minutes' => env('MANDIRI_QRIS_EXPIRY_MINUTES', 5),
    'timeout' => env('MANDIRI_TIMEOUT', 30),

];
