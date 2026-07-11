<?php

return [
    'catalog' => [
        'base_url' => env('WEBBLOCKS_PLUGIN_CATALOG_BASE_URL', 'https://plugins.webblocksui.com'),
        'timeout_seconds' => env('WEBBLOCKS_PLUGIN_CATALOG_TIMEOUT_SECONDS', 5),
        'connect_timeout_seconds' => env('WEBBLOCKS_PLUGIN_CATALOG_CONNECT_TIMEOUT_SECONDS', 3),
    ],

    'enabled' => [
    ],

    'install' => [
        'root' => env('WEBBLOCKS_PLUGIN_INSTALL_ROOT'),
    ],
];
