<?php

return [
    'enabled' => [
        'webblocks-ui-manager' => env('WEBBLOCKS_UI_MANAGER_ENABLED', false),
    ],

    'webblocks_ui_manager' => [
        'cdn_base_path' => env('WEBBLOCKS_UI_MANAGER_CDN_BASE_PATH', 'cdn/webblocks-ui'),
        'cdn_base_url' => env('WEBBLOCKS_UI_MANAGER_CDN_BASE_URL'),
    ],
];
