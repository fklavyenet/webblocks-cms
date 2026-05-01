<?php

return [
    'install' => [
        'guard_enabled' => env('CMS_INSTALL_GUARD_ENABLED', env('APP_ENV') !== 'testing'),
        'environment_path' => env('CMS_INSTALL_ENV_PATH', base_path('.env')),
        'environment_example_path' => env('CMS_INSTALL_ENV_EXAMPLE_PATH', base_path('.env.example')),
        'storage_link_enabled' => env('CMS_INSTALL_STORAGE_LINK_ENABLED', env('APP_ENV') !== 'testing'),
    ],
    'multisite' => [
        'unknown_host_fallback' => env('CMS_MULTISITE_UNKNOWN_HOST_FALLBACK', env('APP_ENV', 'production') !== 'production'),
    ],
    'backup' => [
        'execution' => env('CMS_BACKUP_EXECUTION', 'auto'),
        'dump_timeout_seconds' => env('CMS_BACKUP_DUMP_TIMEOUT_SECONDS', 120),
        'restore_timeout_seconds' => env('CMS_BACKUP_RESTORE_TIMEOUT_SECONDS', 300),
        'stale_after_minutes' => env('CMS_BACKUP_STALE_AFTER_MINUTES', 10),
    ],
    'visitor_reports' => [
        'enabled' => env('CMS_VISITOR_REPORTS_ENABLED', true),
        'utm_enabled' => env('CMS_VISITOR_UTM_ENABLED', true),
        'consent_banner_enabled' => env('CMS_VISITOR_CONSENT_BANNER_ENABLED', env('CMS_VISITOR_REPORTS_ENABLED', true)),
        'consent_cookie_name' => env('CMS_VISITOR_CONSENT_COOKIE_NAME', 'webblocks_visitor_consent'),
        'consent_cookie_lifetime_days' => env('CMS_VISITOR_CONSENT_COOKIE_LIFETIME_DAYS', 180),
        'ignored_user_agents' => [
            'bot',
            'crawler',
            'spider',
            'slurp',
            'bingpreview',
            'facebookexternalhit',
            'google-inspectiontool',
            'googleother',
            'uptime',
            'monitoring',
            'curl/',
            'wget/',
        ],
    ],
];
