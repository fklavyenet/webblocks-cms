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

- Add `PluginManifest` or `PluginDefinition` contracts.
- Add `PluginRegistry`.
- Add an enabled/disabled configuration model.
- Integrate plugin contributions with the admin menu registry.
- Integrate plugin permissions with the permission registry.
- Add a `System -> Plugins` listing.
- Add route ownership guards for plugin namespaces.
- Add tests for absent plugins, disabled plugins, route ownership, permissions, and package boundaries.

### Phase 2 - Plugin Routing, Settings, And Commands

- Add admin route registration for enabled plugins.
- Add plugin settings pages under the plugin namespace or `System -> Plugins -> Plugin detail`.
- Add a console command registration convention.
- Add plugin health and status checks.
- Add plugin lifecycle statuses beyond simple enabled/disabled state.

### Phase 3 - Extension Slots And Block/Plugin Integrations

- Add dashboard widget extension slots.
- Add system card extension slots.
- Add block registry plugin hooks.
- Add public asset hooks for head and body-end contributions.
- Define typed slot contracts and value objects for plugin contributions.

### Phase 4 - WebBlocks UI Manager Pilot Plugin

- Add release tables and models in the plugin package or host-local plugin.
- Add a publish command for release artifacts.
- Generate checksums and manifests.
- Publish first-party CDN files under versioned static paths such as `public/cdn/webblocks-ui/v2.7.8/...`.
- Add an admin releases UI.
- Add plugin health checks.
- Add first-party CDN smoke validation.

### Phase 5 - Packaging And Ecosystem Readiness

- Document plugin package conventions.
- Add plugin install documentation.
- Define plugin update and upgrade strategy.
- Explore plugin marketplace or catalog possibilities.
- Define backward compatibility policy for plugin contracts, extension slots, manifests, and route ownership.
