<?php

return [

  /*
   * Ceiling on the site override directory an export will package.
   *
   * The whole of public/site/{handle} travels with a site, which is right for
   * stylesheets and fonts and wrong for whatever else ends up in there. Past
   * this the export stops with a message naming the directory rather than
   * quietly producing a package nobody can upload.
   */
  'export' => [
    'site_asset_max_bytes' => (int) env('WEBBLOCKS_CMS_EXPORT_SITE_ASSET_MAX_BYTES', 52428800),
  ],

    'auth' => [
        'guard' => env('WEBBLOCKS_CMS_AUTH_GUARD', config('auth.defaults.guard', 'web')),
        'provider' => env('WEBBLOCKS_CMS_AUTH_PROVIDER', 'users'),
        'model' => env('WEBBLOCKS_CMS_AUTH_MODEL', config('auth.providers.users.model', 'App\\Models\\User')),
        'user_model_path' => env('WEBBLOCKS_CMS_AUTH_USER_MODEL_PATH'),

        // Failed admin sign-in attempts per email+IP before the login form is
        // temporarily locked. Successful sign-in clears the counter.
        'max_login_attempts' => (int) env('WEBBLOCKS_CMS_MAX_LOGIN_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('WEBBLOCKS_CMS_LOGIN_DECAY_SECONDS', 60),
    ],
    'diagnostics' => [
        'load_routes' => env('WEBBLOCKS_CMS_DIAGNOSTICS_LOAD_ROUTES', false),
    ],
    'install' => [
        'load_routes' => env('WEBBLOCKS_CMS_INSTALL_LOAD_ROUTES'),
        'web_routes_path' => env('WEBBLOCKS_CMS_WEB_ROUTES_PATH'),

        // Fill the home page a fresh install creates with the shipped starter
        // blocks. Disable it to install with an empty, editable home page.
        // Existing content is never touched either way: starter content is
        // only written into a page that has no blocks at all.
        'starter_content' => (bool) env('WEBBLOCKS_CMS_STARTER_CONTENT', true),

        // Directory holding starter content blueprints, when a product should
        // ship its own instead of the package default. See
        // database/content/starter/README.md for the file format.
        'starter_content_path' => env('WEBBLOCKS_CMS_STARTER_CONTENT_PATH'),
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
        // SVG files can embed inline script and are served from the same origin
        // as the admin, so they are rejected by default. Enable only if you
        // trust every account that can upload media (see docs/security.md).
        'allow_svg_uploads' => (bool) env('WEBBLOCKS_CMS_ALLOW_SVG_UPLOADS', false),
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

  /*
   * WebBlocks Workbench is the shared ticket tracker behind every product in
   * the fleet. The token is pinned to the WebBlocks CMS project on the
   * Workbench side, so it can only ever file against the CMS. It speaks for
   * every admin of THIS install, so it stays server-side and never reaches a
   * browser.
   *
   * Each install holds its own token, which is what makes Workbench's
   * per-token monthly quota bound one site's reporting without touching
   * anyone else's. Installs are told apart by the id this app generates
   * (WebBlocks\Cms\Support\Tickets\InstallId), not by anything set here.
   */
  'workbench' => [
    'url' => env('WORKBENCH_URL', 'https://workbench.webblocksui.com'),
    'ticket_token' => env('WORKBENCH_TICKET_TOKEN'),
    'timeout' => (int) env('WORKBENCH_TIMEOUT', 10),
  ],

];
