# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## 1.32.108

- Fix native MySQL/MariaDB pre-update backups by restoring the Symfony Process import required by direct local dump execution.

## 1.32.107

- Reissue the native update package after guarding release preparation against dirty package sources that can publish stale version files.

## 1.32.106

- Remove remaining active DDEV command hints from development docs, WebBlocks UI Manager plugin guidance, and backup/search test fixtures so native `composer` and `php artisan` examples stay consistent.

## 1.32.105

- Fix Plugin Catalog install availability for current latest-compatible API responses that return release metadata under `data.release` and artifact metadata under sibling `data.artifact`.
- Switch active local-development, install, testing, and operations guidance from DDEV commands to native `composer` and `php artisan` commands.
- Remove the old container backup/restore execution mode so MySQL/MariaDB backups and restores use local CLI binaries in `auto` or `direct` mode.
- Keep `.env.example` on the canonical `WEBBLOCKS_PUBLISHER_*` publishing keys without the older update-server aliases.

## 1.32.104

- Fix System Updates false-success handling by verifying the applied WebBlocks CMS code version against the target release before recording a successful run or showing a success flash.
- Clear the full Laravel optimization cache during System Update maintenance and keep equal current/latest release states non-actionable even when stale update metadata says an update is available.
- Fix native update publishing so cached-config runs detect canonical `WEBBLOCKS_PUBLISHER_*` values from the project `.env` and report key configured status without exposing the Publisher token.

## 1.32.103

- Standardize CMS release publishing on the shared WebBlocks Publisher environment keys: `WEBBLOCKS_PUBLISHER_URL`, `WEBBLOCKS_PUBLISHER_TOKEN`, `WEBBLOCKS_PUBLISHER_PRODUCT`, and `WEBBLOCKS_PUBLISHER_CHANNEL`, with the direct publish endpoint, `webblocks-cms`, and `stable` as defaults.
- Remove legacy publisher environment aliases from CMS release publishing so update publication now fails controlled when only old CMS-specific keys are present.

## 1.32.102

- Fix Plugin Catalog artifact parsing for current WebBlocks Plugins API responses so nested `latest_release.artifact` filename, size, checksum, download URL, validation status, and scan status render correctly and drive catalog install availability.

## 1.32.101

- Fix Plugin Catalog artifact parsing for current WebBlocks Plugins API responses so nested `latest_release.artifact` filename, size, checksum, download URL, validation status, and scan status render correctly and drive catalog install availability.

## 1.32.100

- Add a safe Plugin Catalog install bridge from catalog detail pages: compatible releases with controlled download URL, filename, and SHA-256 metadata can be downloaded server-side, checksum-verified, and installed through the existing disabled-by-default plugin ZIP installer.
- Show catalog artifact status, release status, filename, size, download URL, and SHA-256 metadata on plugin details, with a clear unavailable state when no downloadable artifact exists.
- Keep catalog links separate from install actions and align the Catalog Plugins listing action cell with the standard WebBlocks UI table action group.

## 1.32.99

- Polish Plugin Catalog Browser diagnostics so admin UI hides catalog server URLs and raw CMS version debug text, shows a friendly unavailable state, falls back internally to the built-in public catalog URL when config is missing or blank, and sends the canonical WebBlocks CMS product version to catalog API requests.

## 1.32.98

- Add read-only Plugin Catalog detail pages at `/webadmin/plugins/catalog/{handle}` with catalog index plugin labels and View details actions linking into plugin metadata, compatibility, latest compatible release, release notes/highlights, documentation/support/catalog links, artifact URL, SHA-256 checksum, artifact filename, and artifact size.
- Polish Plugin Catalog detail Manual ZIP Install guidance with operator steps, safe Copy download URL and Copy checksum helper actions, and local installed/enabled state labels sourced only from the CMS `PluginRegistry`.
- Keep catalog detail data display-only with no remote install, enable, migration, route/provider/command/permission registration, update apply, or remote behavior execution.

## 1.32.97

- Add the read-only Plugin Catalog Browser at `/webadmin/plugins/catalog`, reachable from the `System -> Plugins` Browse Plugin Catalog action, for display-only WebBlocks CMS-compatible catalog metadata.
- Add Plugin Catalog API configuration through `WEBBLOCKS_PLUGIN_CATALOG_BASE_URL`, `WEBBLOCKS_PLUGIN_CATALOG_TIMEOUT_SECONDS`, and `WEBBLOCKS_PLUGIN_CATALOG_CONNECT_TIMEOUT_SECONDS`, with unavailable-catalog UI kept controlled.
- Keep catalog browsing display-only with no remote install, enable, migrations, providers, routes, commands, updates, or executable registration from remote plugin data.

## 1.32.96

- Polish Contact Messages list row actions so rows expose only View message and modal-confirmed Delete controls, preserving separate editorial and notification status columns plus bulk deletion.
- Update the Contact Messages View action accessible and title text to `View message`.
- Use the standard confirmed destructive action pattern for Contact Messages row deletion.
- Confirm and document that scored Contact Form spam remains stored/quarantined for admin review and is not auto-deleted by score, while honeypot hits may be discarded before storage.

## 1.32.95

- Preserve System Updates history pagination state after deleting a run, including per-page selection and fallback to the previous valid page when the deleted row emptied the last page.
- Paginate the CMS System Updates history table with a compact `Per page` selector, dedicated `history_page` / `history_per_page` query parameters, and preserved details/delete modal actions on paginated rows.
- Add CMS System Updates history deletion with WebBlocks UI trash icon actions, confirmation modals, CSRF-protected DELETE forms, and history-only deletion safeguards.
- Align `System -> Updates` with the current QuizTem interaction standard: WebBlocks UI card headers/actions, release notes and package safety accordions, compact diagnostics disclosure, disabled unavailable install action, and modal-based history details.
- Stop running broad catalog seeding and `block-types:sync-core` during normal System Update applies; package updates now stay focused on release package apply, required update migrations, cache clears, run recording, and installed-version persistence.
- Add `webblocks:catalog-repair` with dry-run and scoped repair options for block types, slot types, page layouts, and icons while preserving custom catalog rows.

## 1.32.90

- Add native/local CMS update publishing with `composer release:prepare`, `composer release:publish-update -- --dry-run`, and `composer release:publish-update`, backed by `webblocks:publish-update` and direct WebBlocks Publisher API uploads for `webblocks-cms` on the `stable` channel.
- Remove GitHub-based release publishing and delete `.github` workflows; release artifacts, checksums, metadata validation, and update-server verification now happen locally without GitHub release asset URL assumptions.
- Simplify the `System -> Updates` summary so the main status compares the running CMS code version with the latest published release and no longer exposes stored/effective/source checkout terminology in the visible summary.
- Keep stored installed version as update-history persistence and a collapsed technical detail while ensuring stale stored values do not make Install Update actionable.

## 1.32.89

- Redesign the `System -> Updates` screen into a guided operator flow with a friendly status hero, `Release Preview`, actionable-only `Update Options`, quieter `Update History`, and last-position collapsed `Technical Details` while preserving strict package update behavior.
- Clarify source-maintained checkout status on System Updates so effective local code versions can be shown without mutating `system.installed_version` during page rendering.

## 1.32.88

- Fix System Updates availability detection for source-maintained checkouts so already-present local code versions do not show a stale actionable update, and move resolved failed update runs into collapsed history instead of the main latest-run warning.

## 1.32.87

- Simplify the `System -> Updates` screen into stacked Update Summary, Update Options, and Release Details cards so status metadata, backup/download choices, update actions, and structured release notes have clearer hierarchy.
- Add `composer format:changed` as a faster focused formatting validation path for small hotfixes while keeping `composer format:test` as the full repository formatting baseline.

## 1.32.86

- Verify the structured System Updates release-details pipeline end to end by publishing this release with populated `meta.release_details` fields for title, summary, highlights, changes, compatibility notes, operator notes, and technical notes.
- Keep the legacy `release_notes` fallback meaningful for older update clients while compatible clients can render grouped release details on the System Updates screen.

## 1.32.85

- Roll release metadata publishing forward so the tag workflow submits structured release detail fields in top-level and nested payload shapes, and prefers the changelog release section over terse tag text for the legacy `release_notes` fallback.
- Extend update metadata parsing to read structured release details from update-server `meta.release_details` or `meta.details` payloads when the publisher exposes nested metadata there.

## 1.32.84

- Improve `System -> Updates` so available releases can show structured operator-readable release details before installation, including title, summary, highlights, fixes, compatibility, migration, asset, operator, and technical note groups while keeping package URLs, checksums, diagnostics, and low-level server values inside collapsed technical details.
- Publish release metadata with structured release detail fields in the tag workflow so update-server payloads can provide the richer notes while preserving the legacy `release_notes` fallback.
- Add Composer native local daily workflow aliases, including `composer native:doctor` and a read-only `composer native:smoke` check that reuses the native doctor and verifies the HTTPS `.test` APP_URL returns 200 or 302 without printing secrets.
- Document the daily native macOS local workflow, DDEV 80/443 port-conflict handling, Nginx/PHP-FPM/Redis checks, the separate MariaDB `3307` datadir/socket pattern, and restore-after-smoke steps.
- Let backup restore auto mode use direct MySQL/MariaDB CLI execution for native HTTPS `.test` local environments instead of falling back to `ddev exec` merely because `.ddev` files exist, while preserving DDEV behavior for `.ddev.site` URLs or explicit `CMS_BACKUP_EXECUTION=ddev`.
- Improve native restore diagnostics with secret-safe database connection context and document native backup restore requirements, including custom MariaDB ports such as `3307`.
- Fix native maintenance checkouts so package install guards fall back to the host installer when the package notice route is unavailable, avoiding a fresh-install 500 on `https://webblocks-cms.test`.
- Clarify native local documentation with Intel Homebrew Nginx certificate/server paths, PHP-FPM listen detection, DDEV router 80/443 port conflict handling, and a safe separate MariaDB datadir/port option for machines with existing MySQL data.
- Add the read-only `webblocks:doctor-native-local` command for native HTTPS `.test` readiness checks covering PHP, Composer, extensions, MySQL/MariaDB, Redis, Nginx, mkcert, APP_URL, hosts, TLS certificate paths, and writable runtime directories without printing secrets or mutating the machine.
- Document the macOS native local development path alongside the existing DDEV workflow, including trusted HTTPS-only `.test` domains, mkcert TLS setup, Homebrew PHP/Nginx/MySQL/Redis checks, native command equivalents, and a safe rollback plan without removing DDEV support.

## 1.32.83

- Prepare v1.32.83 as a CMS runtime and release-boundary hardening patch by documenting the no-Vite/no-npm/no-Tailwind convention, removing remaining build-chain ignore/update remnants, and adding release artifact guards that fail if Vite config, Laravel Vite plugin references, `@vite`, npm build scripts, lockfiles, `node_modules`, `public/build`, hot-file assumptions, Tailwind config, or PostCSS config return to the CMS runtime boundary.
- Keep CMS-owned assets on the established `public/cms` package/runtime asset path and keep WebBlocks UI consumed from pinned published CDN assets instead of compiling UI source inside WebBlocks CMS.

## 1.32.82

- Polish the `Maintenance -> Backups` admin screen so latest-status and recommendation cards render before the compact filter toolbar, the Backups listing card follows immediately after the filters, and row actions use the shared `td.wb-table-actions > .wb-action-group` table-action standard without globally forcing generic action groups to nowrap.
