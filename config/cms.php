<?php

return [
    'latest_version' => env('CMS_LATEST_VERSION', '0.1.1'),

    'published_at' => env('CMS_PUBLISHED_AT'),

    'release_notes' => [
        '0.1.1' => [
            'Added contact form block.',
            'Added contact messages inbox.',
            'Improved CMS boundary separation.',
        ],
    ],

    'update' => [
        'source' => env('CMS_UPDATE_SOURCE', 'remote'),
        'manifest_url' => env('CMS_UPDATE_MANIFEST_URL', ''),
        'channel' => env('CMS_UPDATE_CHANNEL', 'stable'),
        'timeout_seconds' => env('CMS_UPDATE_TIMEOUT_SECONDS', 5),
        'cache_minutes' => env('CMS_UPDATE_CACHE_MINUTES', 10),
    ],
];
