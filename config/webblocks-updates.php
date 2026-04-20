<?php

return [
    'enabled' => env('WEBBLOCKS_UPDATES_ENABLED', true),
    'api_version' => '1',
    'client' => [
        'enabled' => env('WEBBLOCKS_UPDATE_CLIENT_ENABLED', true),
        'server_url' => env('WEBBLOCKS_UPDATES_CLIENT_SERVER_URL', 'https://updates.webblocksui.com'),
        'channel' => env('WEBBLOCKS_UPDATE_CLIENT_CHANNEL', 'stable'),
        'product' => env('WEBBLOCKS_UPDATE_CLIENT_PRODUCT', 'webblocks-cms'),
        'current_version' => env('WEBBLOCKS_UPDATE_CLIENT_CURRENT_VERSION', env('APP_VERSION', '0.1.5')),
        'site_url' => env('WEBBLOCKS_UPDATE_CLIENT_SITE_URL', env('APP_URL', 'http://localhost')),
        'instance_id' => env('WEBBLOCKS_UPDATE_CLIENT_INSTANCE_ID'),
        'timeout_seconds' => env('WEBBLOCKS_UPDATE_CLIENT_TIMEOUT_SECONDS', 5),
        'connect_timeout_seconds' => env('WEBBLOCKS_UPDATE_CLIENT_CONNECT_TIMEOUT_SECONDS', 3),
        'retry_times' => env('WEBBLOCKS_UPDATE_CLIENT_RETRY_TIMES', 0),
        'retry_sleep_milliseconds' => env('WEBBLOCKS_UPDATE_CLIENT_RETRY_SLEEP_MS', 150),
    ],
    'installer' => [
        'target_path' => env('WEBBLOCKS_UPDATE_TARGET_PATH', base_path()),
        'workspace_root' => env('WEBBLOCKS_UPDATE_WORKSPACE_ROOT', 'app/system-updates'),
        'download_timeout_seconds' => env('WEBBLOCKS_UPDATE_DOWNLOAD_TIMEOUT_SECONDS', 120),
        'command_timeout_seconds' => env('WEBBLOCKS_UPDATE_COMMAND_TIMEOUT_SECONDS', 600),
        'lock_name' => env('WEBBLOCKS_UPDATE_LOCK_NAME', 'system-updates:run'),
        'lock_ttl_seconds' => env('WEBBLOCKS_UPDATE_LOCK_TTL_SECONDS', 900),
        'excluded_paths' => [
            '.git',
            '.github',
            '.ddev',
            'storage',
            'bootstrap/cache',
            'node_modules',
            'vendor',
            'public/storage',
            'public/build',
        ],
    ],
];
