<?php

return [
    'enabled' => env('WEBBLOCKS_UPDATES_ENABLED', true),
    'api_version' => '1',
    'products' => [
        'webblocks-cms',
    ],
    'channels' => [
        'stable',
        'beta',
        'nightly',
    ],
    'server' => [
        'enabled' => env('WEBBLOCKS_UPDATE_SERVER_ENABLED', true),
        'service_name' => env('WEBBLOCKS_UPDATE_SERVER_NAME', 'WebBlocks Update Server'),
        'base_url' => env('WEBBLOCKS_UPDATE_SERVER_BASE_URL', env('APP_URL', 'https://updates.webblocksui.com')),
        'default_channel' => env('WEBBLOCKS_UPDATE_SERVER_DEFAULT_CHANNEL', 'stable'),
    ],
    'client' => [
        'enabled' => env('WEBBLOCKS_UPDATE_CLIENT_ENABLED', true),
        'server_url' => env('WEBBLOCKS_UPDATES_CLIENT_SERVER_URL', 'https://updates.webblocksui.com'),
        'channel' => env('WEBBLOCKS_UPDATE_CLIENT_CHANNEL', 'stable'),
        'product' => env('WEBBLOCKS_UPDATE_CLIENT_PRODUCT', 'webblocks-cms'),
        'current_version' => env('WEBBLOCKS_UPDATE_CLIENT_CURRENT_VERSION', env('APP_VERSION', '0.1.2')),
        'site_url' => env('WEBBLOCKS_UPDATE_CLIENT_SITE_URL', env('APP_URL', 'http://localhost')),
        'instance_id' => env('WEBBLOCKS_UPDATE_CLIENT_INSTANCE_ID'),
        'timeout_seconds' => env('WEBBLOCKS_UPDATE_CLIENT_TIMEOUT_SECONDS', 5),
        'connect_timeout_seconds' => env('WEBBLOCKS_UPDATE_CLIENT_CONNECT_TIMEOUT_SECONDS', 3),
        'retry_times' => env('WEBBLOCKS_UPDATE_CLIENT_RETRY_TIMES', 0),
        'retry_sleep_milliseconds' => env('WEBBLOCKS_UPDATE_CLIENT_RETRY_SLEEP_MS', 150),
    ],
    'publish' => [
        'enabled' => env('WEBBLOCKS_UPDATES_PUBLISH_ENABLED', true),
        'server_url' => env('WEBBLOCKS_UPDATES_PUBLISH_SERVER_URL', 'https://updates.webblocksui.com'),
        'token' => env('WEBBLOCKS_UPDATES_PUBLISH_TOKEN'),
        'product' => env('WEBBLOCKS_UPDATES_PUBLISH_PRODUCT', 'webblocks-cms'),
        'channel' => env('WEBBLOCKS_UPDATES_PUBLISH_CHANNEL', 'stable'),
        'timeout' => env('WEBBLOCKS_UPDATES_PUBLISH_TIMEOUT', 30),
    ],
];
