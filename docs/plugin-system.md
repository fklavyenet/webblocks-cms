---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/plugin-system
cms_title: WebBlocks CMS Plugin System
cms_layout: docs
cms_source_id: webblocks-cms:docs/plugin-system.md
---

# WebBlocks CMS Plugin System

This document records the architecture for the WebBlocks CMS plugin system. CMS core is a generic plugin host with registry-backed plugin definitions, manual super-admin ZIP upload/install, storage-owned install paths, disabled-by-default installed plugins, explicit enable/disable management, trusted CMS API lifecycle endpoints with separate token capabilities, manual-upload uninstall, compatibility checks, enabled-only routes and commands, settings/detail scaffolding, health/status reporting, typed admin extension slots, plugin-owned block declarations, public asset hooks, package convention guards, a checksum-verified Plugin Catalog install bridge for compatible public catalog artifacts, and a controlled catalog-backed update action for installed plugins with newer compatible releases. WebBlocks UI Manager is no longer bundled into CMS core runtime; it is an internal/operator plugin artifact installed manually only on operator installs such as webblocksui.com. There is no public marketplace, full remote plugin store, arbitrary Composer package installer, automatic external plugin download/update, automatic external production WebBlocks UI CDN deployment, or generic update-server publishing.

## Core Decision

WebBlocks CMS core is a plugin host.

The core package provides the reusable CMS product surface:

- content and site management
- public rendering infrastructure
- user, role, and permission foundations
- admin shell and standard admin UI surfaces
- plugin discovery, registry, and extension slot contracts

Product-specific or business-domain-specific capabilities must not be embedded into CMS core unless they are part of the reusable CMS product. They should be delivered as plugins so one install does not inherit another product's menus, commands, settings, data tables, or operational workflows.

Expected plugin areas include:

- WebBlocks UI Release/CDN Manager
- QuizTem integration
- analytics
- SEO pro tools
- newsletter
- commerce
- media optimizer
- update server manager
- custom block packs
- appointments/booking

Appointments is the first planned plugin to require a visitor-facing surface, and its design record — including the core extension points it depends on — is kept in [the appointments plugin plan](appointments-plugin-plan.md).

## Core Vs Plugin Boundary

Core capabilities:

- sites, pages, blocks, media, users, locales, and base settings
- rendering, public shell, layout, slot, and block infrastructure
- permission and role foundation
- admin shell and standard admin UI surface
- plugin discovery, registry, and extension slot contracts

Plugin capabilities:

- admin screens for a specific product or business domain
- a plugin-owned route namespace
- plugin-owned permissions
- plugin-owned settings
- plugin-owned console commands
- plugin-owned migrations
- plugin-owned dashboard widgets
- plugin-owned blocks or block packs
- plugin-owned public routes, only when explicitly declared through `routes.public`

Core view override is forbidden by default. Plugins extend CMS only through documented extension slots and registry contracts. A plugin must not replace package views, monkey patch core services, add hidden route files, or rely on arbitrary include side effects.

## Manual ZIP Install

`System -> Plugins` lets super admins upload a local plugin ZIP. Uploading a ZIP is privileged executable-code installation. The installer validates the archive before writing anything under the configured plugin root, defaulting to `storage/app/webblocks/plugins/{plugin-handle}/{version}`.

Validation requires `webblocks-plugin.json` or `manifest.json`, a kebab-case handle, a semver-like version, provider/class metadata, a compatible CMS version constraint, no installed handle collision, relative package paths only, no path traversal, no absolute paths, no symlink entries, and no writes to forbidden CMS/core targets such as `app`, `packages`, `project`, `storage`, `vendor`, or `public/cms`. Installed plugins remain disabled unless an explicit enable step is completed. Disabled plugins are inert: routes, commands, menus, settings routes, health reporters, widgets, block declarations, and assets are not registered or executed, and `System -> Plugins` reports health as inactive/not checked.

Manual uninstall is available only for manually uploaded plugins and requires super-admin authorization. The plugin must be disabled first. Uninstall removes the installed-plugin package directory and enabled-state file under the configured plugin root, but it does not drop plugin-owned database tables or run destructive migrations. Protected/core/non-manual plugins cannot be uninstalled through this flow.

Trusted operator tools can use `/webadmin/api/plugins` lifecycle endpoints with explicit CMS API token capabilities. These endpoints mirror the same safety model as the browser admin: uploaded ZIPs are validated and installed disabled, enable/disable is explicit, setup migrations are explicit, uninstall requires a disabled manually uploaded plugin, and plugin-owned tables are preserved.

Supported manifest fields include `handle`, `label`, `description`, `version`, `provider`, `required_cms_version`, `permissions`, `commands`, `routes` (`admin`, `api`, `public`, `webhooks`), `settings`, `migrations`, `assets`, and `health`. Migrations are installed as plugin-owned files and are never run automatically on upload or enable. Super admins can run the explicit plugin setup action from the plugin detail screen; the runner scopes execution to the installed plugin path and the manifest-declared migration directories, records setup results in enabled state, and can repair a setup-required plugin whose migration records exist but required plugin tables are missing.

Manual plugin lifecycle is:

1. Upload/install the ZIP. The plugin is disabled by default.
2. Review the plugin detail screen.
3. Enable the plugin when compatible.
4. Run plugin setup/migrations if the detail screen reports `Setup required` or `Plugin migrations pending`.
5. Use operational plugin routes after setup is ready.
6. Disable the plugin to make routes, commands, menus, settings, health checks, and contributions inert.
7. Uninstall only after disable; uninstall preserves plugin-owned tables.

Enabled plugins with pending setup must not crash admin routes. If plugin-owned tables are missing, health and route screens should report setup-required guidance such as `Plugin migrations pending` or `Release tables are missing`.

Plugin admin routes should use declared handle-prefixed permissions. CMS `super_admin` users are explicitly allowed for active enabled-plugin permissions, including permissions loaded from manually installed plugin manifests. Non-super-admin roles remain denied unless a future CMS permission assignment model grants the specific plugin-owned permission. Settings routes use `{plugin-handle}.manage` when the plugin declares it, otherwise they fall back to system access.

Enabled compatible plugin admin routes always run inside the CMS admin route stack: `web`, install-required, CMS auth, CMS admin access, plugin setup guard, and then plugin-owned permission middleware when the plugin route declares it. The CMS auth middleware uses Laravel's web guard/session user while redirecting guests through the CMS-owned `webblocks.auth.login` route. Plugin setup guards are additive and must not replace CMS authentication or admin authorization; plugin controllers can rely on the authenticated CMS user being present after the auth/admin middleware has passed.

## Plugin Contract And Manifest

Every plugin must have a handle:

- kebab-case
- globally unique within the install
- stable across releases
- used as the default prefix for permissions, routes, tables, settings, assets, and package identity

Each plugin should declare metadata through a manifest or definition object:

- handle
- label
- version
- provider class
- optional description
- required CMS version or version constraint
- settings namespace
- database/table prefix
- permissions
- admin menu entries
- admin and public routes
- console commands
- settings schema or settings pages
- migrations
- blocks or block packs
- assets
- health checks, when supported

Plugins are registry-first. They connect to CMS through explicit contracts rather than random includes, root view overrides, or install-specific route files.

Catalog-updated installed plugins are reloaded through CMS core runtime refresh after the replacement package is written. The refresh clears plugin registry, permission, extension, health, and optimized Laravel runtime cache state, then rebuilds active plugin routes for the current runtime so the installed version, active manifest version, active provider metadata, and route/controller source path stay aligned. If a provider class from an older installed package version is already loaded in the same PHP process, CMS treats that provider as stale and falls back to the updated manifest metadata instead of reusing old route paths.

Plugin authorization is resolved centrally by CMS core. Active plugin-owned permissions declared by the enabled plugin allow CMS `super_admin` users, and the same resolver is used for plugin menu visibility, dashboard/system contributions, and `plugin.permission:*` route middleware. Unauthorized users do not see matching plugin menu entries; direct URL access remains a controlled 403. Setup-required handling remains after authorization, and plugin migrations are still explicit.

Current registry API shape:

```php
PluginDefinition::make('webblocks-ui-manager')
  ->label('WebBlocks UI Manager')
  ->version('1.0.0')
  ->requiresCms('^1.32')
  ->provider(WebBlocksUiManagerServiceProvider::class)
  ->settingsNamespace('webblocks_ui_manager')
  ->databasePrefix('webblocks_ui_manager_')
  ->menu([
    PluginMenuItem::make('releases')
      ->label('WebBlocks UI Releases')
      ->icon('package')
      ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
      ->permission('webblocks-ui-manager.view'),
  ])
  ->permissions([
    PluginPermission::make('webblocks-ui-manager.view')->label('View releases'),
    PluginPermission::make('webblocks-ui-manager.manage')->label('Manage release metadata'),
    PluginPermission::make('webblocks-ui-manager.publish')->label('Prepare CDN artifacts'),
  ])
  ->adminRoutes(__DIR__.'/../routes/admin.php')
  ->commands([
    PrepareWebBlocksUiReleaseCommand::class,
  ])
  ->settings(
    PluginSettingsDefinition::make()
      ->label('Release Settings')
      ->description('Controls WebBlocks UI release publishing defaults.')
  )
  ->dashboardWidgets([
    PluginDashboardWidget::make('webblocks-ui-manager.release-status')
      ->title('WebBlocks UI Releases')
      ->description('Read-only release publishing summary.')
      ->permission('webblocks-ui-manager.view'),
  ])
  ->systemCards([
    PluginSystemCard::make('webblocks-ui-manager.cdn-status')
      ->title('CDN Status')
      ->description('Read-only CDN artifact status.')
      ->permission('webblocks-ui-manager.view'),
  ])
  ->blockTypes([
    PluginBlockTypeDefinition::make('webblocks-ui-manager::release-card')
      ->label('Release Card'),
  ])
  ->publicAssets([
    PluginPublicAsset::cssHead('webblocks-ui-manager.public-css', '/cms/plugins/webblocks-ui-manager/public.css'),
    PluginPublicAsset::jsBodyEnd('webblocks-ui-manager.public-js', '/cms/plugins/webblocks-ui-manager/public.js'),
  ])
  ->health(WebBlocksUiManagerHealth::class);
```

The exact API may change during implementation, but the contract must preserve these rules:

- declared metadata is inspectable before a plugin is enabled
- menu, route, permission, command, migration, block, asset, and setting ownership is attributable to a plugin handle
- conflicts fail during build, test, boot diagnostics, or plugin enablement before users see mixed ownership

## Plugin Public Routes

A plugin can own a visitor-facing surface. `PluginDefinition::publicRoutes()` accepts a route file or callable, and the installed-plugin manifest accepts the same declaration under `routes.public` alongside the existing `routes.admin`, `routes.api` and `routes.webhooks` keys.

Every plugin public route is mounted under `/plugins/{plugin-handle}`, with names under `webblocks.plugins.{plugin_handle}.public.*`. The prefix is a single reserved first URI segment shared by all plugins, which is what keeps a plugin endpoint from shadowing a page slug: public CMS pages are served by dynamic `{slug}` routes, so an unprefixed plugin route would compete with real content. The existing catch-all protection reserves the segment from the redirect manager automatically once a route is registered.

The registrar owns the middleware stack rather than trusting the plugin to assemble it. Every route in the group runs `web`, `install.required`, and the `plugin-public-routes` throttle, so an unthrottled public write surface is not something a plugin can ship by omission. A plugin remains free to add a stricter per-route throttle, and both limits apply. The group throttle defaults to 60 requests per minute per IP and plugin, configurable through `webblocks-plugins.public_routes.rate_limit_per_minute` or `WEBBLOCKS_PLUGIN_PUBLIC_ROUTE_RATE_LIMIT`.

Unlike the internal API group, CSRF protection stays on. Plugin public routes serve browser forms and same-origin fetches from block scripts, not bearer-token clients; a plugin needing token authentication should declare `routes.api` instead.

## Plugin Webhook Routes

A third-party callback fits neither group. A payment gateway calling back after a customer pays carries no session, so it cannot carry a CSRF token, and it is not a bearer-token client either. `PluginDefinition::webhookRoutes()` and the manifest's `routes.webhooks` mount a second file under the same `/plugins/{plugin-handle}` prefix and the same `webblocks.plugins.{plugin_handle}.public.*` name namespace, with the CSRF check dropped from that group and nothing else changed: `web`, `install.required` and the group throttle still apply.

The exemption is scoped to the routes in that file. It is applied by removing the middleware from the group rather than by adding paths to a global exemption list, so it stays attributable to the plugin that asked for it and cannot widen to a path that merely looks similar.

Authenticating the caller is the plugin's responsibility and cannot be delegated to the host. A webhook is a notification, not proof: a plugin should verify a signature, or read the transaction back from the provider's API, before it changes any state.

Public routes follow the same inertness rule as every other contribution: only enabled, compatible plugins register them. A disabled plugin's public endpoints do not exist.

## Plugin Block Views

A plugin block type may declare the views it renders through, using `adminView()` and `publicView()` on the block type definition, or `admin_view` and `public_view` in the manifest's block declarations. A declared view that resolves is used for that block's admin form and public render.

Without a declaration the previous convention still applies: installed plugin view paths are appended to the CMS view namespace, and a plugin block's catalog slug is its handle with `::` replaced by `-`, so `appointments::form` resolves `webblocks-cms::pages.partials.blocks.appointments-form` when the plugin mirrors the CMS directory layout. Declaring the view is preferred, because it does not require the plugin to guess a core directory structure it does not own. A declared view that does not resolve falls back to the convention and then to the standard missing-renderer fallback, so a bad declaration degrades rather than throwing during a page render.

Only enabled plugins contribute block views, and core block slugs are never affected by the lookup.

## Plugin Block Type Catalog Rows

Declaring a block type is not enough to make it placeable. Block pickers read the database catalog (`wbcms_block_types`), and `PluginBlockCatalog` only filters that list — it hides a plugin's blocks while the plugin is disabled but cannot add rows. `PluginBlockTypeCatalogSyncer` writes them, and `PluginRuntimeRefresher` runs it, so every plugin install, enable, disable, setup, and update ends with a catalog row for each declared block. `webblocks:catalog-repair --plugin-block-types` (included in `--all`) is the repair path for installs that predate this.

Rows are written for every installed plugin, enabled or not: placement is already gated by the catalog filter, and blocks already on a page still need their type row to resolve. The row's `name`, `description`, `source_type`, `is_system`, and `is_container` describe what the block is and are re-synced from the plugin; `category`, `sort_order`, and `status` are written once at creation and then left to the operator, so retabbing, reordering, or drafting a block to hide it is not undone by a repair. A block's `category` defaults to `content` — picker tabs are keyed by category, and an unrecognized one leaves the block reachable only from the All tab — and a plugin that wants a different tab, sort order, container flag, or catalog slug says so in the block type's metadata (`category`, `sort_order`, `is_container`, `catalog_slug`).

A plugin block handle is always namespaced, so its catalog slug cannot collide with a core slug by accident; the syncer refuses to write over a shipped core slug anyway, rather than letting a malformed plugin rewrite a core block type.

Plugin block copy posts under `plugin_settings[...]`, never `settings[...]`. `BlockRequest` prohibits `settings` for a plugin block because that column is written by core from the sanitized plugin input; a plugin block editor that posts `settings` fails validation on every save rather than degrading.

## Package Convention Rules

Plugin package conventions are separate from CMS core conventions. CMS core owns host contracts; plugins own their domain behavior.

- Handle naming: use stable kebab-case such as `analytics-tools`; never rename a handle after release because it anchors routes, permissions, settings, tables, assets, and upgrade history.
- Service provider registration: a plugin package should expose one Laravel service provider and register its `PluginDefinition` there or through the CMS registry integration point. First-party in-package pilots may register directly from the CMS package provider until split into their own Composer packages.
- Definition or manifest structure: declare `handle`, `label`, `version`, `provider`, `description`, `requiresCms`, settings namespace, database prefix, permissions, routes, commands, extension slots, assets, blocks, and health reporter explicitly.
- Route namespace: admin routes live under `/webadmin/plugins/{plugin-handle}/...` with names under `webblocks.plugins.{plugin_handle}.*`. Public routes live under `/plugins/{plugin-handle}/...` with names under `webblocks.plugins.{plugin_handle}.public.*`.
- Permission naming: every plugin permission starts with `{plugin-handle}.`, for example `analytics-tools.view`.
- Settings conventions: settings namespaces are snake_case and default to the handle with dashes converted to underscores.
- Command naming: resolvable Artisan command names must start with `{plugin-handle}:`, for example `analytics-tools:sync`.
- Migration and table naming: tables use a registry-reserved snake_case prefix ending in `_`, defaulting to the handle converted to snake_case plus `_`. Name indexes and foreign keys explicitly, and keep them short. The reserved prefix consumes most of MySQL's 64-character identifier limit before a table name is spelled, and a generated name such as `webblocks_appointments_service_resource_service_id_resource_id_unique` overruns it. SQLite does not enforce the limit, so a plugin tested only against SQLite can ship migrations that fail on every real install.
- Asset contributions: public asset handles are dot-namespaced with the plugin handle, and static files must publish under a plugin-owned path.
- Dashboard and system card contributions: keys are dot-namespaced with the plugin handle and remain read-only unless a later extension contract adds editable behavior.

WebBlocks UI Manager follows these conventions as the first-party pilot: handle `webblocks-ui-manager`, settings namespace `webblocks_ui_manager`, database prefix `webblocks_ui_manager_`, commands `webblocks-ui-manager:prepare-release` and `webblocks-ui-manager:publish-release`, routes under `/webadmin/plugins/webblocks-ui-manager`, and route names under `webblocks.plugins.webblocks_ui_manager.*`.

## Compatibility And Inertness

Plugin versions are semver-like metadata. `requiresCms()` declares the CMS version constraint needed by the plugin. The current foundation supports exact/comparator constraints such as `>=1.32.0` and caret constraints such as `^1.32`.

The registry distinguishes configured enabled state from active state:

- Configured enabled: `config/webblocks-plugins.php` says the plugin should be enabled.
- Compatible: the installed CMS version satisfies the plugin's required CMS constraint.
- Active: the plugin is both configured enabled and compatible.

Only active plugins contribute menus, routes, commands, settings routes, dashboard widgets, system cards, block declarations, public assets, permissions, and health reporter execution. Disabled and incompatible plugins remain inert. `System -> Plugins` displays `Incompatible` with the required and installed CMS versions when a configured plugin cannot activate.

## Discovery And Local Enablement

Phase 5 does not add marketplace behavior or arbitrary remote installation. Safe discovery is local and explicit:

- first-party package-owned plugins can be registered by the CMS package provider
- future Composer package plugins should register a service provider through Laravel package discovery or explicit app provider configuration
- install-local experiments can use local Composer path repositories during development, but they must still register a normal provider and definition
- enablement remains config-backed through `webblocks-plugins.enabled.{plugin-handle}`

No runtime feature installs arbitrary Composer packages, publishes marketplace catalogs, writes production CDN/update-server artifacts, auto-enables plugins, auto-runs plugin migrations/setup, or performs automatic plugin updates. Remote plugin artifact bridges are limited to super-admin initiated Plugin Catalog install/update actions described below, and both are limited to controlled catalog ZIP URLs with matching SHA-256 metadata.

## Ecosystem Catalog Direction

The CMS plugin system is the first implementation host for a broader WebBlocks plugin ecosystem direction. Future plugin contracts should be reusable by WebBlocks CMS, QuizTem, Herne Panel, WebBlocks Publisher, and later WebBlocks products where each host exposes its own product-specific extension points.

The proposed catalog/store surface is `plugins.webblocksui.com`. The near-term target is a Plugin Catalog for discovery, metadata, compatibility, documentation, release information, checksums, controlled ZIP download links, and a conservative install bridge from trusted catalog artifact metadata. Marketplace behavior, including accounts, licensing, paid plugins, reviews, and approval workflows, is deferred.

`System -> Plugins` includes a `Browse Plugin Catalog` action at `/webadmin/plugins/catalog` (`admin.plugins.catalog.index`). The catalog list requests public listed plugins for `host_product=webblocks-cms` from `GET /api/plugins` and asks for the latest compatible release through `GET /api/plugins/{handle}/latest` when a handle is available. Catalog plugin labels and the View details action open `/webadmin/plugins/catalog/{handle}` (`admin.plugins.catalog.show`), which requests `GET /api/plugins/{handle}` plus the latest compatible release endpoint to show plugin metadata, compatibility, release notes, documentation/support links, artifact download URL, SHA-256 checksum, artifact filename, artifact size, release status, artifact validation status, artifact scan status, channel, version, and safe declared capability metadata when returned by the API. Current WebBlocks Plugins API detail responses may return plugin data directly under `data`, release data under `data.latest_release`, and artifact metadata under `data.latest_release.artifact`; latest-compatible API responses may also return release metadata under `data.release` with artifact metadata under sibling `data.artifact`. CMS normalizes those shapes before rendering details, checking install availability, or processing the server-side catalog install action. Supported artifact fields include `file_name`, `size_bytes`, `checksum_sha256`, `download_url`, `validation_status`, and `scan_status`; older flat release field names remain accepted for compatibility. The canonical WebBlocks CMS product version is sent as `version` and `cms_version` for compatibility matching. The built-in public catalog URL defaults to `https://plugins.webblocksui.com`; operators do not need `.env` changes for default discovery, and can override the internal request target with `WEBBLOCKS_PLUGIN_CATALOG_BASE_URL` (`webblocks-plugins.catalog.base_url`). Timeout settings are available through `WEBBLOCKS_PLUGIN_CATALOG_TIMEOUT_SECONDS` and `WEBBLOCKS_PLUGIN_CATALOG_CONNECT_TIMEOUT_SECONDS`. Normal catalog UI does not expose the configured base URL or request version; unavailable states use friendly operator copy while safe diagnostics remain in logs.

Catalog detail keeps Website, Documentation, Support, and Catalog Detail links separate from install actions. The page shows a clear unavailable artifact state when a compatible release does not include downloadable artifact metadata. The `Download ZIP` action opens only the controlled absolute public `download_url` returned by the catalog and must not expose raw storage paths.

`Install from Catalog` is available only when the catalog plugin is compatible, the latest compatible release is `published`, and the normalized release artifact includes `download_url`, `checksum_sha256`, and `file_name` values. Older flat `sha256`/`checksum_sha256` and `filename`/`artifact_filename` release fields remain accepted for compatibility. The POST action uses CSRF, re-reads catalog metadata server-side, downloads the controlled ZIP URL to temporary storage, checks HTTP success, rejects unreasonable/non-ZIP responses, calculates SHA-256, compares it exactly with catalog metadata, and then passes the temp ZIP through the existing manual plugin ZIP validator/installer. Temporary files are removed after success or failure, and catalog/remote filenames are not trusted as filesystem paths.

Catalog installs register the plugin disabled by default, the same as manual upload. They do not enable the plugin, run plugin migrations/setup, execute plugin provider code, register plugin routes, register permissions, register commands, register assets, register blocks, add dashboard/system cards, apply updates, or change enabled state. Any local installed/enabled state shown on the catalog detail page comes only from the CMS plugin registry, not from remote catalog claims. Manual ZIP upload/install remains available and unchanged.

`System -> Plugins -> Registered Plugins` performs a best-effort catalog availability lookup for installed plugin handles. When the catalog is unavailable or does not provide trusted metadata, the list still renders and no update action is shown. When an installed handle has a newer latest compatible release by `version_compare`, that release is `published`, the plugin is compatible, and the normalized artifact includes `download_url`, `checksum_sha256`, and `file_name`, the Version column shows `Update available: {version}` and the row action group shows a POST-only `Update from Catalog` icon action.

The update POST re-reads catalog detail/latest metadata server-side, requires the same compatible published complete artifact metadata, downloads the controlled ZIP, verifies SHA-256, validates the ZIP through the same plugin package validator, and replaces the installed plugin package version. The plugin-owned database tables are preserved, enabled or disabled lifecycle state is preserved by moving enabled state to the new version only when the old version was enabled, and plugin migrations are not run automatically. If the updated plugin declares new migrations or its tables are missing, the existing setup-required guidance and explicit `Run Plugin Migrations` flow remain responsible for schema setup.

Future marketplace and catalog planning is outside this package repository's documented runtime contract.

## Minimal Plugin Example

A minimal plugin package should expose a provider and a definition similar to:

```php
final class AnalyticsToolsPlugin
{
  public static function definition(): PluginDefinition
  {
    return PluginDefinition::make('analytics-tools')
      ->label('Analytics Tools')
      ->version('0.1.0')
      ->provider(AnalyticsToolsServiceProvider::class)
      ->requiresCms('^1.32')
      ->settingsNamespace('analytics_tools')
      ->databasePrefix('analytics_tools_')
      ->permissions([
        PluginPermission::make('analytics-tools.view')->label('View analytics tools'),
      ])
      ->adminRoutes(__DIR__.'/../routes/admin.php')
      ->commands([
        SyncAnalyticsCommand::class,
      ])
      ->health(AnalyticsToolsHealth::class);
  }
}
```

The provider should register package views, config, migrations, and the plugin definition without adding CMS-owned `/admin`, `/cms`, or root public route files. Plugin routes should be defined relative to the plugin route group; for example, `/reports` becomes `/webadmin/plugins/analytics-tools/reports`.

## Admin Menu Rules

Plugins may add admin menu entries, but every plugin menu entry must be permission-gated.

`PluginMenuItem::group(string $group)` is an **exact-match label, not a picklist**. The admin sidebar gives every distinct group string its own heading and only merges items whose group string is identical (slug-insensitively) — there is no fixed enum of allowed section names and no hardcoded bucket a plugin's items fall into by default.

Two ways to use it:

- **Share an existing bucket** by passing one of the shared names exactly: `System`, `Tools`, `Integrations`. Do this for a small utility whose few menu items don't warrant their own heading.
- **Get your own dedicated section** by passing your plugin's own name instead, for a large product surface (e.g. a whole booking or commerce system). This needs no core-side registration — the heading is created automatically the first time a distinct group string is seen:

```php
->menu([
    PluginMenuItem::make('appointments')
        ->label('Appointments')
        ->route('webblocks.plugins.webblocks_appointments.appointments.index')
        ->icon('wb-icon-calendar')
        ->permission(self::PERMISSION_VIEW)
        ->group('Appointments') // its own section, not a shared bucket
        ->sort(60),
])
```

Do not reach for a generic-sounding but undocumented name (e.g. `Content`) as a substitute for either path above: it is not one of the shared buckets, so it reads as a request for your own section, but a plausible-sounding generic label like that is exactly what a second, unrelated plugin is also likely to pick — and two plugins passing the identical string silently share one heading. If your plugin is its own product surface, name the group after the plugin.

Admin menu rules:

- icons must come from the WebBlocks UI icon catalog
- route names must live under the plugin route namespace
- menu ordering and collision rules must be managed by the registry
- disabled or uninstalled plugins must not render menu entries
- menu item labels should describe the capability, not leak install-specific project names into generic CMS installs
- menu entries must not appear in core installs when the owning plugin is absent

## Route Namespace Rules

Admin plugin routes default to this URL prefix:

```text
/webadmin/plugins/{plugin-handle}/...
```

Admin plugin route names default to this namespace:

```text
webblocks.plugins.{plugin_handle}.*
```

The route name namespace uses the plugin handle transformed only as needed for Laravel route names. For example, `webblocks-ui-manager` becomes `webblocks.plugins.webblocks_ui_manager.*` if underscores are required by implementation.

A plugin may request a shorter admin prefix only through the registry. Reserved short prefixes must be globally unique. Prefix conflicts must fail during build, test, boot diagnostics, or plugin enablement.

Plugins must not pollute:

- CMS core route names
- the `/webadmin` core route namespace outside their reserved plugin prefix
- the legacy `/admin` namespace
- the `/cms` static asset namespace

Disabled and incompatible plugins must not register admin routes. The active-only registrar is intentionally conservative: if a plugin is disabled through `config/webblocks-plugins.php` or does not satisfy its CMS version constraint, its routes are absent rather than present-but-forbidden.

Public routes are opt-in. A plugin that declares public routes must declare ownership clearly enough that route ownership can be tested. Public plugin routes must avoid collisions with site pages, CMS public routes, and host product routes. Every plugin public surface goes through `routes.public` and `routes.webhooks`; core no longer carries a first-party bridge for any plugin's public URLs.

## Permission Rules

Every admin menu, route, and action must be attached to a plugin permission.

Permission names must include the plugin handle prefix:

```text
webblocks-ui-manager.view
webblocks-ui-manager.publish
webblocks-ui-manager.settings
```

Permission behavior must stay compatible with the CMS permission model. If super admin bypass exists, it must use the same explicit CMS authorization path as core CMS permissions.

Plugin permissions must be visible in admin role management when the plugin is installed or discoverable. Disabled plugin permissions must not authorize active behavior, even if a role still stores a matching permission string.

## Settings Rules

Plugin settings must be stored in the plugin's own namespace. They must not collide with general CMS config, host application config, or environment variables.

Settings rules:

- setting keys should be prefixed by the plugin handle
- sensitive values must use secret-safe storage where available
- sensitive values must never be rendered in logs, health check output, exception messages, or admin flash messages
- settings UI must live under the plugin route namespace or inside `System -> Plugins -> Plugin detail`
- environment variables may seed defaults, but runtime settings should remain plugin-owned and inspectable through the registry

Phase 2 provides a read-only settings route foundation for enabled plugins that declare `PluginSettingsDefinition` without a custom route name. The default route is:

```text
/webadmin/plugins/{plugin-handle}/settings
```

Its default route name is:

```text
webblocks.plugins.{plugin_handle}.settings.edit
```

Editable settings storage and validation schemas are reserved for a later phase. Phase 5 reserves settings namespaces through `PluginDefinition::settingsNamespace()` so plugins do not collide with CMS core config or other plugins.

## Migration And Data Lifecycle Rules

Plugin migrations must not collide with core migrations.

Plugin table names must carry the plugin handle prefix or a documented shortened prefix reserved by the plugin registry. For `webblocks-ui-manager`, table names use `webblocks_ui_manager_`. Phase 5 reserves database prefixes through `PluginDefinition::databasePrefix()` and rejects duplicate prefixes.

Lifecycle states must be distinct:

- Enable: plugin menu, routes, commands, scheduled jobs, widgets, blocks, settings, health checks, and actions become available according to permissions and compatibility.
- Disable: plugin menu, scheduled jobs, plugin routes, plugin actions, settings routes, health checks, widgets, blocks, and assets are unavailable; data remains in place.
- Uninstall: manually uploaded disabled plugins can be removed from the storage-owned install root. Plugin-owned database tables and historical data remain in place.
- Uninstall: reserved for a future design; by default it must not delete data.
- Decommission or purge: future destructive data deletion flow requiring explicit destructive confirmation.

Uninstall must not be database-destructive. Deleting plugin tables, artifacts, uploaded files outside the plugin package directory, or historical records requires a separate explicit destructive confirmation design.

Schema upgrades should be additive and reversible where practical. A plugin release that changes schema must document:

- minimum compatible CMS version
- plugin version introducing the schema
- migration/table prefix used
- whether disabled plugins can safely leave existing data in place
- operational notes for rollback or decommission

The manual plugin migration runner is intentionally scoped. It runs only migration directories declared by the installed plugin and only after resolving those directories inside the configured plugin install root. It does not run host app migrations or unrelated plugin migrations. Plugin migrations should be additive, reversible where practical, and safe to retry when setup repair is required.

## Assets And Static File Rules

Plugin assets must be published under their own namespace. They must not mix with core `public/cms` assets.

For WebBlocks UI Manager, versioned CDN output should use immutable paths such as:

```text
public/cdn/webblocks-ui/v2.7.9/...
```

Asset rules:

- versioned artifact directories are immutable
- old versioned directories must not be deleted as part of normal publish
- `latest` must not be used for first-party CDN consumption
- dry-run publish must report writes, skips, and blocked operations without writing files
- apply publish must validate expected dist files, source paths, release version, target paths, checksums, and manifest consistency before writing
- existing files with matching checksums are skipped; existing files with different checksums block the run
- CDN or static hosting should be served by Nginx or another static service when possible
- Laravel route-based asset streaming must not be the default for CDN files
- plugin admin assets must be isolated from core CMS admin assets and publish under a plugin namespace

The WebBlocks UI Manager local publish workflow writes only into the configured project-owned static target, defaulting to `public/cdn/webblocks-ui/{version}/...`. It does not deploy to external production infrastructure, publish update-server metadata, change CMS core WebBlocks UI consumption URLs, or install remote packages.

Phase 3 adds registry-backed public asset declarations for enabled plugins. These declarations are currently limited to explicit asset URLs and are rendered as public page assets only when the owning plugin is enabled:

- head CSS renders as `<link rel="stylesheet">` in the public `<head>`
- head JS renders as deferred or async/module `<script>` tags in the public `<head>`
- body-end JS renders near the end of the public `<body>`
- asset handles must be dot-namespaced with the plugin handle, such as `analytics-tools.public-js`
- disabled plugin assets are absent from collection and rendering

This is an asset contribution hook foundation, not a plugin package installer or asset publisher. Plugins remain responsible for publishing their own static files under a plugin-owned namespace.

## Event, Hook, And Extension Slot Rules

Plugins must not monkey patch or override core. Core extension slots must be explicit, documented, and testable.

Initial extension slot candidates:

- `admin.menu`
- `admin.dashboard.widgets`
- `admin.system.cards`
- `permissions.registry`
- `block.registry`
- `public.head.assets`
- `public.body_end.assets`

Slot contracts should be typed and value-object based. Avoid raw array contracts where possible so collisions, invalid shapes, and ownership can be validated early.

Phase 3 implements these typed extension-slot objects:

- `PluginDashboardWidget` for read-only dashboard cards
- `PluginSystemCard` for read-only system cards or links
- `PluginBlockTypeDefinition` for plugin-owned block type declarations
- `PluginBlockPackDefinition` for grouped plugin block declarations
- `PluginPublicAsset` for public head and body-end asset declarations
- `PluginAdminExtensionRegistry`, `PluginBlockRegistry`, and `PluginPublicAssetRegistry` for enabled-only collection

Dashboard widget and system card keys must be dot-namespaced with the plugin handle, for example `analytics-tools.overview`. Public asset handles follow the same dot namespace rule. Plugin block handles must use a plugin-owned namespace such as `analytics-tools::score-card`; unqualified core-style block handles such as `hero` are rejected. These hooks make plugin contributions discoverable and attributable without replacing core package views.

Dashboard widgets render on the super-admin dashboard only when the plugin is enabled and the current user can satisfy the widget permission, if one is declared. System cards render only on their intended system overview surfaces under the same enabled and permission checks. The `System -> Plugins` management page does not render generic plugin contribution cards; it stays focused on manual plugin install, lifecycle, health, setup, settings, and uninstall actions unless a future extension slot is explicitly designed for plugin management. Both slots are intentionally read-only foundations.

Block hooks let enabled plugins expose plugin-owned block types and block packs through the registry. Plugin-owned catalog rows use plugin-scoped slugs, are filtered out of block pickers while the owning plugin is disabled, and can render through plugin-provided Blade views registered in the shared CMS view namespace. Core remains the generic host: plugin block handles stay namespaced, plugin views live inside the plugin package, and domain-specific block behavior must not be added as a core block.

## Plugin Lifecycle

The full lifecycle target:

1. discover
2. install
3. enable
4. disable
5. health/status
6. upgrade
7. uninstall or decommission, in a later destructive-data design

The implemented Phase 1 through Phase 5 runtime target is intentionally smaller than the full lifecycle:

- registry
- enabled configuration
- `System -> Plugins` listing
- `System -> Plugins` detail and read-only settings surfaces
- admin menu registration
- permission registration
- enabled-only admin route registration
- enabled-only command registration
- basic health/status reporting
- typed read-only dashboard and system card extension slots
- plugin-owned block and block pack declaration hooks
- public head and body-end asset contribution hooks
- first-party WebBlocks UI Manager pilot plugin with release metadata, safe local manifest preparation, and controlled local CDN dry-run/apply publishing
- plugin version and required CMS compatibility metadata
- incompatible-plugin active-state and health reporting
- package convention and collision guards
- route ownership guards

That foundation gives CMS a safe host boundary before plugins gain deeper lifecycle behavior.

### Phase 1 Implementation Note

The initial Phase 1 runtime now includes:

- `PluginDefinition`, `PluginRegistry`, `PluginMenuItem`, and `PluginPermission` value objects under the package `Support\Plugins` namespace
- deterministic validation for kebab-case handles, duplicate handles, duplicate menu item keys, semver-like versions, and handle-prefixed plugin permissions
- config-backed enabled state through `config/webblocks-plugins.php`
- a package-owned `System -> Plugins` listing at `/webadmin/system/plugins`
- route guard coverage proving `/webadmin` remains canonical while CMS-owned `/admin` and Laravel `/cms` routes remain absent

Phase 1 does not include dynamic Composer plugin discovery, plugin migrations, install/enable/disable UI actions, public plugin routes, marketplace/catalog behavior, or WebBlocks UI Manager business logic. Config-backed enabled state is intentionally a bridge; a later lifecycle phase may move install/enable/disable state to persistent storage.

### Phase 2 Implementation Note

The Phase 2 runtime now includes:

- enabled-only plugin admin route registration through `PluginRouteRegistrar`
- default admin plugin URLs under `/webadmin/plugins/{plugin-handle}/...`
- default admin plugin route names under `webblocks.plugins.{plugin_handle}.*`
- default read-only settings pages for enabled plugins that declare `PluginSettingsDefinition`
- enabled-only console command collection through `PluginCommandRegistrar`
- `PluginHealthResult`, `PluginLifecycleStatus`, and `PluginHealthMonitor` for basic status reporting
- `System -> Plugins` detail pages that expose lifecycle, health, settings, route, command, permission, and menu contribution summaries
- route guard coverage proving enabled test plugin routes register, disabled plugin routes are absent, `/webadmin` remains canonical, `/cms` is not a Laravel admin route namespace, and CMS-owned `/admin` routes remain absent

This phase intentionally keeps migrations discovery, plugin install/apply/run actions, destructive lifecycle actions, dynamic Composer discovery, public plugin routes, and WebBlocks UI Manager runtime behavior out of scope.

### Phase 3 Implementation Note

The Phase 3 runtime now includes:

- typed admin extension contracts under `Support\Plugins\Contracts`
- `PluginDashboardWidget` and `PluginSystemCard` value objects collected through `PluginAdminExtensionRegistry`
- enabled-only dashboard widget rendering on the super-admin dashboard
- enabled-only system card collection for intended system overview surfaces, kept separate from the `System -> Plugins` lifecycle management page
- `PluginBlockTypeDefinition`, `PluginBlockPackDefinition`, and `PluginBlockRegistry` for plugin-owned block declarations
- `PluginPublicAsset` and `PluginPublicAssetRegistry` for safe public head and body-end asset declarations
- validation guards for extension keys, widget keys, system card keys, block handles, block pack namespaces, asset handles, plugin ownership, and duplicate declarations
- disabled-plugin inert behavior for dashboard widgets, system cards, block hooks, and public assets
- route guard coverage confirming `/webadmin` and `/webadmin/plugins/...` remain valid while `/admin` and Laravel `/cms` admin routes remain absent

This phase intentionally keeps real marketplace behavior, package installation, plugin migration runners, public plugin routes, editable widgets, core block override hooks, and the WebBlocks UI Manager plugin out of scope. Plugins still must not override package views or monkey patch core services.

### Phase 4 Implementation Note

The Phase 4 runtime now includes the first-party `webblocks-ui-manager` pilot plugin. The plugin is registered by the package registry but disabled by default through `config/webblocks-plugins.php`.

When enabled, the pilot contributes:

- handle-prefixed permissions: `webblocks-ui-manager.view`, `webblocks-ui-manager.manage`, and `webblocks-ui-manager.publish`
- a plugin admin route namespace under `/webadmin/plugins/webblocks-ui-manager/...` with route names under `webblocks.plugins.webblocks_ui_manager.*`
- a plugin menu item for WebBlocks UI release records
- read-only dashboard and system cards through the Phase 3 extension slots
- read-only settings/detail visibility through the Phase 2 settings foundation
- plugin health checks for release metadata readiness, setup-required/missing-table status, and configured CDN base path readiness
- plugin-owned tables and models: `webblocks_ui_manager_releases`, `webblocks_ui_manager_artifacts`, and `webblocks_ui_manager_publish_runs`
- a safe local `webblocks-ui-manager:prepare-release` command that records release metadata, computes artifact SHA-256 checksums, and can optionally write a local `manifest.json`
- a controlled `webblocks-ui-manager:publish-release {version} --dry-run` and `webblocks-ui-manager:publish-release {version}` workflow that records publish runs and writes only after validation passes
- first-party CDN target conventions under `public/cdn/webblocks-ui/{version}/...`

Disabled state remains inert: routes, commands, menus, settings routes, permissions, dashboard/system cards, health behavior, and asset contributions are absent from active collection. Enabled-but-not-set-up state remains safe: the menu may be visible, but a plugin screen checks schema readiness before querying and renders setup-required guidance when its tables are missing. Enabled compatible manual plugin admin URLs must not dashboard-fallback when dynamic route hydration is stale or route caching is in play; the plugin route fallback keeps plugin admin pages on their `/webadmin/plugins/{plugin-handle}/...` URL by rehydrating the plugin's own routes and then running the one that matches. It is generic: it dispatches whatever route the plugin declares, under that route's own permission middleware, rather than naming controllers and repeating authorization checks per plugin. Plugin admin and public URLs alike are plugin-owned; core bridges none of them by handle.

Phase 4 intentionally does not add external production CDN deployment automation, marketplace behavior, generic third-party plugin install/update flows, generic plugin migration runners, public plugin routes, core view overrides, update-server publishing, or changes to CMS core WebBlocks UI consumption URLs.

### Phase 5 Implementation Note

The Phase 5 runtime now includes packaging and ecosystem readiness foundations:

- plugin version and `requiresCms()` compatibility checks against the installed CMS version
- configured enabled state separated from active state, so incompatible configured plugins stay inert
- `System -> Plugins` lifecycle messaging for `Enabled`, `Disabled`, and `Incompatible`
- incompatible plugin health results that do not execute plugin health reporters
- convention metadata for settings namespaces and database/table prefixes
- command-name guards for resolvable Artisan command classes, requiring `{plugin-handle}:...`
- database prefix collision guards
- focused tests for compatibility metadata, incompatible behavior, command and prefix collisions, disabled/incompatible inertness, WebBlocks UI Manager regression, package boundaries, and route ownership
- documentation for package conventions, local discovery, minimal plugin creation, schema upgrade strategy, and release compatibility policy

Phase 5 intentionally does not add marketplace/catalog UI, arbitrary remote package installation, dynamic remote Composer discovery, generic third-party plugin migration runners, automatic external production CDN deployment, generic update-server publishing, or public plugin routes.

## Testing And Release Guardrails

The plugin system must be protected by route ownership, package boundary, and coexistence tests.

Required guardrails:

- plugin route ownership is testable
- core CMS installs show no plugin menus when no plugins are enabled
- disabled plugin menus do not render
- disabled plugin routes and actions are unavailable or fail authorization
- disabled plugin widgets, system cards, block declarations, and public assets are absent
- incompatible plugin routes, commands, menus, permissions, widgets, system cards, block declarations, public assets, settings routes, and health reporter behavior are absent
- command names, database prefixes, handles, extension slots, widgets, blocks, assets, and permission namespaces remain collision-guarded
- plugin dashboard/system extension cards render only when enabled and permitted
- plugin-owned block declarations are discoverable without overriding core block contracts
- plugin public assets are collected by safe location and are absent when disabled
- plugin route registration must not restore a CMS-owned `/admin` namespace
- `/webadmin` remains the canonical CMS admin prefix
- `/cms` remains static asset territory, not a Laravel plugin route namespace
- plugin attempts to override core tables, route names, or views should fail tests or diagnostics
- package boundary tests expand to cover plugin-owned routes, views, assets, migrations, and commands
- coexistence tests should cover CMS + QuizTem + plugin scenarios in the roadmap

Plugin tests should include both absent-plugin and disabled-plugin cases so core CMS remains clean in generic installs. The WebBlocks UI Manager pilot also carries migration/schema, command, manifest/checksum, enabled-only, disabled-inert, admin rendering, route guard, and package boundary tests.

## WebBlocks UI Manager Pilot Plugin Decisions

WebBlocks UI Manager is not embedded in CMS core behavior. It currently starts as a first-party package-owned pilot plugin under the CMS package namespace so the plugin host can prove a real product-specific operational surface without moving WebBlocks UI release/CDN management into generic CMS core.

The preferred long-term model may still become a separate Composer package or separate repository after plugin lifecycle and packaging conventions mature.

The plugin's responsibility:

- WebBlocks UI release artifact records
- source dist validation
- safe local publish preparation
- controlled local static publish dry-runs and apply runs
- publish run history
- manifest and checksum generation
- CDN health checks

The WebBlocks UI build stays in the WebBlocks UI repository. The plugin does not build WebBlocks UI. It receives or validates release artifacts, records local metadata for first-party CDN paths, and can publish validated files into the configured local/project-owned static target. External production CDN deployment is intentionally deferred and must remain explicit.

Our own products may consume `cdn.webblocksui.com` for pinned first-party assets. External user documentation should continue to recommend GitHub or jsDelivr CDN consumption unless that policy changes separately.

CDN rules for the pilot:

- use versioned paths
- do not use `latest`
- never mutate an existing versioned artifact directory
- do not delete old versioned CDN directories during normal publish
- run dry-run before apply when operating manually
- block checksum mismatches instead of replacing files silently
- prefer static serving over Laravel route streaming

## WebBlocks UI Manager Publish Flow

Prepare release metadata and checksums:

```bash
php artisan webblocks-ui-manager:prepare-release v2.7.9 --artifact=/path/to/webblocks-ui.css --artifact=/path/to/webblocks-icons.css --artifact=/path/to/webblocks-ui.js
```

Dry-run the publish:

```bash
php artisan webblocks-ui-manager:publish-release v2.7.9 --dry-run
```

Apply the local publish:

```bash
php artisan webblocks-ui-manager:publish-release v2.7.9
```

The admin release detail screen exposes the same dry-run and publish actions when the plugin is enabled, compatible, and the user has `webblocks-ui-manager.publish`. The real publish action uses a confirmation modal. Dry-run is non-destructive.

Required settings:

- `WEBBLOCKS_UI_MANAGER_ENABLED=true` enables the plugin.
- `WEBBLOCKS_UI_MANAGER_CDN_BASE_PATH=cdn/webblocks-ui` controls the local/project-owned static root under `public/`.
- `WEBBLOCKS_UI_MANAGER_CDN_BASE_URL` is optional display metadata for generated public URLs.
- `webblocks-plugins.webblocks_ui_manager.expected_dist_files` lists required dist filenames.

Publish validation checks:

- release metadata exists and is not draft
- release version is semver-like
- release CDN path equals the configured root plus the release version
- expected dist files are present
- source files exist inside the project root and are not symlink escapes
- artifact target paths stay inside the configured CDN root
- stored artifact checksums match current source files and manifest metadata
- existing published files either match checksums and are skipped or block the run
- existing manifest content must match prepared manifest content

Publish runs are stored in `webblocks_ui_manager_publish_runs` with mode, status, target paths, operation details, and secret-free failure messages.
