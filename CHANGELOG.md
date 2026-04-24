# Changelog

## Unreleased

- Add Privacy-Aware Visitor Reports V2 with a first-party consent cookie, compact public privacy banner, basic anonymous page view tracking by default, and richer full tracking only after explicit acceptance.
- Refine cookie consent UX with a persistent footer `Cookie settings` trigger, a narrower bottom-sheet style public consent panel, a top-right close control, and a dedicated admin `Cookie settings` card.
- Fix the public cookie consent reopen UX so the footer `Cookie settings` link remains available after closing the panel and reopens the same panel in all consent states.
- Add truthful Visitor Reports semantics so page views include anonymous rows while unique visitors, sessions, referrers, campaigns, and device summaries only use consented full-tracking rows.
- Add public privacy settings routes, admin banner control, README documentation, and test coverage for consent-aware tracking behavior.
- Refactor the admin sidebar into focused Content, Reports, Sites, Access, Structure, System, and Maintenance groups so install-level tools are no longer overloaded under System.
- Restructure `README.md` into a v1.0 entry-point overview with focused links to detailed docs.
- Add compact v1.0 documentation pages for installation, getting started, users and permissions, editorial workflow, revisions, and operations.
- Add Install Wizard V1 for fresh installs with browser-based requirements, database, core setup, and first super admin steps.
- Add install state guards so public, admin, and auth entry points guide incomplete installs into setup and lock the installer after completion.
- Add safe first-run environment handling for app key generation, `.env` updates, database validation, and storage link setup within the installer flow.
- Add Page Revisions / Restore V1 for pages with automatic page-scoped revision snapshots across page edits, workflow changes, page translations, and slot/block changes.
- Add admin revision history and in-place restore for pages, including pre-restore safety snapshots, multisite-aware access control, and restore coverage for page fields, page translations, slots, blocks, and block translation rows.
- Keep page revisions explicitly separate from environment Backup / Restore and site Export / Import flows.
- Add Editorial Workflow V1 for pages with `draft`, `in_review`, `published`, and `archived` statuses.
- Add role-based workflow enforcement so `site_admin` and `super_admin` users can publish while `editor` users can only work in draft and submit for review.
- Restrict public page rendering to workflow-published pages while preserving existing block-level visibility rules inside published pages.
- Add compact workflow badges and actions across the page admin flow, plus server-side workflow guards for page editing, translations, and slot/block editing.

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
