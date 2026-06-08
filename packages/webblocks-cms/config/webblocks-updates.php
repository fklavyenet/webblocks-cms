<?php

use WebBlocks\Cms\Support\WebBlocks;

return [
    'enabled' => env('WEBBLOCKS_UPDATES_ENABLED', true),
    'server_url' => env('WEBBLOCKS_UPDATES_SERVER_URL', 'https://updates.webblocksui.com'),
    'channel' => env('WEBBLOCKS_UPDATES_CHANNEL', 'stable'),
    'api_version' => '1',
    'product' => WebBlocks::HANDLE,
    'current_version' => WebBlocks::VERSION,
    'site_url' => env('APP_URL', 'http://localhost'),
    'instance_id' => null,
    'timeout_seconds' => 5,
    'connect_timeout_seconds' => 3,
    'retry_times' => 0,
    'retry_sleep_milliseconds' => 150,
    'pending_cache_ttl_seconds' => 3600,
    'runs' => [
        'keep' => env('WEBBLOCKS_UPDATES_RUNS_KEEP', 5),
    ],
    'publisher' => [
        'url' => env('WEBBLOCKS_PUBLISHER_URL', 'https://publisher.webblocksui.com/api/updates/publish'),
        'token' => env('WEBBLOCKS_PUBLISHER_TOKEN'),
        'product' => env('WEBBLOCKS_PUBLISHER_PRODUCT', WebBlocks::HANDLE),
        'channel' => env('WEBBLOCKS_PUBLISHER_CHANNEL', 'stable'),
        'timeout_seconds' => 120,
        'connect_timeout_seconds' => 5,
    ],
    'installer' => [
        'target_path' => base_path(),
        'workspace_root' => 'app/system-updates',
        'download_timeout_seconds' => 120,
        'command_timeout_seconds' => 600,
        'migration_strategy' => env('WEBBLOCKS_UPDATES_MIGRATION_STRATEGY', 'auto'),
        'package_update_migrations_path' => 'packages/webblocks-cms/database/migrations/updates',
        'lock_name' => 'system-updates:run',
        'lock_ttl_seconds' => 900,
        'excluded_paths' => [
            '.git',
            '.github',
            'project',
            'storage',
            'bootstrap/cache',
            'vendor',
            'public/storage',
        ],
    ],
];
