---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/plugin-ecosystem-and-catalog
cms_title: WebBlocks Plugin Ecosystem And Catalog
cms_layout: docs
cms_source_id: webblocks-cms:docs/plugin-ecosystem-and-catalog.md
---

# WebBlocks Plugin Ecosystem And Catalog

This document records the next-stage WebBlocks plugin ecosystem direction before implementation starts. It is architecture documentation only. It does not add runtime code, routes, migrations, controllers, API clients, database tables, admin screens, deployment automation, release tags, or version bumps.

## Purpose

The WebBlocks plugin architecture should be ecosystem-wide, not CMS-only. WebBlocks CMS is the first plugin host because it already has plugin system foundations, registry-backed definitions, manual ZIP upload/install, disabled-by-default installed plugins, compatibility checks, and inert disabled or incompatible behavior. The same contract should be reusable by other WebBlocks products when they need package-first extensions with clear ownership and safe lifecycle rules.

Designing the contract only around CMS would make plugin identity, packaging, compatibility, catalog metadata, and security rules harder to share later. The target direction is a shared WebBlocks plugin contract that each product can host through its own extension points while preserving a common model for identity, package inspection, compatibility, enablement, updates, and catalog discovery.

## Product Scope

Possible plugin hosts include:

- WebBlocks CMS
- QuizTem
- Herne Panel
- WebBlocks Publisher
- future WebBlocks products

Each host product may expose different extension points. WebBlocks CMS exposes CMS admin menus, plugin routes, permissions, settings, commands, migrations, blocks, assets, health checks, dashboard widgets, and system cards. QuizTem, Herne Panel, WebBlocks Publisher, and future products may expose different product-owned registries and screens.

Even when extension points differ, plugin identity, packaging, compatibility, lifecycle, and catalog metadata should follow shared conventions across the WebBlocks ecosystem. A plugin may support one host product or multiple host products, but product compatibility must always be explicit and inspectable before enablement.

## Plugin Identity Standard

Every ecosystem plugin should have one stable handle:

- kebab-case
- globally unique across the WebBlocks ecosystem
- stable across releases
- used as the default prefix for routes, permissions, settings, commands, tables, assets, and package identity

Handle-prefixed ownership keeps plugin behavior attributable and collision-safe. Admin routes, public routes, permission strings, settings namespaces, command names, database table prefixes, asset handles, package names, artifact paths, and catalog records should all be traceable to the owning plugin handle.

Product compatibility must be explicit. A plugin may support only WebBlocks CMS, only QuizTem, only Herne Panel, only WebBlocks Publisher, or a supported combination of host products. Unsupported host products must treat the plugin as incompatible and inert.

## Plugin Package / Manifest Direction

The ecosystem should move toward a shared manifest concept that can be inspected before installation and before enablement. The exact implementation API can evolve, and host products may adapt the manifest into product-specific registries, but ownership and compatibility must remain inspectable before enablement.

Future shared manifest metadata should include:

- `handle`
- `label`
- `vendor` or `author`
- `version`
- supported host products
- required host product versions
- required PHP and Laravel versions when applicable
- provider classes per host product when needed
- permissions
- admin menu contributions
- route declarations
- commands
- migrations
- blocks or block packs
- assets
- settings
- health checks
- release notes
- checksum and signature metadata

The manifest should let a host answer safety questions before enabling code: which products the package supports, which product versions are required, which providers may boot, which routes and commands would be registered, which permissions would be created, which tables and settings namespaces are owned, which migrations may be offered for explicit setup, and which artifacts can be verified.

## Plugin Catalog / Store Direction

`plugins.webblocksui.com` is the proposed future catalog/store surface. This document does not imply that the domain exists, is deployed, or is live.

The terms should stay distinct:

- Plugin Catalog: discovery, metadata, compatibility, documentation, screenshots, support links, release metadata, security status, and download links.
- Plugin Store: later install/update integration from trusted catalog metadata into a host product.
- Marketplace: future commercial features such as accounts, licensing, paid plugins, reviews, approval workflows, publisher profiles, and revenue workflows.

The first milestone should be a Plugin Catalog, not a full commercial Marketplace. A catalog can establish the metadata contract, compatibility matrix, artifact checksums, documentation links, and safe manual download path without adding remote install, automatic updates, paid licensing, or commercial approval workflows.

For product positioning, MVP scope, candidate implementation models, public website surfaces, operator concepts, and API planning for the proposed catalog product, see [Plugin Catalog Product Architecture](plugin-catalog-product-architecture.md).

## Recommended Phases

1. Phase 1: Documentation and metadata contract.
2. Phase 2: Catalog server/data model planning for `plugins.webblocksui.com`.
3. Phase 3: CMS admin `Browse Plugin Catalog` read-only UI. Implemented as `/webadmin/plugins/catalog`, using `WEBBLOCKS_PLUGIN_CATALOG_BASE_URL` / `webblocks-plugins.catalog.base_url` and defaulting to `https://plugins.webblocksui.com`.
4. Phase 4: Manual ZIP download/install flow linked from catalog metadata.
5. Phase 5: Controlled `Install from Catalog` flow, still disabled-by-default after install.
6. Phase 6: Plugin update checks and update availability. Implemented for CMS `System -> Plugins -> Registered Plugins` when installed plugin handles have newer compatible catalog releases with complete artifact metadata.
7. Phase 7: Controlled plugin update apply flow. Implemented as a super-admin POST action that reuses catalog checksum verification and plugin ZIP validation while preserving lifecycle state and leaving migrations explicit.
8. Phase 8: Marketplace/licensing/commercial features.

Each phase must preserve disabled-by-default installation, compatibility-first behavior, and explicit setup or migration actions. Remote metadata may help users discover, assess, install, or explicitly update plugins, but it must not silently enable plugins, run migrations, apply updates, or bypass host-product compatibility rules.

The CMS catalog browser lists public WebBlocks CMS-compatible catalog plugins and latest compatible release metadata. The CMS now has explicit super-admin catalog install/update actions for trusted ZIP artifacts with complete checksum metadata, but catalog browsing itself does not install Composer packages, enable plugins, run migrations, register routes, register commands, register providers, register permissions, or activate plugin state from remote data.

## Security And Safety Rules

Catalog/store unavailable states must not break installed plugin management. Installed plugin listing, enable/disable controls, setup guidance, health status, and uninstall behavior should continue to work from local state.

Remote catalog data must not automatically enable plugins. Remote catalog data must not automatically run migrations. Remote catalog data must not automatically apply updates. Catalog-backed updates require an explicit super-admin POST action and trusted artifact metadata. Automatic arbitrary Composer install is out of scope unless a future architecture decision explicitly approves it.

ZIP artifacts must be verified before installation. Verification should block path traversal, hidden metadata files, root escape, public executable surprises, route collisions, permission collisions, table-prefix collisions, host-product boundary collisions, symlink escapes, forbidden install targets, malformed manifests, incompatible host products, and checksum or future signature mismatches.

Incompatible, disabled, missing-files, or insecure plugins must remain inert. They must not register routes, commands, menus, permissions, settings routes, migrations, scheduled jobs, blocks, assets, widgets, health reporters, or other active runtime behavior.

Uninstall remains disabled-first and storage-owned. Ordinary uninstall must remove only the storage-owned plugin package directory and local enabled-state records. It must not drop plugin-owned database tables unless a future destructive cleanup tool is intentionally designed with explicit confirmation.

Core view override remains forbidden by default. Plugin extension must use documented registries or extension slots. Plugins must not replace package views, monkey patch product services, add hidden route files, or rely on arbitrary include side effects.

## Catalog Metadata Requirements

Plugin Catalog records should include:

- plugin listing metadata: handle, label, description, vendor/author, categories, tags, current stable release, documentation URL, screenshots, support URL, and source or issue tracker URL when available
- release metadata: version, release date, release notes, artifact URLs, checksums, future signatures, minimum host requirements, upgrade notes, and deprecation status
- compatibility metadata: supported host products, supported host product version constraints, required PHP/Laravel versions when applicable, supported platform services, and migration/setup requirements
- security advisory and deprecation metadata: affected versions, patched versions, severity, advisory links, insecure flags, deprecated releases, replacement guidance, and blocked install/update status
- artifact verification metadata: checksum algorithm, checksum value, artifact size, future signature data, signing key identity, and integrity status
- documentation, screenshot, and support URLs: public docs, changelog, setup guide, screenshots, support contact, issue tracker, and vendor profile
- host product compatibility matrix: one row per supported host product, including product handle, product label, compatible version constraints, provider class metadata when needed, extension points used, setup requirements, and known limitations

Catalog metadata should be useful before download, before install, before enablement, before setup/migration, and before update apply.

## Relationship To WebBlocks Publisher

The future Plugin Catalog may reuse ideas from the existing WebBlocks Publisher/update metadata flow, including release metadata, artifact checksums, manifests, and hosted distribution concepts. Plugin catalog publishing should still be documented as a separate product capability.

This document does not assume implementation inside the current WebBlocks Publisher code. `plugins.webblocksui.com` can later be powered by WebBlocks Publisher, a dedicated catalog app, or a WebBlocks CMS site with a catalog plugin. The implementation choice should be made after the catalog metadata contract and product boundary are clear.

## Relationship To Existing CMS Plugin System

See [Plugin System](plugin-system.md) for the current WebBlocks CMS plugin host architecture.

CMS manual ZIP upload/install remains the current supported plugin installation method. Uploaded plugins install under storage-owned paths, remain disabled by default, and require explicit enablement and setup/migration actions. Disabled and incompatible plugins remain inert.

Catalog/store work should build on the existing CMS plugin lifecycle instead of replacing it. Future catalog discovery, manual download links, install-from-catalog flows, update checks, and controlled update apply actions should reuse the same handle, compatibility, disabled-by-default, setup-required, schema readiness, permission, route ownership, and uninstall safety rules.

## Non-Goals For The First Documentation Phase

This phase does not include:

- runtime implementation
- automatic remote install
- automatic plugin update apply
- paid marketplace behavior
- license server behavior
- third-party approval workflow
- production deployment automation
- live site verification
