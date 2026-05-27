# Roadmap

This roadmap captures planned architecture and implementation direction. It does not imply that every listed feature exists in the current runtime.

## CMS Plugin System

### Phase 0 - Documentation And Rules

- Add `docs/plugin-system.md` as the canonical plugin architecture document.
- Record the `CMS Plugin Host Architecture` decision in `ARCHITECTURE_DECISIONS.md`.
- Define plugin boundary rules for core vs plugin ownership.
- Document the WebBlocks UI Manager pilot plugin scope without adding runtime code.
- Mark the plugin system as planned architecture in documentation indexes.

### Phase 1 - Minimal Core Plugin Registry

- Done: add initial `PluginDefinition`, `PluginRegistry`, `PluginMenuItem`, and `PluginPermission` contracts.
- Done: add config-backed enabled/disabled state through `config/webblocks-plugins.php`.
- Done: expose registry summaries in `System -> Plugins`.
- Done: collect enabled plugin menu items and plugin permissions from the registry.
- Done: add route ownership guards for plugin namespaces and canonical admin/static route boundaries.
- Done: add tests for absent plugins, disabled plugins, route ownership, permissions, and package boundaries.
- Later: dynamic plugin package discovery, plugin migrations, install/enable/disable UI actions, public plugin routes, and persistent lifecycle state.

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

### Phase 4 - WebBlocks UI Manager Pilot Plugin

- Done: add the first-party `webblocks-ui-manager` pilot plugin as package-owned runtime code, disabled by config by default.
- Done: add plugin-owned release and artifact tables/models using the `webblocks_ui_manager_` namespace.
- Done: add a plugin admin releases UI under `/webadmin/plugins/webblocks-ui-manager/...`.
- Done: add a safe local `webblocks-ui-manager:prepare-release` command foundation that records release metadata, checksums, and manifest data without production CDN deployment.
- Done: define first-party CDN target conventions such as `public/cdn/webblocks-ui/{version}/...`.
- Done: add plugin health checks, settings defaults, dashboard widgets, system cards, menu entries, permissions, route guards, and package boundary coverage.
- Later: real production CDN publish/deploy actions, CDN smoke validation against hosted targets, generic third-party plugin install/update flows, marketplace/catalog behavior, and CMS core WebBlocks UI consumption URL changes.

### Phase 5 - Packaging And Ecosystem Readiness

- Document plugin package conventions.
- Add plugin install documentation.
- Define plugin update and upgrade strategy.
- Explore plugin marketplace or catalog possibilities.
- Define backward compatibility policy for plugin contracts, extension slots, manifests, and route ownership.
