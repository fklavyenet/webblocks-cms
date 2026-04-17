<?php

return [
    'latest_version' => env('CMS_LATEST_VERSION', '0.1.2'),

    'published_at' => env('CMS_PUBLISHED_AT'),

    'release_notes' => [
        '0.1.2' => [
            'Made /admin the canonical dashboard URL.',
            'Added backward-compatible /admin/dashboard redirect.',
            'Aligned auth redirects with the canonical admin entry path.',
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
