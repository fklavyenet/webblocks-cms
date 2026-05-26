# WebBlocks CMS Plugin System

This document records the architecture for the WebBlocks CMS plugin system. Phase 1, Phase 2, and Phase 3 runtime foundations now exist: registry-backed plugin definitions, config-backed enabled state, enabled-only admin route and command registration, plugin settings page scaffolding, health/status reporting, the `System -> Plugins` listing/detail surfaces, typed read-only dashboard and system card extension slots, plugin-owned block declaration hooks, and safe public asset contribution hooks. Deeper features such as dynamic Composer discovery, plugin migrations, install/apply/run lifecycle actions, public plugin route discovery, marketplace behavior, and the WebBlocks UI Manager plugin remain future work.

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
- plugin-owned public routes, only when explicitly declared

Core view override is forbidden by default. Plugins extend CMS only through documented extension slots and registry contracts. A plugin must not replace package views, monkey patch core services, add hidden route files, or rely on arbitrary include side effects.

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

Current registry API shape:

```php
PluginDefinition::make('webblocks-ui-manager')
  ->label('WebBlocks UI Manager')
  ->version('1.0.0')
  ->requiresCms('^1.33')
  ->provider(WebBlocksUiManagerServiceProvider::class)
  ->menu([
    PluginMenuItem::make('releases')
      ->label('WebBlocks UI Releases')
      ->icon('package')
      ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
      ->permission('webblocks-ui-manager.view'),
  ])
  ->permissions([
    PluginPermission::make('webblocks-ui-manager.view')->label('View releases'),
    PluginPermission::make('webblocks-ui-manager.publish')->label('Publish CDN artifacts'),
    PluginPermission::make('webblocks-ui-manager.settings')->label('Manage settings'),
  ])
  ->adminRoutes(__DIR__.'/../routes/admin.php')
  ->commands([
    PublishWebBlocksUiReleaseCommand::class,
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

## Admin Menu Rules

Plugins may add admin menu entries, but every plugin menu entry must be permission-gated.

The preferred behavior is to add items to existing admin groups such as:

- System
- Tools
- Integrations

A top-level plugin menu may be reserved only for a large product surface that would be confusing as a single group item.

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

Disabled plugins must not register admin routes. The enabled-only registrar is intentionally conservative: if a plugin is disabled through `config/webblocks-plugins.php`, its routes are absent rather than present-but-forbidden.

Public routes are opt-in. A plugin that declares public routes must declare ownership clearly enough that route ownership can be tested. Public plugin routes must avoid collisions with site pages, CMS public routes, and host product routes.

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

Editable settings storage and validation schemas are reserved for a later phase.

## Migration And Data Lifecycle Rules

Plugin migrations must not collide with core migrations.

Plugin table names must carry the plugin handle prefix or a documented shortened prefix reserved by the plugin registry. For `webblocks-ui-manager`, table names should use a stable prefix such as `webblocks_ui_manager_`.

Lifecycle states must be distinct:

- Enable: plugin menu, routes, commands, scheduled jobs, widgets, blocks, and actions become available according to permissions and health.
- Disable: plugin menu, scheduled jobs, plugin routes, and plugin actions are unavailable; data remains in place.
- Uninstall: reserved for a future design; by default it must not delete data.
- Decommission or purge: future destructive data deletion flow requiring explicit destructive confirmation.

The first implementation must not make uninstall destructive. Deleting plugin tables, artifacts, uploaded files, or historical records requires a separate explicit destructive confirmation design.

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
- CDN or static hosting should be served by Nginx or another static service when possible
- Laravel route-based asset streaming must not be the default for CDN files
- plugin admin assets must be isolated from core CMS admin assets and publish under a plugin namespace

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

Dashboard widgets render on the super-admin dashboard only when the plugin is enabled and the current user can satisfy the widget permission, if one is declared. System cards render on the `System -> Plugins` screen under the same enabled and permission checks. Both slots are intentionally read-only foundations.

Block hooks are declaration-only foundations. They let enabled plugins expose plugin-owned block types and block packs through the registry, but they do not replace core block contracts, core block views, core block seeders, or block editing services.

## Plugin Lifecycle

The full lifecycle target:

1. discover
2. install
3. enable
4. disable
5. health/status
6. upgrade
7. uninstall or decommission, in a later destructive-data design

The implemented Phase 1 through Phase 3 runtime target is intentionally smaller than the full lifecycle:

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
- enabled-only system card rendering on `System -> Plugins`
- `PluginBlockTypeDefinition`, `PluginBlockPackDefinition`, and `PluginBlockRegistry` for plugin-owned block declarations
- `PluginPublicAsset` and `PluginPublicAssetRegistry` for safe public head and body-end asset declarations
- validation guards for extension keys, widget keys, system card keys, block handles, block pack namespaces, asset handles, plugin ownership, and duplicate declarations
- disabled-plugin inert behavior for dashboard widgets, system cards, block hooks, and public assets
- route guard coverage confirming `/webadmin` and `/webadmin/plugins/...` remain valid while `/admin` and Laravel `/cms` admin routes remain absent

This phase intentionally keeps real marketplace behavior, package installation, plugin migration runners, public plugin routes, editable widgets, core block override hooks, and the WebBlocks UI Manager plugin out of scope. Plugins still must not override package views or monkey patch core services.

## Testing And Release Guardrails

The plugin system must be protected by route ownership, package boundary, and coexistence tests.

Required guardrails:

- plugin route ownership is testable
- core CMS installs show no plugin menus when no plugins are enabled
- disabled plugin menus do not render
- disabled plugin routes and actions are unavailable or fail authorization
- disabled plugin widgets, system cards, block declarations, and public assets are absent
- plugin dashboard/system extension cards render only when enabled and permitted
- plugin-owned block declarations are discoverable without overriding core block contracts
- plugin public assets are collected by safe location and are absent when disabled
- plugin route registration must not restore a CMS-owned `/admin` namespace
- `/webadmin` remains the canonical CMS admin prefix
- `/cms` remains static asset territory, not a Laravel plugin route namespace
- plugin attempts to override core tables, route names, or views should fail tests or diagnostics
- package boundary tests expand to cover plugin-owned routes, views, assets, migrations, and commands
- coexistence tests should cover CMS + QuizTem + plugin scenarios in the roadmap

Plugin tests should include both absent-plugin and disabled-plugin cases so core CMS remains clean in generic installs.

## WebBlocks UI Manager Pilot Plugin Decisions

WebBlocks UI Manager will not be embedded in CMS core.

It may start as either:

- a separate package under `packages/webblocks-ui-manager`
- a host-local plugin for the webblocksui.com install

The preferred long-term model is a separate Composer package or separate repository.

The plugin's responsibility:

- WebBlocks UI release artifact records
- source dist validation
- first-party CDN publish
- manifest and checksum generation
- CDN health checks

The WebBlocks UI build stays in the WebBlocks UI repository. The plugin does not build WebBlocks UI. It receives or validates release artifacts and publishes them to the first-party CDN path.

Our own products may consume `cdn.webblocksui.com` for pinned first-party assets. External user documentation should continue to recommend GitHub or jsDelivr CDN consumption unless that policy changes separately.

CDN rules for the pilot:

- use versioned paths
- do not use `latest`
- never mutate an existing versioned artifact directory
- do not delete old versioned CDN directories during normal publish
- prefer static serving over Laravel route streaming
