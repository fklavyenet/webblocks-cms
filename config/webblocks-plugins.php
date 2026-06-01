<?php

return [
    'catalog' => [
        'base_url' => env('WEBBLOCKS_PLUGIN_CATALOG_BASE_URL', 'https://plugins.webblocksui.com'),
        'timeout_seconds' => env('WEBBLOCKS_PLUGIN_CATALOG_TIMEOUT_SECONDS', 5),
        'connect_timeout_seconds' => env('WEBBLOCKS_PLUGIN_CATALOG_CONNECT_TIMEOUT_SECONDS', 3),
    ],

    'enabled' => [
        'webblocks-ui-manager' => env('WEBBLOCKS_UI_MANAGER_ENABLED', false),
    ],

    'webblocks_ui_manager' => [
        'cdn_base_path' => env('WEBBLOCKS_UI_MANAGER_CDN_BASE_PATH', 'cdn/webblocks-ui'),
        'cdn_base_url' => env('WEBBLOCKS_UI_MANAGER_CDN_BASE_URL'),
        'expected_dist_files' => [
            'webblocks-ui.css',
            'webblocks-icons.css',
            'webblocks-ui.js',
        ],
    ],
];
