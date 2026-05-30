# Roadmap

This roadmap captures planned architecture and implementation direction. It does not imply that every listed feature exists in the current runtime.

## CMS Plugin System

### Phase 0 - Documentation And Rules

- Add `docs/plugin-system.md` as the canonical plugin architecture document.
- Record the `CMS Plugin Host Architecture` decision in `ARCHITECTURE_DECISIONS.md`.
- Define plugin boundary rules for core vs plugin ownership.
- Document the WebBlocks UI Manager pilot plugin scope before runtime rollout.
- Mark the plugin system as planned architecture in documentation indexes.

### Phase 1 - Minimal Core Plugin Registry

- Done: add initial `PluginDefinition`, `PluginRegistry`, `PluginMenuItem`, and `PluginPermission` contracts.
- Done: add config-backed enabled/disabled state through `config/webblocks-plugins.php`.
- Done: expose registry summaries in `System -> Plugins`.
- Done: collect enabled plugin menu items and plugin permissions from the registry.
- Done: add route ownership guards for plugin namespaces and canonical admin/static route boundaries.
- Done: add tests for absent plugins, disabled plugins, route ownership, permissions, and package boundaries.
- Later: dynamic plugin package discovery beyond manual ZIP upload, plugin migrations, public plugin routes, and database-backed lifecycle state.

### Phase 2 - Plugin Routing, Settings, And Commands

- Done: add admin route registration for enabled plugins under `/webadmin/plugins/{plugin-handle}/...`.
- Done: add plugin settings page foundations under the plugin namespace and link them from `System -> Plugins -> Plugin detail`.
- Done: add a console command registration convention for enabled plugins.
- Done: add basic plugin health and status value objects plus System Plugins visibility.
- Later: add persistent lifecycle statuses beyond simple enabled/disabled state.

### Phase 3 - Extension Slots And Block/Plugin Integrations

- Done: add typed admin extension slot contracts and registry value objects.
- Done: add enabled-only dashboard widget extension slots for read-only plugin cards.
- Done: add enabled-only system card extension slots for read-only plugin cards and links.
- Done: add plugin-owned block type and block pack declaration hooks without core block view overrides.
- Done: add public asset hooks separated into safe head and body-end contribution locations.
- Done: add collision, ownership, disabled-plugin, attribution, admin rendering, asset collection, block hook, and route guard coverage.
- Later: editable plugin widgets, public plugin routes, plugin lifecycle storage, marketplace/catalog behavior, and generic plugin migration runners.

### Phase 4 - WebBlocks UI Manager Operator Plugin

- Done: extract the first-party `webblocks-ui-manager` operator plugin out of bundled CMS runtime and into `plugins/webblocks-ui-manager`.
- Done: make the plugin available only through manual ZIP upload/install and explicit enablement.
- Done: add plugin-owned release and artifact tables/models using the `webblocks_ui_manager_` namespace.
- Done: add a plugin admin releases UI under `/webadmin/plugins/webblocks-ui-manager/...`.
- Done: add a safe local `webblocks-ui-manager:prepare-release` command foundation that records release metadata, checksums, and manifest data without production CDN deployment.
- Done: define first-party CDN target conventions such as `public/cdn/webblocks-ui/{version}/...`.
- Done: add plugin health checks, settings defaults, dashboard widgets, system cards, menu entries, permissions, route guards, and package boundary coverage.
- Done: add a controlled plugin-owned local CDN publish workflow with dry-run/apply command modes, admin actions, publish run records, checksum/manifest/path validation, and idempotent writes into the configured first-party static target.
- Later: external production CDN deployment automation, hosted CDN smoke validation against remote targets, marketplace/catalog behavior, update-server publishing, and CMS core WebBlocks UI consumption URL changes.

### Phase 5 - Packaging And Ecosystem Readiness

- Done: document plugin package conventions for handles, providers, definitions, routes, permissions, settings, commands, migrations/tables, assets, dashboard/system cards, and package boundaries.
- Done: add compatibility foundations for plugin versions, required CMS version constraints, active-vs-configured enabled state, incompatible health/status reporting, and clear `System -> Plugins` messaging.
- Done: harden collision guards for plugin database prefixes and resolvable command names while preserving existing guards for handles, permissions, extension slots, widgets, blocks, assets, and table/prefix conventions.
- Done: add package boundary and route guard coverage proving first-party plugin code stays plugin-owned, disabled and incompatible plugins remain inert, `/webadmin` remains canonical, `/cms` remains static-only from Laravel's route perspective, and `/admin` remains absent.
- Done: document safe local discovery conventions, minimal plugin creation, schema upgrade strategy, and release compatibility policy without adding marketplace, remote installer, arbitrary Composer install, external production CDN deployment, or update-server behavior.
- Done: add manual plugin ZIP upload/install with archive validation, storage-owned install paths, disabled-by-default installed plugins, explicit enablement, and no marketplace, remote store, arbitrary Composer install, or automatic third-party download.
- Done: polish `System -> Plugins` into a concise management UI with lifecycle actions and safe manual-upload uninstall that preserves plugin-owned tables.
- Later: database-backed plugin lifecycle storage, generic migration runners, public plugin routes, editable plugin settings, marketplace/catalog UI, remote package install/update flows, automatic external production CDN deployment, and generic update-server publishing.

### Phase 6 - Ecosystem Plugin Catalog Direction

- Planned: keep plugin contracts reusable across WebBlocks CMS, QuizTem, Herne Panel, WebBlocks Publisher, and future WebBlocks products instead of designing them only for CMS.
- Planned: define shared plugin identity, package/manifest, compatibility, lifecycle, release metadata, checksum/signature, and catalog metadata conventions.
- Planned: use `plugins.webblocksui.com` as the proposed future Plugin Catalog and later Plugin Store surface without implying that the service exists or is live today.
- Planned: start with a read-only Plugin Catalog for discovery, metadata, compatibility, documentation, release information, checksums, and manual download links.
- Planned: later add controlled Plugin Store install/update integration from trusted catalog metadata, while preserving disabled-by-default installs, compatibility-first behavior, explicit setup/migration actions, and inert disabled/incompatible plugins.
- Deferred: Marketplace behavior such as paid plugins, licensing, accounts, reviews, approval workflows, and commercial publisher operations.
- Deferred: automatic remote install, automatic update apply, arbitrary Composer install, production deployment automation, and live site verification.
