<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    | 'sandbox' during development, 'production' when going live.
    */
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Core Credentials
    |--------------------------------------------------------------------------
    | Obtain from https://developer.safaricom.co.ke after creating an app.
    */
    'consumer_key'    => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Business Shortcode & Passkey
    |--------------------------------------------------------------------------
    | Shortcode: your Paybill or Till number.
    | Passkey: provided by Safaricom for Lipa na M-Pesa (STK Push).
    */
    'shortcode' => env('MPESA_SHORTCODE'),
    'passkey'   => env('MPESA_PASSKEY'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    | Must be publicly reachable HTTPS endpoints.
    | Set per-request to override these defaults.
    */
    'callback_url' => env('MPESA_CALLBACK_URL'),   // STK Push
    'result_url'   => env('MPESA_RESULT_URL'),      // B2C, B2B, Status, Reversal, Balance
    'timeout_url'  => env('MPESA_TIMEOUT_URL'),     // async timeout fallback

    /*
    |--------------------------------------------------------------------------
    | Operator Credentials (required for B2C, B2B, Reversal, Balance)
    |--------------------------------------------------------------------------
    | initiator_name:      API operator username from Daraja portal.
    | security_credential: Initiator password encrypted with Safaricom's cert.
    |                      Generate once with HasSecurityCredential::generateSecurityCredential().
    */
    'initiator_name'      => env('MPESA_INITIATOR_NAME'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('MPESA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist (VerifyMpesaIp middleware)
    |--------------------------------------------------------------------------
    | Safaricom's known Daraja IP ranges. Update if they publish new ranges.
    | Set to an empty array to disable IP checking (not recommended for production).
    */
    'safaricom_ips' => [
        // Production
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
        // Sandbox — allow all (set via APP_ENV check in middleware)
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    | Prefix and middleware for the auto-registered webhook routes.
    | Set 'register_routes' to false to define your own routes manually.
    */
    'routes' => [
        'register_routes' => true,
        'prefix'          => 'mpesa',
        'middleware'       => ['api'],
    ],

];
