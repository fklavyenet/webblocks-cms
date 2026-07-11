<?php

use WebBlocks\Cms\Support\Updates\ReleaseDefaults;
use WebBlocks\Cms\Support\WebBlocks;

return [
    'enabled' => env('WEBBLOCKS_UPDATES_ENABLED', true),
    'server_url' => ReleaseDefaults::SERVER_URL,
    'latest_path' => ReleaseDefaults::LATEST_PATH,
    'channel' => ReleaseDefaults::CHANNEL,
    'api_version' => '1',
    'product' => ReleaseDefaults::PRODUCT_KEY,
    'current_version' => WebBlocks::VERSION,
    'telemetry' => [
        'enabled' => env('WEBBLOCKS_TELEMETRY', true),
        'schema_version' => '1',
    ],
    'timeout_seconds' => 5,
    'connect_timeout_seconds' => 3,
    'retry_times' => 0,
    'retry_sleep_milliseconds' => 150,
    'indicator_cache_ttl_seconds' => 3600,
    'indicator_inactive_cache_ttl_seconds' => 60,
    'pending_cache_ttl_seconds' => 3600,
    'runs' => [
        'keep' => env('WEBBLOCKS_UPDATES_RUNS_KEEP', 5),
    ],
    'publisher' => [
        'url' => ReleaseDefaults::publishUrl(),
        'token' => env('WEBBLOCKS_PUBLISHER_TOKEN'),
        'product' => ReleaseDefaults::PRODUCT_KEY,
        'channel' => ReleaseDefaults::CHANNEL,
        'timeout_seconds' => 120,
        'connect_timeout_seconds' => 5,
    ],
    'signature' => [
        // Base64 Ed25519 public key that installs use to verify signed releases.
        // Empty disables enforcement (checksum verification still applies).
        'public_key' => env('WEBBLOCKS_UPDATE_PUBLIC_KEY', ReleaseDefaults::UPDATE_PUBLIC_KEY),
        // Base64 Ed25519 secret key used only by the maintainer's publisher to
        // sign a release. Never set this on an installed site.
        'signing_key' => env('WEBBLOCKS_PUBLISHER_SIGNING_KEY'),
    ],
    'installer' => [
        'target_path' => base_path(),
        'workspace_root' => 'app/system-updates',
        'download_timeout_seconds' => 120,
        'command_timeout_seconds' => 600,
        'migration_strategy' => env('WEBBLOCKS_UPDATES_MIGRATION_STRATEGY', 'auto'),
        'package_update_migrations_path' => 'vendor/fklavyenet/webblocks-cms/database/migrations/updates',
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
