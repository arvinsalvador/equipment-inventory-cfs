<?php

use App\Models\BrowserPushSubscription;

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@seims.site'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => BrowserPushSubscription::class,
    'table_name' => 'browser_push_subscriptions',
    'database_connection' => env('DB_CONNECTION', 'mysql'),
    'client_options' => [],
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),
];
