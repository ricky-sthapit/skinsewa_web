<?php

return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'resend' => ['key' => env('RESEND_KEY')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'slack' => ['notifications' => ['bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'), 'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL')]],
    'chat_webhook' => [
        'url' => env('CHAT_WEBHOOK_URL'),
        'secret' => env('CHAT_WEBHOOK_SECRET'),
        'timeout' => env('CHAT_WEBHOOK_TIMEOUT', 5),
    ],
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
];
