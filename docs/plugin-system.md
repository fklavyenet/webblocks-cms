# WebBlocks CMS Plugin System

This document records the planned architecture for the WebBlocks CMS plugin system. It is documentation-only for now: no runtime plugin loader, route registration, migration runner, command registration, or admin menu implementation exists as part of this decision.

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

Pseudo API:

```php
WebBlocksPlugin::make('webblocks-ui-manager')
  ->label('WebBlocks UI Manager')
  ->version('1.0.0')
  ->requiresCms('^1.33')
  ->provider(WebBlocksUiManagerServiceProvider::class)
  ->adminMenu(fn (AdminMenuRegistry $menu) => $menu->addToGroup(
    group: 'system',
    item: AdminMenuItem::make('webblocks-ui-releases')
      ->label('WebBlocks UI Releases')
      ->icon('package')
      ->route('webblocks.plugins.webblocks-ui-manager.releases.index')
      ->permission('webblocks-ui-manager.view')
  ))
  ->permissions([
    PluginPermission::make('webblocks-ui-manager.view')->label('View releases'),
    PluginPermission::make('webblocks-ui-manager.publish')->label('Publish CDN artifacts'),
    PluginPermission::make('webblocks-ui-manager.settings')->label('Manage settings'),
  ])
  ->routes(fn (PluginRouteRegistry $routes) => $routes->admin(__DIR__.'/../routes/admin.php'))
  ->commands([
    PublishWebBlocksUiReleaseCommand::class,
  ])
  ->settings(WebBlocksUiManagerSettings::class);
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

## Plugin Lifecycle

The full lifecycle target:

1. discover
2. install
3. enable
4. disable
5. health/status
6. upgrade
7. uninstall or decommission, in a later destructive-data design

The first implementation target is intentionally smaller:

- discovery
- registry
- enabled configuration
- `System -> Plugins` listing
- admin menu registration
- permission registration
- route ownership guards

That minimum gives CMS a safe host boundary before plugins gain deeper lifecycle behavior.

### Phase 1 Implementation Note

The initial Phase 1 runtime now includes:

- `PluginDefinition`, `PluginRegistry`, `PluginMenuItem`, and `PluginPermission` value objects under the package `Support\Plugins` namespace
- deterministic validation for kebab-case handles, duplicate handles, duplicate menu item keys, semver-like versions, and handle-prefixed plugin permissions
- config-backed enabled state through `config/webblocks-plugins.php`
- a package-owned `System -> Plugins` listing at `/webadmin/system/plugins`
- route guard coverage proving `/webadmin` remains canonical while CMS-owned `/admin` and Laravel `/cms` routes remain absent

This phase still does not include dynamic Composer plugin discovery, plugin route loading, plugin migrations, plugin commands, install/enable/disable UI actions, public plugin routes, marketplace/catalog behavior, or WebBlocks UI Manager business logic. Config-backed enabled state is intentionally a bridge; a later lifecycle phase may move install/enable/disable state to persistent storage.

## Testing And Release Guardrails

The plugin system must be protected by route ownership, package boundary, and coexistence tests.

Required guardrails:

- plugin route ownership is testable
- core CMS installs show no plugin menus when no plugins are enabled
- disabled plugin menus do not render
- disabled plugin routes and actions are unavailable or fail authorization
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
