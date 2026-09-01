<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

use WebBlocks\Cms\Support\Updates\Client\Support\Version\ConfigVersionResolver;

/*
|--------------------------------------------------------------------------
| WebBlocks Publisher Client — configuration contract
|--------------------------------------------------------------------------
|
| This is the single contract every consumer project configures. Values are
| grouped into: SHARED defaults (safe to leave as-is) and PRODUCT-SPECIFIC
| values (each consumer MUST set `product` and `minimum_client_version`).
|
| Decisions this file encodes come from standards/publisher-client-phase1-diff.md
| (referenced as §N below). Do not silently diverge from those decisions.
|
*/

return [

    // Master + web-trigger kill switches (§4.3).
    'enabled' => env('PUBLISHER_CLIENT_ENABLED', true),
    'web_run_enabled' => env('PUBLISHER_CLIENT_WEB_RUN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Product identity — PRODUCT-SPECIFIC (§4.2)
    |--------------------------------------------------------------------------
    | Injected per consumer; never hard-coded in the engine.
    */
    'product' => env('PUBLISHER_CLIENT_PRODUCT'),          // e.g. 'example-product'
    'product_name' => env('PUBLISHER_CLIENT_PRODUCT_NAME'), // human display name; falls back to product
    'channel' => env('PUBLISHER_CLIENT_CHANNEL', 'stable'),

    // Compatibility floor — the client refuses a release that requires a newer
    // client than this. Normalized from the server's minimum_client_version /
    // supported_from_version at the client boundary (§4.3, §5.5).
    'minimum_client_version' => env('PUBLISHER_CLIENT_MINIMUM_VERSION'),

    // Composer/runtime identity for the package apply strategy (§4.2).
    'package' => [
        'name' => env('PUBLISHER_CLIENT_PACKAGE_NAME'),    // e.g. 'vendor/example-package'
        'psr4_namespace' => null,                          // e.g. 'Vendor\\Example\\'
        'service_provider' => null,                        // FQCN, used for runtime-target resolution
    ],

    /*
    |--------------------------------------------------------------------------
    | Version source — Task A integration point (§6, §4.2)
    |--------------------------------------------------------------------------
    | The single place the client learns the installed version. Default resolver
    | reads `source` (normally the product's Support::VERSION via config('app.version')).
    | Post-apply verification re-reads the applied version through the same resolver.
    */
    'version' => [
        'resolver' => ConfigVersionResolver::class,
        'source' => env('PUBLISHER_CLIENT_VERSION'),       // fallback if resolver needs an explicit value
        // For file-tokenizer verification (§4.2): where the applied VERSION const lives.
        'const_file' => null,                              // e.g. 'src/Support/WebBlocks.php'
        'const_name' => 'VERSION',
    ],

    /*
    |--------------------------------------------------------------------------
    | Publisher server — SHARED defaults (§4.1)
    |--------------------------------------------------------------------------
    */
    'server_url' => rtrim(env('PUBLISHER_CLIENT_SERVER_URL', 'https://publisher.webblocksui.com'), '/'),
    'latest_path' => '/api/updates/latest',
    'publish_path' => '/api/updates/publish',
    'api_version' => '1',
    'timeout_seconds' => 5,
    'connect_timeout_seconds' => 3,
    'retry_times' => 0,
    'retry_sleep_milliseconds' => 150,
    'pending_cache_ttl_seconds' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Apply strategy — one engine, two modes (§7.1)
    |--------------------------------------------------------------------------
    | 'package'   -> replace only the configured product package directory.
    | 'full-root' -> replace the app root, skipping preserve_paths (standalone apps).
    */
    'apply' => [
        'strategy' => env('PUBLISHER_CLIENT_APPLY_STRATEGY', 'package'),

        // Per-run workspace (download + extract scratch) under storage_path().
        'workspace_root' => 'app/publisher-client',

        // Download timeout for the artifact fetch.
        'download_timeout_seconds' => 120,

        // Where files land. null => resolved by strategy
        // (package: active runtime package path; full-root: base_path()).
        'target_path' => env('PUBLISHER_CLIENT_TARGET_PATH'),

        // full-root: never overwrite these (§7.2). User data lives here in a
        // standard Laravel app, so it is always shielded.
        'preserve_paths' => [
            '.env', '.git', 'storage', 'bootstrap/cache',
            'vendor', 'public/storage', 'node'.'_modules',
        ],

        // full-root: after the overlay, report files that live under these roots
        // on the install but are not in the release — the apply never deletes, so
        // a file the product dropped stays behind forever. Only `config/` by
        // default: Laravel requires every file in it, so a leftover there fails
        // the app the day it references code a release removed. Nothing is
        // deleted; the run is marked success-with-warnings. Set to [] to disable.
        'orphan_scan_paths' => ['config'],

        // package: only these top-level roots inside the package are replaced.
        'allowed_roots' => [],

        // Enforce that the target is the active runtime package.
        'enforce_active_runtime_target' => true,

        // Bracket the apply with `artisan down` / `artisan up` at the host root.
        'maintenance_mode' => true,

        // Run `composer install` after apply, before migrations. Needed by
        // full-root apps whose release artifacts ship source only (no vendor/),
        // so dependencies + the autoloader are refreshed from the new lock.
        'composer_install' => env('PUBLISHER_CLIENT_COMPOSER_INSTALL', false),
        'composer_install_args' => ['install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader'],

        // Hard cap on artifact size before extraction.
        'max_artifact_bytes' => 26214400, // 25 MB

        // Mode for directories the apply creates at the target. Hosting panels
        // that write via a group user over setgid+ACL directories need
        // group-write to survive: a bare 0755 clamps the ACL mask and may lock
        // the control panel out of updater-created directories. 02775 = rwxrwsr-x.
        'directory_mode' => 02775,

        // package: staged-artifact boundary validation (§7.1 hardening). Runs
        // ONLY under the package strategy — a full-root
        // artifact legitimately ships app/Http, app/Models, etc. Each rule is
        // independent: an empty list disables that check.
        'package_validation' => [
            // Reject any staged file outside this top-level allowlist.
            // Empty = don't enforce (structure-specific; each package sets its own).
            'allowed_roots' => [],

            // Reject these path prefixes anywhere in the artifact. A package
            // release must never carry the frontend build chain — assets are
            // built on the host, not shipped. Safe universal default.
            'forbidden_paths' => [
                'node'.'_modules',
                'package'.'.json',
                'package'.'-lock'.'.json',
                'yarn'.'.lock',
                'pnpm'.'-lock'.'.yaml',
                'vite'.'.config',
                'tailwind'.'.config',
                'postcss'.'.config',
                'public'.'/build',
                'public'.'/hot',
            ],

            // Reject a scanned source file whose contents reference host-app
            // runtime or the frontend toolchain (the "package boundary scan").
            // Empty = don't scan (references are product-specific).
            'forbidden_content_patterns' => [],

            // Extensions the content scan reads (only when
            // forbidden_content_patterns is non-empty).
            'scan_extensions' => ['php', 'blade.php', 'json', 'md', 'css', 'js'],

            // Reject hidden/dot path segments. Package artifacts are built
            // dotfile-free by publisher:prepare-update.
            'reject_hidden_segments' => true,

            // Staged tree must contain these paths (structural sanity, e.g. src).
            'required_paths' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrity & authenticity (§7.8)
    |--------------------------------------------------------------------------
    | checksum: proves the file matches the payload. MANDATORY.
    | signature: Ed25519, proves the OWNER authored it and it wasn't swapped.
    |            MANDATORY in every product — an unsigned/bad-signed package is
    |            refused. One org-wide pinned public key for now (per-product later).
    */
    'checksum_required' => true,

    'signature' => [
        'required' => true,
        // Org-wide pinned Ed25519 public key (NOT secret — safe to ship). Every
        // adopter verifies releases against this by default; override per-env with
        // PUBLISHER_CLIENT_PUBLIC_KEY only when rotating. The matching secret
        // signing key stays owner-side (below), never shipped to consumers.
        'public_key' => env('PUBLISHER_CLIENT_PUBLIC_KEY', 'iUYAXjEXDcdJDIQdfWW04gZHVvUf8tPfPSjS4uKHXFk='),
        'signing_key' => env('WEBBLOCKS_PUBLISHER_SIGNING_KEY'),   // OWNER-side only; never shipped to consumers
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup — mandatory before apply (§7.2)
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => true,
        'retention' => 3,       // keep the last N pre-update snapshots
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations (§4.3, §5.4)
    |--------------------------------------------------------------------------
    | Default package-only; 'auto' detects source vs vendor layout.
    */
    'migrations' => [
        'enabled' => true,
        'strategy' => env('PUBLISHER_CLIENT_MIGRATION_STRATEGY', 'auto'), // auto | package | source
        // Relative to the host root. When set (non-empty), 'auto' resolves to the
        // package strategy (migrate only this path); when null, 'auto' resolves to
        // source (full `migrate --force`).
        'package_migrations_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-apply commands — allowlisted only (§4.2, §4.3)
    |--------------------------------------------------------------------------
    | Product-specific commands (asset publish, cache clear, catalog repair…)
    | ship empty by default; each consumer opts in to a named allowlist.
    */
    'commands' => [
        'allowed' => [],
        'post_apply' => [],
        'timeout_seconds' => 600,
        'composer_binary' => env('PUBLISHER_CLIENT_COMPOSER_BINARY', 'composer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Run history / retention (§4.1)
    |--------------------------------------------------------------------------
    */
    'runs' => [
        'keep' => env('PUBLISHER_CLIENT_RUNS_KEEP', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Changelog — cumulative (§7.4)
    |--------------------------------------------------------------------------
    | The engine models release notes as a LIST of per-version entries so the
    | admin screen can show everything between installed and latest. Degrades
    | to a single entry until the Publisher returns a version range.
    */
    'changelog' => [
        'cumulative' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Publisher upload — OWNER-side (§7.8, layer A)
    |--------------------------------------------------------------------------
    | The Bearer token is the "only I can publish" gate. Held by the owner.
    */
    'publisher' => [
        'token' => env('WEBBLOCKS_PUBLISHER_TOKEN'),
        'timeout_seconds' => 120,
        'connect_timeout_seconds' => 5,
        // Where `prepare` writes (and `publish` discovers) release payloads +
        // artifacts, relative to storage_path().
        'release_storage_path' => 'app/publisher-client-release',

        // Where `publisher:prepare-update` reads release notes from when `--notes`
        // is not passed: the section of this changelog whose heading contains the
        // release version becomes the release_notes. Relative to the packaged
        // source root. Set null to disable auto-notes (then notes come only from
        // --notes). Format-agnostic: any `#`-heading line mentioning the version.
        'changelog_path' => 'CHANGELOG.md',

        // Paths `publisher:prepare-update` excludes from the release ZIP. Leave
        // null for the baseline repository, environment, dependency, runtime,
        // cache and linked-storage paths. A full-root standalone app sets its own
        // list to also shed dev-only trees, built assets and product data it must
        // never ship (tests, compiled assets, a SQLite DB, …). Directory
        // entries match themselves and everything beneath; a file entry matches
        // exactly.
        'artifact_excludes' => null,

        // Optional ALLOWLIST of permitted top-level roots (opt-in; blacklist is the
        // default). null/[] ⇒ blacklist only. A strict `laravel_app` product sets
        // this to the exact roots the deployment inspector accepts — e.g. ['app', 'config',
        // 'database', 'lang', 'public', 'resources', 'routes', 'artisan',
        // 'composer.json', 'composer.lock', 'bootstrap'] — so only those ship, no
        // matter what stray file the filesystem walk finds. Combine with
        // artifact_excludes for sub-paths inside an allowed root (bootstrap/cache,
        // bootstrap/providers.php). Backup/temp files (.bak/.tmp/.old/~) are always
        // dropped regardless of this setting.
        'artifact_allowed_roots' => null,

        // Wrap the release payload in a single `<product>/` top-level directory
        // (default) so the apply-side extractor unwraps one clean root. Set false
        // for a product whose pre-migration bootstrap extractor validates paths at
        // the ROOT and does NOT unwrap a single top directory. The
        // package's own extractor accepts both shapes.
        'wrap_in_product_dir' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency lock (§ capability matrix)
    |--------------------------------------------------------------------------
    */
    'lock' => [
        'name' => 'publisher-client:run',
        'ttl_seconds' => 900,
        // Stale-lock takeover (1.0.2): a held lock whose heartbeat is older
        // than this (or absent) is treated as abandoned by a fatally-dead run
        // and taken over instead of rejecting the new attempt. The running
        // engine beats between pipeline steps, per subprocess output chunk and
        // while the backup copy streams, so a live run stays well under this.
        'stale_after_seconds' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-run preflight (1.0.2)
    |--------------------------------------------------------------------------
    | Advisory checks surfaced on the update screen before a run. Currently:
    | the private Composer repo reachable + authenticated with the project's
    | auth.json (only meaningful when apply.composer_install is true).
    */
    'preflight' => [
        'cache_ttl_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin UI — radically minimal + standard nav placement (§7.6, §7.7)
    |--------------------------------------------------------------------------
    | Default screen shows ONLY: current->new version, cumulative changelog,
    | one Update button. History/logs/checksum/diagnostics are NOT shown by
    | default (the engine still produces them for CLI/support).
    | Controller + view stay per-product for branding; they call the shared service.
    */
    'admin' => [
        'minimal_ui' => true,
        'navbar_indicator' => true,             // "update available" badge in the admin top bar
        'indicator_cache_ttl_seconds' => 3600,
        'indicator_inactive_cache_ttl_seconds' => 60,
        // Sidebar: Maintenance (placed last) -> System Updates. Applied per-product.
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry — opt-out, anonymous (default ON since 1.0.3)
    |--------------------------------------------------------------------------
    | Anonymous adoption/liveness ping on update checks: a random file-persisted
    | installation id plus product, installed version, channel, php/laravel
    | versions. No domains, paths, user data or secrets. Disable per site with
    | PUBLISHER_CLIENT_TELEMETRY=false.
    */
    'telemetry' => [
        'enabled' => env('PUBLISHER_CLIENT_TELEMETRY', true),
        'schema_version' => '1',
    ],

];
