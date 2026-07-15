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

    'zeptomail' => [
        'url' => env('ZEPTOMAIL_URL', 'https://api.zeptomail.com/v1.1/email'),
        'token' => env('ZEPTOMAIL_TOKEN'),
    ],

    'termii' => [
        'url' => env('TERMII_URL', 'https://v3.api.termii.com'),
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'CorpsLink'),
    ],

    'paystack' => [
        'url' => env('PAYSTACK_URL', 'https://api.paystack.co'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        // Optional per-transaction override of the dashboard's default
        // callback URL — same role as Flutterwave's redirect_url below.
        // Without it Paystack falls back to whatever's set in Settings ›
        // Preferences, which may not exist yet / may point somewhere stale.
        'callback_url' => env('PAYSTACK_CALLBACK_URL', env('APP_URL', 'http://localhost').'/payments/callback'),
    ],

    'flutterwave' => [
        'url' => env('FLUTTERWAVE_URL', 'https://api.flutterwave.com/v3'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'webhook_hash' => env('FLUTTERWAVE_WEBHOOK_HASH'),
        // Flutterwave requires this — where it redirects the browser after
        // the hosted checkout page completes. The client app handles the
        // landing (deep link or lightweight page); actual payment
        // confirmation still goes through POST /payments/{reference}/verify
        // or the webhook, not this redirect.
        'redirect_url' => env('FLUTTERWAVE_REDIRECT_URL', env('APP_URL', 'http://localhost').'/payments/callback'),
    ],

    'monnify' => [
        'url' => env('MONNIFY_URL', 'https://sandbox.monnify.com'),
        'api_key' => env('MONNIFY_API_KEY'),
        'secret_key' => env('MONNIFY_SECRET_KEY'),
        // Merchant contract identifier from the Monnify dashboard — required
        // on every init-transaction call, not optional like the others' keys.
        'contract_code' => env('MONNIFY_CONTRACT_CODE'),
        'redirect_url' => env('MONNIFY_REDIRECT_URL', env('APP_URL', 'http://localhost').'/payments/callback'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

];
