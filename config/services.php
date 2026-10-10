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

    // Firebase: project id untuk verifikasi login Google customer; credentials (path file service account JSON)
    // untuk push notifikasi FCM ke tablet kasir/dapur. Channel & suara harus sama dengan yang dibuat app Flutter.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'android_channel' => 'order_masuk',
        'android_sound' => 'notif_order_masuk',
    ],

    // WhatsApp gateway Fonnte (kirim struk pesanan ke customer).
    'fonnte' => [
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'token' => env('FONNTE_TOKEN'),
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
