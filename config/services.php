<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    
    'virtuohr' => [
        'token' => env('VIRTUOHR_API_TOKEN'),
        'webhook_secret' => env('VIRTUOHR_WEBHOOK_SECRET'),
        'webhook_tolerance' => (int) env('VIRTUOHR_WEBHOOK_TOLERANCE', 300),
        'webhook_signature_header' => env('VIRTUOHR_WEBHOOK_SIGNATURE_HEADER', 'X-VirtuoHR-Signature'),
        'webhook_timestamp_header' => env('VIRTUOHR_WEBHOOK_TIMESTAMP_HEADER', 'X-VirtuoHR-Timestamp'),
        'webhook_idempotency_header' => env('VIRTUOHR_WEBHOOK_IDEMPOTENCY_HEADER', 'Idempotency-Key'),
    ],

];
