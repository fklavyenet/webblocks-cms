<?php

return [
    'auth' => [
        'guard' => env('WEBBLOCKS_CMS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
        'provider' => env('WEBBLOCKS_CMS_AUTH_PROVIDER', 'users'),
        'model' => env('WEBBLOCKS_CMS_AUTH_MODEL', config('auth.providers.users.model', 'App\\Models\\User')),
        'user_model_path' => env('WEBBLOCKS_CMS_AUTH_USER_MODEL_PATH'),
    ],
    'diagnostics' => [
        'load_routes' => env('WEBBLOCKS_CMS_DIAGNOSTICS_LOAD_ROUTES', false),
    ],
    'install' => [
        'load_routes' => env('WEBBLOCKS_CMS_INSTALL_LOAD_ROUTES'),
        'web_routes_path' => env('WEBBLOCKS_CMS_WEB_ROUTES_PATH'),
    ],
    'middleware' => [
        'register_aliases' => env('WEBBLOCKS_CMS_REGISTER_MIDDLEWARE_ALIASES', true),
    ],
    'admin' => [
        'load_routes' => env('WEBBLOCKS_CMS_ADMIN_LOAD_ROUTES', true),
        'load_status_route' => env('WEBBLOCKS_CMS_ADMIN_LOAD_STATUS_ROUTE', false),
    ],
    'assets' => [
        'install_path' => env('WEBBLOCKS_CMS_PUBLIC_ASSET_PATH', 'public/cms'),
    ],
    'media' => [
        'remote_fetch' => [
            'max_kilobytes' => env('WEBBLOCKS_CMS_REMOTE_MEDIA_MAX_KB', 51200),
            'timeout_seconds' => env('WEBBLOCKS_CMS_REMOTE_MEDIA_TIMEOUT_SECONDS', 15),
            'connect_timeout_seconds' => env('WEBBLOCKS_CMS_REMOTE_MEDIA_CONNECT_TIMEOUT_SECONDS', 5),
            'max_redirects' => env('WEBBLOCKS_CMS_REMOTE_MEDIA_MAX_REDIRECTS', 3),
        ],
    ],
    'defaults' => [
        'locale' => env('WEBBLOCKS_CMS_DEFAULT_LOCALE', 'en'),
        'site_name' => env('WEBBLOCKS_CMS_DEFAULT_SITE_NAME', 'Default Site'),
        'site_handle' => env('WEBBLOCKS_CMS_DEFAULT_SITE_HANDLE', 'default'),
    ],
    'public' => [
        'load_routes' => env('WEBBLOCKS_CMS_PUBLIC_LOAD_ROUTES', true),
        'load_status_route' => env('WEBBLOCKS_CMS_PUBLIC_LOAD_STATUS_ROUTE', false),
    ],
    'boundaries' => [
        'load_migrations' => env('WEBBLOCKS_CMS_LOAD_PACKAGE_MIGRATIONS', false),
    ],
    'migrations' => [
        'fresh_path' => 'database/migrations/fresh',
    ],
];
