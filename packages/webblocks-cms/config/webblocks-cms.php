<?php

return [
    'diagnostics' => [
        'load_routes' => env('WEBBLOCKS_CMS_DIAGNOSTICS_LOAD_ROUTES', false),
    ],
    'admin' => [
        'load_routes' => env('WEBBLOCKS_CMS_ADMIN_LOAD_ROUTES', true),
        'load_status_route' => env('WEBBLOCKS_CMS_ADMIN_LOAD_STATUS_ROUTE', false),
    ],
    'public' => [
        'load_routes' => env('WEBBLOCKS_CMS_PUBLIC_LOAD_ROUTES', true),
        'load_status_route' => env('WEBBLOCKS_CMS_PUBLIC_LOAD_STATUS_ROUTE', false),
    ],
    'boundaries' => [
        'load_migrations' => env('WEBBLOCKS_CMS_LOAD_PACKAGE_MIGRATIONS', false),
    ],
];
