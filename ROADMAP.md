# Roadmap

This roadmap captures planned architecture and implementation direction. It does not imply that every listed feature exists in the current runtime.

## Internal Content API / AI-Friendly CMS Operations

### Phase 0 - Documentation And Route Contract

- Done: document `/webadmin/api` as the canonical internal/operator CMS API prefix.
- Done: document resource-style endpoints directly under `/webadmin/api/...`, such as `/webadmin/api/pages` and `/webadmin/api/blocks`.
- Done: document plan-based content operations at `/webadmin/api/content/validate` and `/webadmin/api/content/apply`.
- Done: document the AI Page Building Guide for safe read-only discovery, validate-before-apply workflow, exact block handle discovery, and authenticated draft preview URLs.
- Done: keep the initial route contract generic and CMS-owned, without QuizTem-specific runtime code.

### Phase 1 - Draft-Safe Internal Content API

- Done: add the Bearer token guard backed by `WEBBLOCKS_CMS_INTERNAL_API_TOKEN`.
- Done: keep the API disabled when the token is missing and return controlled JSON responses.
- Done: add discovery endpoints for sites, locales, page layouts, and block types.
- Done: add read-only resource endpoints for pages and blocks directly under `/webadmin/api`.
- Done: add `POST /webadmin/api/content/validate` and `POST /webadmin/api/content/apply` for complete draft page content plans.
- Done: create draft pages, page translations, layout slots, and page-owned block trees through the existing relational content model and block translation/settings writers.
- Excluded: publish, destructive page deletion, resource update/delete/move endpoints, remote fetch, media download/import, site creation, public unauthenticated access, browser session requirements, and overwrite of existing pages or blocks.

### Phase 2A - Navigation And Shared Slot Foundations

- Done: add safe navigation menu/item API foundations under `/webadmin/api/navigation-menus`.
- Done: add safe Shared Slot API foundations under `/webadmin/api/shared-slots`.
- Done: add explicit compatible same-site Shared Slot assignment for existing page slots.
- Done: extend content validate/apply with optional `navigation_menus`, `shared_slots`, and `page_slot_shared_slots` plan sections.
- Excluded: publish, overwrite, destructive delete/replace, remote fetch, media import, site creation, automatic page creation from navigation, and broad AI site-builder behavior.

### Phase 2B - Draft-Safe Navigation/Shared Slot Mutation

- Planned: add optional draft-safe update and move endpoints only where the model shape is clear.
- Done: add `replace_existing_draft_page` validate/apply mode for transaction-scoped replacement of page-owned slots on existing draft pages with optimistic safety guards.
- Planned: add explicit safe clear/replace flows for additional resources only after separate safety designs.
- Planned: support header/navbar construction through structured content operations while keeping it generic CMS behavior.

### Phase 3 - Draft Update/Replace And Existing-Media References

- Planned: add direct draft-safe resource mutation endpoints where they remain useful after validate/apply stabilizes.
- Done: add controlled draft page-owned slot replacement through content validate/apply.
- Planned: support page assets after separate safety design.
- Planned: allow media references by existing media ID only, without remote media import.

### Phase 4 - Explicit Workflow Operations

- Planned: add explicit, permissioned workflow transitions.
- Deferred: optional publish support until a separate design records authorization, review, audit, and safety behavior.

### Later - Public Delivery API

- Possible: add a public headless delivery API if ever needed.
- Keep any public delivery API separate from this internal/operator API.

## Markdown Docs To CMS Sync

This is a planned AI/operator workflow direction for source-linked documentation pages. It does not imply that a runtime feature exists today.

- Phase 0: documentation and source mapping model.
- Phase 1: selected-doc AI/operator dry-run planning workflow.
- Phase 2: draft-safe CMS apply using Internal Content API.
- Phase 3: docs navigation planning and publish workflow.

## Documentation / Product Site Readiness

This is documentation planning only. It does not define a new runtime phase.

- Use the Feature Inventory as a source page for deciding which user-facing docs and future technical product-site pages are missing.
- Keep technical Markdown documentation separate from marketing content, while allowing the same source docs to inform future source-linked CMS documentation pages.

## Public Theme / Visual Tones

This is planned product direction for controlled public styling. It does not imply that runtime code, migrations, admin screens, CSS, or API endpoints exist today.

### Phase 1 - Block Visual Tone Foundation

- Done: add `icon_tone` support for `content_header`, `card_header`, `column_item`, and `link-list-item`.
- Done: expose admin select fields for visual design roles: `default`, `soft`, `brand`, `accent`, `highlight`, `bold`, and `quiet`.
- Done: expose supported tone fields through Internal Content API/content-contract discovery.
- Done: render public classes or token hooks instead of arbitrary inline color styles.

### Phase 2 - Site-Level Public Theme Presets

- Planned: add site-scoped public theme selection under `Sites -> Edit Site -> Theme`.
- Planned: provide theme preview/mockup UI for presets such as `canvas`, `atlas`, `pulse`, `prism`, `graphite`, and `horizon`.
- Planned: output a public body marker such as `data-wb-public-theme` and theme-owned CSS variables for public pages only.
- Planned: keep admin user accent/theme preferences separate from public site themes.

### Phase 3 - Optional Custom Theme Builder

- Deferred: add user-defined theme sets with guarded color fields.
- Deferred: include accessibility and contrast warnings, reset/fallback behavior, import/export portability, and light/dark-aware token pairs.
- Excluded: arbitrary block-level random colors as the default authoring workflow.

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
- Planned: document the proposed `plugins.webblocksui.com` product architecture, including product positioning, candidate implementation options, MVP scope, domain model, public website surface, operator concepts, and possible read-only API direction.
- Planned: use `plugins.webblocksui.com` as the proposed future Plugin Catalog and later Plugin Store surface without implying that the service exists or is live today.
- Planned: start with a read-only Plugin Catalog for discovery, metadata, compatibility, documentation, release information, checksums, and manual download links.
- Planned: later add controlled Plugin Store install/update integration from trusted catalog metadata, while preserving disabled-by-default installs, compatibility-first behavior, explicit setup/migration actions, and inert disabled/incompatible plugins.
- Deferred: Marketplace behavior such as paid plugins, licensing, accounts, reviews, approval workflows, and commercial publisher operations.
- Deferred: automatic remote install, automatic update apply, arbitrary Composer install, production deployment automation, and live site verification.
