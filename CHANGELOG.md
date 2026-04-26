# Changelog

## [Unreleased]

- Added block UI renderer contract documentation for aligning CMS public blocks with WebBlocks UI primitives and patterns.
- Verified WebBlocks UI primitives against the shipped CDN assets used by the CMS and documented the current UI gap for `wb-prose`.
- Stabilized public layout and slot wrapper structure by keeping the existing public shell and removing the forced sidebar card wrapper.
- Added public layout and slot structure feature tests.
- Added a public layout mode resolver.
- Added sidebar-aware public shell composition while keeping `content` mode reserved until reliable metadata exists.
- Added tests for public layout mode behavior.
- Promoted Hero to a first-class WebBlocks UI-aligned block.
- Added Hero admin form and translation support.
- Added Hero public rendering tests.
- Normalized public button variant rendering.
- Added structured hero and promo CTA rendering through child Button blocks.
- Added button rendering tests.
- Aligned public `columns` rendering with shipped WebBlocks grid, card, stat, and link-list primitives through explicit parent variants.
- Added a `Columns Variant` control in the admin block form for `cards`, `plain`, `stats`, and `links`.
- Kept `card-grid` on the shipped card/grid primitives while deferring a first-class replacement for `feature-grid`.
- Added public and admin coverage for columns variant rendering and persistence.
- Improved public `code` block rendering with safe escaped `<pre><code>` output.
- Added minimal public TOC support using anchored heading links and shipped link-list primitives.
- Stabilized FAQ rendering as a simple card-based structure while deferring true accordion behavior.
- Removed remaining invalid public uses of `wb-prose` and `wb-cluster-3`.
- Completed Phase 3 public block alignment.


## 1.0.5

- Inline release helper scripts into the GitHub Actions release workflow.
- Remove the obsolete local `scripts/` directory.
- Keep CMS product identity and version centralized in `App\Support\WebBlocks`.

### Stability & Integrity
- Data model unified around translation tables for page identity and translatable block content.
- Legacy page title and slug storage removed from active page identity handling.
- Multisite, locale, navigation, and block translation integrity hardened without changing public routing behavior.
- Request-level validation improved so invalid translation, block locale, and cross-site navigation writes fail before DB exceptions where practical.
- Runtime URL generation and public route resolution verified to stay aligned across pages, navigation, and admin previews.
- Revision restore, clone, export/import, and legacy import reconstruction paths hardened while keeping compatibility normalization isolated.

### Internal
- Legacy compatibility paths isolated to reconstruction, import, migration, and backfill workflows.
- Contact form submit and success copy moved out of block settings and treated as translation-owned content.
- Extensive integrity, regression, and edge-case coverage added across multisite, multilingual, validation, URL, and reconstruction flows.
- Refine the fresh CMS welcome screen with a stronger WebBlocks UI-native product introduction and clearer first actions.
- Add development and release workflow documentation, clarify the dev installed-version synchronization policy, and document the local development update boundary in README.

## 1.0.4

- Fix public slider inline JavaScript syntax issue.
- Move public consent synchronization JavaScript into a CMS core static asset.
- Move public slider behavior into a CMS core static asset.
- Move public footer fallback CSS into a CMS core stylesheet.
- Document CMS core public asset boundaries and keep install-level overrides separate.

## 1.0.3

- Make the page translation site integrity migration fully retry-safe by skipping already-created indexes and constraints during partial upgrade recovery.

## 1.0.2

- Fix MariaDB upgrade failure in the page translation site integrity migration by avoiding removal of indexes required by foreign key constraints.

## 1.0.1

- Reorder the admin sidebar so System appears before Maintenance.

## 1.0.0

First stable release of WebBlocks CMS.

- Introduces a complete block-based CMS with multisite support
- Adds role-based access control (`super_admin`, `site_admin`, `editor`)
- Adds Editorial Workflow V1 (`draft`, `in_review`, `published`, `archived`)
- Adds Page Revisions and Restore V1
- Adds Install Wizard V1 for browser-based setup
- Includes media management, navigation, and page builder
- Includes Export / Import, Backup / Restore, and Updates system

## 0.4.0

- Add Users Phase 1 with admin-managed user system including create, edit, delete, active/inactive state, and last login tracking.
- Add Users Phase 1.5 with search, role/status filters, and improved admin UX.
- Add Users Phase 2 with role-based user model using `super_admin`, `site_admin`, and `editor`.
- Add install-level users with site-scoped access via `site_user` assignments.
- Add server-side enforcement of site access across major admin areas.
- Add `super_admin`-only access to system-level features including users, updates, backups, and settings.
- Maintain backward compatibility by keeping `is_admin` as a temporary bridge while transitioning to role-based authorization.

## 0.3.3

- Add Visitor Reports Phase 2 on top of the stable `0.3.x` line so installed CMS sites can receive the update through the normal updater.
- Extend public visitor tracking with sanitized nullable `utm_source`, `utm_medium`, and `utm_campaign` capture, plus optional `CMS_VISITOR_UTM_ENABLED` control.
- Expand `/admin/reports/visitors` with Top Campaigns, Source Breakdown, and Medium Breakdown cards that continue to respect date range, site, and locale filters.
- Add a compact Visitor Summary widget to `/admin` with the last 7 days of page views, unique visitors, and top page context.
- Document the Phase 2 tracking model, campaign reporting behavior, privacy notes, limits, and dashboard summary updates in the Markdown docs.

## 0.3.2

- Promote Visitor Reports V1 release to the 0.3.x line so it becomes visible as the latest stable update.
- No functional changes compared to 0.2.1.

## 0.2.1

- Add Visitor Reports V1 with a compact admin screen at `/admin/reports/visitors`.
- Implement lightweight public visitor tracking backed by the new `visitor_events` table with multisite-aware and locale-aware reporting queries.
- Keep tracking privacy-safe by storing `ip_hash` instead of raw IP addresses and documenting the feature, config, and V1 limits in the README.

## 0.3.1

- Fix release workflow script invocation so release note generation and archive builds run reliably in GitHub Actions even when executable bits are not preserved.
- Include post-merge stabilization after the integrated multisite and site-management release flow.
- Retain the combined multisite, site clone/import, site delete, settings, and sidebar improvements introduced across the 0.3.x line.

## 0.3.0

- Merge the multisite and multilingual foundation into the main line as the base for site-aware admin and public flows.
- Add first-party site clone and export/import workflows for controlled duplication and package-based transfer between installs.
- Add site deletion safeguards, a minimal system settings screen, and reorganized System and Maintenance navigation in the admin sidebar.
- Improve controlled system settings persistence and clarify admin UX across site-management flows.

## 0.2.0

- Ship the first real multisite and multilingual core release with legacy single-site upgrade migrations for existing `0.1.8` installs.
- Preserve default public routing by creating a primary `default` site, seeding `en` as the default locale, backfilling legacy pages and translatable block content, and keeping default-locale URLs prefixless.
- Publish the release through the stable update channel with an explicit `minimum_client_version` of `0.1.8` so installed legacy sites can detect and apply the upgrade through the normal updater.
- Add first-party Export / Import V1 as a portable site package workflow for migration, duplication, and transfer between installs.
- Keep Export / Import explicitly separate from Backup / Restore with dedicated admin screens, package storage, package validation, audit tables, and artisan commands.
- Support site package export/import for site records, locale assignments, pages, page translations, page slots, blocks, block translations, navigation, and optional media/assets with safe archive validation and ID remapping on import.
