<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Payment gateway Tripay. Sandbox: https://tripay.co.id/api-sandbox
    'tripay' => [
        'base_url' => env('TRIPAY_BASE_URL', 'https://tripay.co.id/api-sandbox'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
        'api_key' => env('TRIPAY_API_KEY'),
        'private_key' => env('TRIPAY_PRIVATE_KEY'),
        'payment_method' => env('TRIPAY_PAYMENT_METHOD', 'QRIS'),
        'expiry_minutes' => (int) env('TRIPAY_EXPIRY_MINUTES', 15),
        'return_url' => env('TRIPAY_RETURN_URL'),
    ],

    // Firebase Authentication (login Google customer). Cukup project id untuk verifikasi token.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
