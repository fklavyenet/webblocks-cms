# Architecture Decisions

This file records binding architecture decisions for WebBlocks CMS. Longer implementation guidance can live under `docs/`, but these decisions should stay short and product-level.

## CMS Role In Host Applications

- WebBlocks CMS can run as a standalone CMS.
- WebBlocks CMS can also be installed beside another Laravel host product as an optional website and content management layer.
- CMS does not inherit host product domain authority.
- Host products do not inherit CMS authority.
- CMS must stay package-first and avoid route, config, view, and table collisions with the host application.

## CMS Admin Prefix

- The CMS admin prefix must be configurable.
- Coexistence installs should use `/webadmin` as the recommended CMS admin prefix.
- The `/cms` path segment is reserved for CMS static assets.
- CMS admin prefixes must not reuse physical public asset directory segments.
- CMS must not add `/cms` admin aliases or redirects.
- `/admin` may belong to the host application.
- CMS must not assume that `/admin` is always CMS-owned.
- CMS must not restore CMS-owned `/admin` routes.
- Current implementation and target direction must be documented separately until the route prefix is fully configurable.

## Identity And Login

- In a shared Laravel host, the `users` table is the identity and login layer.
- Login and registration are host application responsibilities.
- CMS must not require a separate `/webadmin/login` style login system in host-owned auth applications.
- Guest users who request the CMS admin area should be redirected to the host `/login` page.
- Login must preserve the intended URL so users can return to the originally requested CMS admin page.
- Being authenticated does not imply CMS authorization.

## CMS Admin Resource URLs

- CMS admin routes live under `/webadmin`.
- `/admin` may belong to the host application and must not be used for CMS admin URLs.
- `/cms` is reserved for static CMS assets and must not be used for CMS admin URLs.
- `/webadmin/api` is reserved for token-protected JSON APIs, not browser admin pages.
- Admin resource collection routes use `/webadmin/{resource}`.
- Admin create routes use `/webadmin/{resource}/create`.
- Admin edit routes use `/webadmin/{resource}/{id}/edit`.
- Admin member action routes use `/webadmin/{resource}/{id}/{action}`.
- Admin collection action routes use `/webadmin/{resource}/{action}`.
- Page preview is an authenticated member action at `/webadmin/pages/{page}/preview`.

Reason:

- Co-installed host products may already own `/admin`, while `/cms` is a physical public asset namespace.
- Browser admin actions and JSON APIs have different auth, response, and operational contracts.

Consequences:

- New browser admin actions must follow the member or collection action shape under `/webadmin`.
- Do not introduce alternate page preview routes such as `/webadmin/pages/preview/{page}`, `/webadmin/preview/pages/{page}`, `/admin/...`, `/cms/...`, or `/webadmin/api/...`.
- Admin preview routes must use CMS admin authentication and authorization, must not mutate content, and must not weaken public routing.

## CMS Authorization

- CMS access is controlled by the product-owned CMS membership and role system.
- CMS super admin status is not the same thing as host product admin status.
- Host product admin status is not the same thing as CMS super admin status.
- CMS super admin status is a CMS membership or role record, not a special `users` table record.
- Installer, register, or invite flows must not create duplicate users for the same email.
- Installer or invite flows should first find an existing host user by email, create one only when needed, then attach the CMS membership or role record.

## CMS Plugin Host Architecture

- WebBlocks CMS core is a plugin host.
- Product-specific or domain-specific features belong in plugins instead of CMS core.
- Plugins use a registry-first contract to add admin menus, routes, permissions, settings, commands, migrations, blocks, assets, dashboard widgets, system cards, and extension-slot contributions.
- Core view overrides are forbidden by default; plugins must use documented extension slots or registry contracts.
- WebBlocks UI Manager is an internal/operator plugin for WebBlocks UI release records, safe local artifact metadata preparation, checksum/manifest generation, and first-party CDN management foundations. It must not be bundled into ordinary CMS runtime packages.

Reason:

- WebBlocks CMS now runs as a package beside different host products, so one install must not inherit another product's admin screens, operational commands, tables, or release workflows.
- Keeping domain features out of core protects the reusable CMS product boundary and keeps generic installs focused on content, sites, rendering, users, roles, permissions, and plugin extension infrastructure.
- A registry-first design makes ownership, collisions, authorization, and route boundaries testable.

Consequences:

- CMS core provides the registry, manual ZIP upload/install, enabled state, enabled-only admin route and command registration, settings scaffolding, health/status reporting, typed read-only admin extension slots, plugin-owned block declarations, public asset contribution hooks, and guardrails before deeper plugin lifecycle behavior is added.
- Plugin admin routes default under `/webadmin/plugins/{plugin-handle}/...`, while `/webadmin` remains the canonical CMS admin prefix, `/cms` remains static asset territory, and CMS-owned `/admin` routes must not return.
- Plugin permissions must be handle-prefixed and visible through CMS role management only as plugin-owned capabilities.
- Plugin block declarations must use plugin-owned handles such as `plugin-handle::block-handle` and must not replace core block views or monkey patch core block services.
- Plugin package conventions are enforceable host contracts: handles are kebab-case, settings namespaces and table prefixes are plugin-owned, command names are handle-prefixed, and resolvable collisions fail before active runtime behavior is collected.
- Disabled or incompatible plugins contribute no active runtime behavior, including dashboard widgets, system cards, block hooks, public assets, menus, permissions, routes, commands, settings routes, or health reporter execution.
- `System -> Plugins` reports configured enabled state separately from active compatibility. A plugin can be configured enabled yet remain inactive when its required CMS version constraint is not satisfied.
- WebBlocks UI Manager is not registered, routed, migrated, menu-visible, command-registered, or health-visible in normal CMS installs. It becomes available only when an operator manually uploads its plugin ZIP and explicitly enables it. Existing tables created by v1.32.67 are not dropped automatically.

## Public Themes And Visual Tones

- Public themes are site-scoped identity settings, not install-level preferences or admin-user theme/accent preferences.
- Public block design colors use visual tones and theme-owned tokens by default, not arbitrary per-block hex colors.
- Visual tones describe public design roles such as `default`, `soft`, `brand`, `accent`, `highlight`, `bold`, and `quiet`.
- Semantic status tones such as `info`, `success`, `warning`, and `danger` remain separate and keep their meaning for alerts, validation, form feedback, and status messaging.

Reason:

- Multisite installs need each public site to own its theme independently from the admin UI and from other sites.
- Role-based tones let block plans remain portable across presets while preserving brand consistency, accessibility, and future dark mode support.

Consequences:

- Public theme controls should live under site editing, with `Sites -> Edit Site -> Theme` as the target admin home.
- Public renderers should use classes, attributes, and CSS variables instead of inline arbitrary color styles.
- The first implementation should start with narrow block visual tone fields such as `icon_tone` before adding full site preset selection.
- Future custom theme builders require contrast, fallback, reset, import/export, and accessibility guardrails.

## Markdown-Sourced Documentation Publishing

- Technical documentation source lives in Git Markdown files.
- WebBlocks CMS may publish those docs as source-linked CMS documentation pages through an AI/operator workflow.
- Source-linked documentation pages should be regenerated from the Markdown source rather than edited manually in CMS.
- Mapping must use stable source identifiers and hashes, not content comparison.
- The model must remain target-site agnostic.

Reason:

- Product documentation should stay reviewable with code and release documentation, while WebBlocks CMS can provide the published documentation experience.
- A target-site agnostic source-linking model allows the same Markdown source to be planned for any target documentation site without binding the architecture to one installation.

Consequences:

- Source-linked documentation pages are managed derivatives, not the source of truth.
- The safe update strategy is to regenerate managed page-owned slots from the linked Markdown source when the source hash changes.
- Publishing remains a separate human or approved workflow decision.

## Package-Native Schema Updates

- Runtime-required WebBlocks CMS schema changes must be available to both fresh package installs and existing package-native installs updated through System Updates.
- Fresh schema migrations alone are not sufficient for runtime-required tables or columns.
- Existing package-native installs receive required CMS schema through package update migrations shipped under package `database/migrations/updates` and run from `vendor/fklavyenet/webblocks-cms/database/migrations/updates`.
- System Updates must not be considered successful if new code can become active while required schema is missing.
- Admin, API, and runtime surfaces should fail gracefully with controlled setup/update guidance when required schema is missing.

Reason:

- Package-native installs intentionally skip host/root Laravel migrations during System Updates to avoid colliding with host application migrations.
- The 1.32.146 API token release showed that code can become active while schema is absent when a runtime table is added only to the normal migration path.

Consequences:

- Schema-changing releases must update the fresh install schema path and add package update migrations when existing installs need the same table or column.
- Release validation must include package update migration regression coverage for new runtime-required schema.
- Final release reports for schema changes must state whether the fresh schema path, package update migration, update migration test, and graceful missing-schema behavior were handled.

## Single Canonical Installed CMS Package Root

- Package-native installed CMS source of truth is `vendor/fklavyenet/webblocks-cms`.
- Composer update and CMS System Update must produce the same installed package root layout for WebBlocks CMS package files.
- System Update must not maintain `packages/webblocks-cms` as a second active, updated runtime copy in package-native consumers.
- `packages/webblocks-cms` is reserved for source-maintained development checkouts and may exist in old installs only as a legacy transition leftover.

Reason:

- Two updated runtime trees make version reporting, File Manager inspection, AI guide paths, and operator diagnosis ambiguous.
- Composer-managed consumers should see the same package root whether they update through Composer or the CMS System Update screen.

Consequences:

- Package-native System Updates apply package-rooted artifacts to `vendor/fklavyenet/webblocks-cms`.
- Package-native update migrations run from `vendor/fklavyenet/webblocks-cms/database/migrations/updates` after the package root is replaced.
- Repo-shaped legacy vendor installs are normalized to the flat package root during System Update, and WebBlocks CMS Composer autoload metadata is refreshed away from nested `packages/webblocks-cms` paths.
- Legacy `packages/webblocks-cms` leftovers must not be deleted automatically until a future explicit cleanup tool can prove they are inactive and safe to remove.

## WebBlocks Plugin Ecosystem Catalog Direction

- WebBlocks plugin contracts should be designed for reuse across WebBlocks products, not only WebBlocks CMS.
- WebBlocks CMS remains the first implementation host.
- Shared plugin identity, manifest, compatibility, lifecycle, release metadata, and catalog metadata conventions should support future hosts such as QuizTem, Herne Panel, WebBlocks Publisher, and later WebBlocks products.
- `plugins.webblocksui.com` is the proposed future Plugin Catalog and later Plugin Store domain.
- The near-term target is a Plugin Catalog for discovery, metadata, compatibility, documentation, release information, artifact checksums, and manual download links.
- Product architecture for the proposed catalog should remain implementation-neutral while comparing a dedicated Laravel application, WebBlocks Publisher capability, CMS-powered catalog plugin, or hybrid model.
- Plugin Store behavior for controlled install/update integration is later work and must preserve disabled-by-default installs, compatibility-first behavior, explicit setup/migration actions, and inert disabled/incompatible plugins.
- Commercial Marketplace behavior is deferred.

Reason:

- Product-specific extension points may differ by host product, but plugin identity, package ownership, compatibility metadata, lifecycle safety, release metadata, and catalog discovery should be consistent across the WebBlocks ecosystem.
- A shared contract makes it possible to build catalog and store capabilities without tying every future plugin decision to CMS-specific assumptions.

Consequences:

- CMS manual ZIP upload/install remains the current supported installation method.
- This decision does not approve automatic remote plugin install, automatic update apply, arbitrary Composer install, paid licensing, marketplace accounts, third-party approval workflows, production deployment automation, or live site verification.
