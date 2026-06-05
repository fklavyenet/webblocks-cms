# WebBlocks CMS Changelog Archive - 1.32.x

This file contains older 1.32.x release notes moved from CHANGELOG.md. Release headings and notes are preserved as written.

## 1.32.115

- Remove the local root `layouts.admin` compatibility wrapper so plugin and package admin views must use `webblocks-cms::layouts.admin`, matching package-consumer installs.
- Pin CMS WebBlocks UI consumption and the default icon manifest source to the newest published `v2.7.12` release.
- Clean the CMS product brand folder down to the canonical logo, mark, favicon, touch icon, and app icon set, with matching root and package assets.
- Regenerate CMS product PNG brand assets so the actual logo mark is visible instead of flat-color output.

## 1.32.114

- Update CMS WebBlocks UI consumption to `v2.7.11` and adopt the refreshed auth brand helper classes on the package-owned login shell.
- Add CMS product brand variants for normal, dark, on-accent/inverse, and high-contrast favicon usage under `/cms/brand`, keeping product shell branding separate from site-level public favicons.

## 1.32.113

- Simplify System Updates into the two-card `Install Update` and `Update Details` flow, moving release notes, update readiness, and last-run details into accordions.
- Replace the main-screen Update History table and row deletion with automatic retained-run pruning, safe last-run detail modals, CLI run inspection/pruning commands, and a downloadable support report.

## 1.32.112

- Fix enabled compatible plugin admin route registration so plugin-owned routes always keep the CMS `/webadmin` web, install, auth, and admin middleware stack before plugin setup and permission checks.
- Keep plugin setup-required screens controlled after CMS authentication while preserving plugin-owned permission decisions for super admins and unauthorized users.

## 1.32.111

- Fix catalog-updated plugin runtime refresh so enabled plugins cannot keep using stale installed package metadata, provider route paths, or registry permission/menu state after `Update from Catalog`.
- Centralize plugin permission resolution so CMS super admins can access active plugin-owned routes consistently while unauthorized users lose matching menu visibility and keep controlled 403 responses.

## 1.32.110

- Add Registered Plugins catalog update availability for installed plugins when the Plugin Catalog has a newer compatible published release with complete ZIP artifact metadata.
- Add a CSRF-protected `Update from Catalog` action that reuses the catalog checksum and plugin ZIP validation path, preserves enabled or disabled lifecycle state, preserves plugin-owned tables, and leaves plugin migrations as an explicit setup action.

## 1.32.109

- Fix installed plugin registry loading for catalog-installed manifests that declare permission identifiers as `key` and migration paths as a single string.

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

## 1.32.81

- Prepare v1.32.81 as a production Backups download hotfix by routing backup archive download, detail, listing, and delete paths through one canonical backups-disk resolver, supporting legacy absolute paths inside the backups root while blocking traversal, symlink escapes, and absolute paths outside that root.
- Show controlled missing, unreadable, and unsafe archive feedback on `System -> Backups` instead of raw filesystem exceptions or blind download links, and prepare backup storage during package install without using broad permissions.

## 1.32.80

- Polish Contact Message detail pages with `Contact Message: {sender}` titles, compact visitor meta layout, and a secret-free `contact:mail-diagnose` command for inspecting Contact Form SMTP configuration and optional controlled send tests.

## 1.32.79

- Document the admin table action audit and standardize Contact Messages, Users, and Plugin Management row actions on `td.wb-table-actions > .wb-action-group` so icon and dropdown controls stay compact without making the generic action group utility globally nowrap.

## 1.32.78

- Harden Contact Form spam handling around the existing package-standard honeypot: filled `website` submissions now have explicit backend coverage for generic success-without-storage behavior, while normal submissions remain stored and notifications are still attempted separately.
- Add conservative Contact Message spam scoring for commercial outreach language, link density, repeated same-IP submissions, and free-mail sales pitches with generic subjects, storing `spam_score` and `spam_reasons` while using the durable `spam` editorial status instead of deleting messages.
- Clarify Contact Messages admin list/detail language so editorial spam status, stored spam signals, and SMTP notification failure details are shown as separate concepts.

## 1.32.77

- Standardize admin browser tab titles through the shared package admin layout as `{Page Title} - WebBlocks CMS`, while keeping Project Identity scoped to the admin topbar and avoiding duplicate product suffixes.
- Add package artifact and package-native update coverage so the changed admin layout and title helper ship in the release ZIP and replace the active Composer package runtime during System Update.

## 1.32.76

- Align the admin sidebar footer version label with the WebBlocks UI utility-class contract by moving centering onto the footer text element and removing the CMS-specific sidebar footer text-align override.

## 1.32.75

- Extend the WebBlocks UI Manager CMS-core bridge from the Releases and Settings entry URLs to the full release action tree, including create, store, show, edit, update, dry-run, and publish routes, so enabled compatible manual plugin admin actions stay under `/webadmin/plugins/webblocks-ui-manager/...` instead of falling back to `/webadmin`.
- Keep setup-required handling on release action URLs when WebBlocks UI Manager tables are missing, while Settings remains available to super admins and plugin permissions continue to gate view, manage, and publish actions.

## 1.32.74

- Bridge enabled WebBlocks UI Manager Releases and Settings entry routes through CMS core before plugin-owned route files execute, preventing stale manual plugin route/source context from redirecting operators back to `/webadmin`.
- Keep the named Releases and Settings plugin URLs resolving to `/webadmin/plugins/webblocks-ui-manager/...` while rendering setup-required guidance or settings on the same URL for super admins.

## 1.32.73

- Add a cached-safe enabled plugin admin route fallback so WebBlocks UI Manager Releases and Settings URLs stay on `/webadmin/plugins/webblocks-ui-manager/...` instead of falling through to the dashboard when dynamic manual plugin routes have not been hydrated for the request.
- Render the WebBlocks UI Manager setup-required state on the Releases URL with the `WebBlocks UI Releases` page title, plugin detail action, and super-admin `Run Plugin Migrations` action.

## 1.32.72

- Fix enabled manual plugin sidebar navigation so WebBlocks UI Manager menu links render the concrete `/webadmin/plugins/webblocks-ui-manager/releases` href and open the setup-required or releases page instead of returning operators to `/webadmin`.
- Keep plugin dashboard/system contribution cards out of the `System -> Plugins` management screen unless a contribution is explicitly designed for plugin management.
- Polish the manual plugin install card so `Upload Plugin ZIP` uses the standard primary admin button pattern in the card action row.

## 1.32.71

- Fix manual plugin route authorization so enabled compatible plugin routes register declared plugin-owned permissions before route authorization runs, with CMS `super_admin` explicitly allowed for active plugin abilities.
- Restore WebBlocks UI Manager route middleware to handle-prefixed plugin permissions: `webblocks-ui-manager.view` for release read pages, `webblocks-ui-manager.manage` for release metadata and settings, and `webblocks-ui-manager.publish` for publish actions.
- Add regression coverage proving a manually installed and enabled WebBlocks UI Manager no longer returns 403 for `super_admin` on Releases or Settings, while non-super-admin users remain denied and setup-required guidance still appears when release tables are missing.

## 1.32.70

- Fix manual plugin setup lifecycle handling so enabled plugins with missing plugin-owned tables report setup-required/migrations-pending health, expose a super-admin `Run Plugin Migrations` action scoped to the installed plugin package path, record setup results in plugin enabled state, and avoid raw database errors on plugin-owned admin routes.
- Harden WebBlocks UI Manager release screens with schema readiness checks and a controlled setup-required page when `webblocks_ui_manager_*` tables are missing, while normal release listing/detail/publish behavior resumes after plugin setup completes.
- Polish `System -> Plugins` action layouts with horizontal table action groups, a normal-width Danger Zone uninstall action, and a clearer Settings card `Open Settings` button.

## 1.32.69

- Polish `System -> Plugins` into a calmer manual plugin management screen with concise list columns, icon actions, clearer installed/disabled/enabled/incompatible/missing-files lifecycle language, inactive health messaging for disabled plugins, and readable plugin detail cards for overview, lifecycle, capabilities, settings, health, technical details, and danger-zone actions.
- Add safe uninstall support for manually uploaded plugins: super-admin only, manual-upload only, disabled-first, storage-owned package directory removal, enabled-state cleanup, protected/core plugin guardrails, path escape protection, and preserved plugin-owned database tables with explicit operator messaging.

## 1.32.68

- Prepare a post-1.32.67 product-boundary hardening patch: WebBlocks CMS core is now a generic plugin host with manual super-admin ZIP upload/install support under `System -> Plugins`, safe package validation, storage-owned plugin installation, disabled-by-default installed plugins, explicit enablement, and no marketplace, remote store, Composer installer, or automatic external plugin download.
- Extract WebBlocks UI Manager out of the bundled CMS package/runtime into `plugins/webblocks-ui-manager` as an internal/operator plugin source with a local ZIP artifact script. Normal CMS installs no longer register its routes, commands, menus, permissions, settings, health cards, views, migrations, or tables by default; existing tables from v1.32.67 are left untouched for optional manual cleanup.

## 1.32.67

- Release the completed CMS plugin system foundation: registry-backed definitions, config-backed enabled state, enabled-only routes and commands, settings/detail pages, health/status reporting, typed dashboard and system card extension slots, plugin-owned block declarations, safe public asset hooks, package conventions, compatibility metadata, incompatible-plugin messaging, active-only enforcement, collision guards, package boundary tests, and route guards proving `/webadmin` remains canonical while `/cms` stays static-only and CMS-owned `/admin` routes remain absent.
- Extend the first-party WebBlocks UI Manager plugin with a controlled local CDN publish workflow: plugin-owned publish run records, `webblocks-ui-manager:publish-release` dry-run/apply command modes, admin dry-run and confirmation-gated publish actions, checksum/manifest/version/path validation, expected dist-file checks, symlink/path traversal/project-root guards, idempotent writes into the configured local `public/cdn/webblocks-ui/{version}` target, publish health/status reporting, and focused command/admin/route/package-boundary coverage. External production deployment, marketplace behavior, remote plugin installers, arbitrary Composer installs, update-server publishing, and CMS core WebBlocks UI URL changes remain intentionally deferred.
- Add CMS plugin system Phase 5 packaging and ecosystem readiness: documented plugin package conventions, minimal-plugin developer guidance, safe local discovery rules, schema upgrade/release compatibility guidance, compatibility metadata enforcement for required CMS versions, incompatible plugin status and health reporting in `System -> Plugins`, command and database-prefix collision guards, incompatible-plugin inert behavior, and expanded plugin boundary/route regression tests. Marketplace/catalog UI, arbitrary remote installers, generic Composer package installation, external production CDN deployment automation, update-server publishing, and public plugin route discovery remain intentionally deferred.
- Add CMS plugin system Phase 4 with the first-party WebBlocks UI Manager pilot plugin: disabled-by-default plugin registration, plugin-owned release/artifact tables and models, namespaced `/webadmin/plugins/webblocks-ui-manager/...` release screens, handle-prefixed permissions, plugin menu/settings/health/dashboard/system-card contributions, safe local `webblocks-ui-manager:prepare-release` metadata command, checksum and manifest generation, package boundary tests, and route guard coverage. External production CDN deployment automation, marketplace behavior, generic third-party plugin install/update flows, and CMS core WebBlocks UI consumption URL changes remain intentionally deferred.
- Add CMS plugin system Phase 3 runtime foundations: typed admin extension objects for read-only dashboard widgets and system cards, enabled-only plugin contribution collection, plugin-owned block and block pack declarations, safe public asset contribution hooks for head and body-end assets, collision/ownership validation, admin rendering tests, block hook tests, asset collection tests, and route namespace guard coverage.
- Add CMS plugin system Phase 2 runtime foundations: enabled-only plugin admin route registration under `/webadmin/plugins/{plugin-handle}`, plugin route names under `webblocks.plugins.{plugin_handle}.*`, console command registration for enabled plugins, read-only plugin settings scaffolding, plugin health/status reporting, and `System -> Plugins` detail/status surfaces, without adding install/apply/run lifecycle actions, plugin migrations, or WebBlocks UI Manager runtime code.
- Add the minimal CMS plugin registry foundation with plugin manifest value objects, config-backed enabled state, plugin permission/menu collection, route boundary guards, and the core `System -> Plugins` listing.
- Document the planned CMS plugin host architecture, plugin boundary rules, WebBlocks UI Manager pilot scope, and phased plugin roadmap without changing runtime behavior.

## 1.32.66

- Revert WebBlocks UI CDN integration from minified assets to standard dist assets while minification hardening is deferred, keeping the canonical jsDelivr `v2.7.9` tag and `/webadmin` plus `/cms` path contracts unchanged.

## 1.32.65

- Update CMS-owned WebBlocks UI CDN consumption to `v2.7.9` using the canonical jsDelivr tag URL format for `webblocks-ui.min.css`, `webblocks-icons.min.css`, `webblocks-ui.min.js`, and the default icon manifest sync source.
- Let Contact Form success toast auto-dismiss use the WebBlocks UI `v2.7.9` static toast lifecycle, keeping the shared top-right `#wb-overlay-root` toast container and manual `data-wb-dismiss="toast"` close hook without CMS-owned toast workaround JavaScript.

## 1.32.64

- Prepare v1.32.64 as a browser-facing WebBlocks UI CDN hotfix by removing the `raw.githubusercontent.com` fallback introduced in v1.32.63, which Chrome can block for CSS and JavaScript through ORB or MIME handling.
- Keep WebBlocks UI pinned to `v2.7.8` and continue consuming the minified production dist artifacts through the canonical jsDelivr tag URL format: `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@v2.7.8/packages/webblocks/dist/`.

## 1.32.63

- Prepare v1.32.63 as a WebBlocks UI CDN consumption patch by pinning CMS-owned runtime assets and the default icon manifest sync source to WebBlocks UI `v2.7.8`.
- Switch production CDN asset URLs to the shipped minified WebBlocks UI dist artifacts: `webblocks-ui.min.css`, `webblocks-icons.min.css`, and `webblocks-ui.min.js`, while keeping CMS-owned `/cms` public assets and the canonical `/webadmin` admin prefix unchanged.

## 1.32.62

- Adopt the WebBlocks UI toast feedback standard for transient public Contact Form success messages, keeping successful redirects on the clean page URL while validation errors remain anchored and inline near the form.
- Remove the duplicate Contact Form success rendering inside the block so successful submissions show one non-blocking toast from the shared public `#wb-overlay-root`.

## 1.32.61

- Polish public Contact Form redirects so successful submissions return to the clean current page URL without a form fragment, while validation errors still return to the form-specific anchor and unsafe source URLs continue to fall back to the canonical page URL.

## 1.32.60

- Refine the Search Index Status card to use compact WebBlocks admin table patterns instead of loose settings-row sections.

## 1.32.59

- Move Visitor Reports from Maintenance to System after Settings, rename the Maintenance search sidebar item to Search Rebuild, and polish the Search Index status screen into one consolidated rebuild-focused card.

## 1.32.58

- Make Visitor Reports privacy-safe and more honest by storing referrer hosts, referrer type, device category, bot flag, and normalized UTM aggregate values without raw referrer URLs, raw query strings, full user-agent strings, or raw IP addresses.
- Improve Visitor Reports summaries with human and bot page-view breakdowns, All/Human/Bots traffic filtering, visible Top Referrer counts, device share percentages, and explicit `Not tracked` states when unique visitor or session metrics cannot be calculated because consent-based session identifiers were not collected.
- Add focused coverage for anonymous page views, referrer host normalization, internal/direct grouping, device aggregation, bot filtering, UTM aggregation, site-scoped report access, and Top Pages unique visitor not-tracked display.

## 1.32.57

- Restore the package-owned `/webadmin/login` WebBlocks UI auth shell with package-safe Blade views, route-aware forgot password and register links, pinned WebBlocks UI assets, `/cms/css/guest.css`, and `/cms/brand` logo assets while keeping `/admin` and `/cms` admin aliases absent.
- Document the final `/webadmin` admin and `/cms` static asset coexistence standard, including the Nginx `try_files` collision root cause and the requirement to keep `public/cms/index.php` absent.

## 1.32.56

- Publish a follow-up package so the active updater can run the retired `public/cms/index.php` cleanup added in v1.32.55 on already-updated live installs.

## 1.32.55

- Make System Update delete the retired install-root `public/cms/index.php` handoff when the package no longer ships it, completing the `/webadmin` admin-prefix migration for already-updated installs.

## 1.32.54

- Move the canonical CMS admin and package-owned login namespace from `/cms` to `/webadmin` so admin routes no longer collide with the `public/cms` static asset directory.
- Remove the `public/cms/index.php` front-controller handoff workaround from root and package public assets while keeping static `/cms/css`, `/cms/js`, and `/cms/brand` asset URLs unchanged.

## 1.32.53

- Prepare v1.32.53 as a follow-up `/cms/` handoff hotfix by resetting PHP front-controller server variables before loading Laravel from `public/cms/index.php`, preserving the canonical `/cms/` request path instead of letting PHP-FPM treat the CMS asset directory as the application base path.

## 1.32.52

- Prepare v1.32.52 as a live `/cms/` web-server compatibility hotfix by shipping a package `public/cms/index.php` front-controller handoff, preventing Nginx `try_files $uri $uri/ ...` installs from serving the CMS asset directory as a forbidden static directory before Laravel can resolve the canonical CMS dashboard route.

## 1.32.51

- Prepare v1.32.51 as a focused `/cms` dashboard root hotfix so super admins can open the canonical dashboard normally, while site-scoped CMS admins and editors are safely redirected from `/cms` or `/cms/` to an allowed admin landing route instead of seeing a dead-end 403.

## 1.32.50

- Prepare v1.32.50 as the `/cms` coexistence namespace migration patch, making `/cms` the canonical CMS admin dashboard entry while preserving host-owned `/login` behavior for co-installed Laravel apps.
- Restore the repository-wide `ddev composer format:test` baseline by applying Pint-safe formatting, preserving the project 2-space PHP indentation rule, and wiring the indentation guard across maintained source roots.
- Clean up routine test-suite naming and validation guidance around the canonical `/cms` admin namespace, while keeping retired bridge coverage manual and archival.
- Clean up the `/cms` namespace migration by moving the admin asset picker upload endpoint to `/cms/media` and ignoring generated root publish copies of package config and boundary-marker artifacts.
- Move the canonical CMS admin and package-owned login namespace from `/admin` to `/cms`, keeping host-owned `/login` behavior available for co-installed Laravel apps and removing CMS-owned `/admin` aliases from the route table.
- Retire legacy root-managed bridge coverage from routine package-native release validation, keeping the historical `1.31.53 -> 1.32.33 bridge -> 1.32.34+ package-rooted` path in a manual `legacy` test group only.
- Keep package-native release gates focused on current package-rooted artifact shape, System Updates, package extraction, package metadata, and fresh Composer consumer boundaries.
- Tighten composer test-suite organization so package, install, artifact, update, admin-smoke, release-fast, and legacy bridge scripts have clearer package-native responsibilities with less routine overlap.

## 1.32.49

- Prepare v1.32.49 as a focused Contact Form UX polish patch while preserving storage-first public success behavior and existing notification delivery behavior.
- Clean up the Contact Message admin detail Visitor message card so sender, subject, and message content read in one vertical flow, and add progressive enhancement for public Contact Form success alerts to auto-dismiss after a short delay while leaving validation errors persistent.

## 1.32.48

- Prepare v1.32.48 as a focused Contact Form notification readability patch while preserving storage-first public success behavior and existing notification delivery behavior.
- Improve Contact Form notification email and admin detail readability by separating visitor message content, submission source details, notification delivery state, and quieter technical metadata including the already-stored IP address.

## 1.32.47

- Prepare v1.32.47 as a focused Contact Form notification hardening patch by adding a site-scoped default recipient and resolving notifications from block override to site default to `CONTACT_RECIPIENT_EMAIL` to safe `MAIL_FROM_ADDRESS` fallback while preserving storage-first public success behavior.
- Document CMS coexistence, configurable admin prefix direction, host-owned login, and CMS-owned membership authorization decisions.

## 1.32.46

- Prepare v1.32.46 as a focused Contact Form submit hotfix by registering the public contact form rate limiter from the package service provider for fresh Composer consumers.
- Keep public Contact Form submissions and validation failures anchored to the originating page instead of leaving browsers on `/contact-messages`, while preserving stored admin Contact Messages.

## 1.32.45

- Prepare v1.32.45 as a fresh-consumer portability hotfix by fixing the public Contact Form block renderer so it no longer requires host Blade components such as `x-input-label`, `x-text-input`, `x-input-error`, or `x-primary-button`.
- Include canonical site-level public override assets at `public/site/{site_handle}/css/site.css` and `public/site/{site_handle}/js/site.js` in Site Export / Import packages when file inclusion is enabled, restoring them under the final imported site handle.

## 1.32.44

- Prepare v1.32.44 as a focused fresh-consumer public routing hotfix by removing the untouched Laravel welcome `/` route during `webblocks:install` when it is safe, with a timestamped backup first.
- Preserve custom or ambiguous `routes/web.php` files while warning installers when the welcome route cleanup is skipped, keeping CMS public routing from being shadowed on clean Composer installs.

## 1.32.43

- Prepare v1.32.43 as a focused import catalog compatibility hotfix by restoring product-known transitional block type rows for `card-grid` and `navigation-auto` through the core block type sync path.
- Keep import diagnostics precise for truly custom missing block types while allowing fresh and package-updated installs to repair these shipped compatibility rows before import validation.

## 1.32.42

- Prepare v1.32.42 as a focused fresh-consumer import readiness hotfix by repairing shipped core block type, slot type, and page layout slot catalog rows before site import validation when an import package references missing core rows.
- Improve site import catalog diagnostics so admins see exact missing block type slugs and slot type handles instead of the generic missing catalog failure.
- Reduce duplicate import failure rendering on the import detail screen while keeping one top-level error, one status detail, and a concise output log.

## 1.32.41

- Prepare v1.32.41 as a focused fresh-consumer media folder schema hotfix by restoring the historical nullable `media_folders.slug` column in the package fresh-install schema.
- Add an idempotent package update repair migration that adds `media_folders.slug` to affected existing installs and backfills missing folder slugs from folder names without rewriting existing slugs.
- Extend fresh schema, update migration, and site Export / Import coverage so packages containing media folders with slugs import cleanly on fresh consumer schemas.

## 1.32.40

- Prepare v1.32.40 as a focused fresh-consumer Export / Import hotfix by registering the package default `site-transfers` filesystem disk when the host app has not defined one, preserving custom host disk config, and creating `storage/app/site-transfers` during `webblocks:install`.
- Move new site export archives onto the shared `site-transfers` disk used by imports and migration defaults, while existing stored archive disk names remain respected for download and deletion.
- Add defensive runtime storage readiness checks and controlled admin errors so missing or unwritable transfer storage does not expose raw Laravel filesystem disk exceptions.

## 1.32.39

- Fix public Gallery fixed-aspect media rendering so screenshot-style images preserve the full image with centered contain fitting and intentional dark letterboxing, while keeping lightbox, captions, overlays, and existing aspect classes intact.
- Increase the CMS Gallery large gap token mapping so `wb-gallery--gap-lg` has clearer breathing room than the medium gap.

## 1.32.38

- Fix default public header slots that contain only a Navbar block by promoting the slot wrapper to the `nav.wb-navbar` root, allowing shipped WebBlocks UI sticky navbar behavior to work without site-specific sticky CSS workarounds.

## 1.32.37

- Pin CMS-owned WebBlocks UI runtime assets and the default icon manifest sync source to `v2.7.7`, keeping public and admin layouts on the released grid/card CSS behavior without CMS-owned card-grid overrides.

## 1.32.36

- Fix Link List admin saves so child Link List Item meta and description can be blank without validation failure or dropped rows, while still requiring item titles and URLs and omitting empty public description wrappers.

## 1.32.35

- Fix the package-native pages parent-key update migration so MySQL and MariaDB index metadata is read through stable lowercase aliases with defensive uppercase fallback, preventing live updates from failing on `Undefined property: stdClass::$column_name`.

## 1.32.34

- Fix fresh Composer package installs and package-native System Updates so CMS-owned admin brand assets ship from `packages/webblocks-cms/public/cms/brand` and publish or sync into install-root `public/cms/brand` alongside CMS CSS and JavaScript.

## 1.32.33

- Prepare v1.32.33 as the real root-managed compatibility bridge for pre-package-native updater clients such as `1.31.53`, with the old archive root shape those clients can validate and package-native updater code they need for later package-rooted releases.
- Add a bridge-only updater bootstrap so old `App\Support\System\Updates\*` wrappers can load package-native updater support classes even before the legacy install has regenerated Composer autoload metadata for `WebBlocks\Cms\`.
- Use v1.32.33 as the bridge publication target because v1.32.32 was already auto-published to the update service as a package-rooted artifact and must not be offered to `1.31.53` clients.
- Document the safe update path as `1.31.53 -> 1.32.33 bridge -> 1.32.34+ package-rooted`.
- Clarify that bridge-capable installs such as `1.32.30` must skip the root-managed bridge and receive a package-rooted `1.32.34+` release instead.

## 1.32.32

- Prepare v1.32.32 as a root-managed compatibility bridge for pre-package-native updater clients such as `1.31.53`, so old installs can receive an old-shape artifact before moving to package-rooted releases.
- Fix the package-transition update compatibility gap by raising package-rooted release metadata to require a bridge-capable updater (`1.32.18+`) and documenting an explicit root-managed bridge artifact path for older installs such as `1.31.53`.
- Add regression coverage proving the `1.31.53`-style validator rejects package-rooted artifacts with the live `composer.json and artisan were not found at the archive root` failure, while accepting only an explicit root-managed bridge archive, and keep package-rooted validation strict for modern updates.
- Add a root-managed bridge archive builder that excludes install-owned paths including `.env`, `storage/`, `project/`, `public/site/`, `public/storage`, and root `config/` overrides while requiring both legacy updater wrappers and package-native updater code in the bridge source.

## 1.32.31

- Remove the remaining unnecessary root `app/` transition shims for legacy asset models/support, the unused slot-type request, the root `WebBlocks` identity mirror, and the root public-search reindex trait now that package media, package identity, and package search runtime are authoritative.
- Remove leftover empty root `app/` transition directories after the broad compatibility-layer cleanup, delete the now-unused root asset request shims and block-translation concern wrapper, and strengthen `PackageWrapperCleanupTest` so package-counterpart wrapper directories stay absent.
- Aggressively reduce the maintenance repo root `app/` compatibility layer toward the real package-consumer boundary by deleting redundant package-counterpart controllers, requests, mail, models, commands, and support wrappers while keeping only the host-owned install middleware boundary.
- Add `PackageWrapperCleanupTest` as a static/runtime guard proving the root app tree stays minimal, package admin/public routes use package controllers, package commands are registered by `WebBlocksCmsServiceProvider`, and deleted root FQCNs are not required by the current package runtime.
- Remove the redundant root command wrappers `app/Console/Commands/{BlockTypeContractsAuditCommand,ImportDemoMedia,ResetPrimitiveBlocksCommand,SiteCloneCommand,SiteDeleteCommand,SyncCoreBlockTypesCommand}.php` now that the package service provider already registers the package command implementations directly.
- Remove the redundant root translation-model wrappers `app/Models/{BlockButtonTranslation,BlockContactFormTranslation,BlockGalleryItemTranslation,BlockImageTranslation}.php`, add bootstrap regression coverage that those root files stay absent, and document the remaining root-owned boundaries that still block broader app-wrapper cleanup.

## 1.32.30

- Prepare v1.32.30 as an emergency package asset hotfix by strengthening the release package boundary around `public/cms/js/admin/listing-bulk-actions.js` for the exact `vendor/fklavyenet/webblocks-cms` Composer package root.
- Add workflow-zip and Composer source checkout coverage so both the custom release package and GitHub/Packagist tag archive shapes must include the bulk listing JavaScript where clean consumers load or publish package assets.
- Bump the CMS runtime version constants to `1.32.30` without changing the asset publishing contract: `webblocks-cms-assets` continues to publish package `public/cms` into install-root `public/cms`.

## 1.32.29

- Prepare v1.32.29 as a focused bulk-listing asset/package hotfix by making `cms/js/admin/listing-bulk-actions.js` an explicitly tracked package and root runtime asset, including it in package public asset coverage and the release artifact boundary.
- Change the `webblocks-cms-assets` publish target to the active runtime compatibility path so `vendor:publish --tag=webblocks-cms-assets --force` publishes package CMS assets into `public/cms`, including `public/cms/js/admin/listing-bulk-actions.js`.
- Sync package `public/cms` assets into the install-root `public/cms` runtime path during System Update while still replacing clean package roots and nested transition vendor package roots, so existing package-native consumers receive the bulk listing JavaScript without inline Blade scripts or install-specific logic.

## 1.32.28

- Add an idempotent existing-install schema repair migration so package-native System Updates and source-maintained installs create the `pages_id_site_id_unique` parent key on `pages(id, site_id)` when older databases are missing it, keeping new backups portable for the `page_translations(page_id, site_id)` composite foreign key.
- Extend backup/restore regression coverage around guarded MySQL/MariaDB imports and the page translation parent-key contract so future releases cover both fresh schemas and already-migrated installs.

## 1.32.27

- Fix MySQL/MariaDB full database restores so backup SQL imports are wrapped with temporary `FOREIGN_KEY_CHECKS` and `UNIQUE_CHECKS` guards even when the dump omitted them, keeping out-of-order table creation portable across dev/test installs without weakening SQL validation.
- Harden backup/restore failure reporting by sanitizing restore errors before they are persisted or shown in admin output, and align the package fresh-install schema with the historical `pages(id, site_id)` parent key and `page_translations(page_id, site_id)` composite foreign key/index contract.

## 1.32.26

- Add selected bulk deletion to Backups, Contact Messages, Media, Pages, Site Exports, and Site Imports, using page-visible checkbox selection only with no select-all-across-filtered-results behavior.
- Replace browser confirmation dialogs on those destructive listing flows with CMS/WebBlocks confirmation modals, covering both selected bulk delete and row delete actions where the listing exposes deletion.
- Preserve server-side ID validation, per-record authorization and site-scope checks, domain-specific safety rules such as Media usage guards and transfer archive cleanup, partial-success feedback, and focused rendering/endpoint coverage for the expanded bulk-delete listing pattern.
- Move the Pages listing `View` column immediately after the `Page` column while adding the new leading bulk-selection checkbox column.

- Prepare v1.32.25 as a follow-up updater target hotfix so package-native System Updates replace both the root `packages/webblocks-cms` transition copy and the active Composer autoload package runtime under `vendor/fklavyenet/webblocks-cms/...` when consumers are still installed from the transition repository shape.
- Add regression coverage for the exact fresh-consumer failure where Composer continued loading stale `vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src` controllers with root `admin/site-transfers.*` view calls after a successful System Update.

- Prepare v1.32.24 as a follow-up fresh-consumer package-boundary hotfix by adding release-artifact and Composer source-checkout assertions for the active Site Export / Import controllers, ensuring shipped `src/...` and `packages/webblocks-cms/src/...` controller files both render `webblocks-cms::admin.site-transfers.*` package views instead of root `admin.site-transfers.*` views.
- Strengthen the release gate around the exact fresh-consumer failure so future tags cannot ship package controllers containing `view('admin/site-transfers...')`, `view('admin.site-transfers...')`, or matching `response()->view(...)` root view calls.

- Prepare v1.32.23 as a fresh-consumer package-boundary hotfix by making the Site Export / Import controllers render package-owned `webblocks-cms::admin.site-transfers.*` views instead of root `admin.site-transfers.*` Blade names, keeping fresh Laravel consumers able to open the Export / Import admin screens without root view files.
- Extend fresh-consumer smoke coverage and static package-boundary audits for the site transfer and promotion admin surfaces so package routes, controllers, and Blade files stay namespaced through `webblocks-cms::` and do not regress to root admin layouts, includes, or components.

- Prepare v1.32.22 as a follow-up updater hotfix by tightening `UpdateMigrationRunner` source-maintained detection: package consumers are no longer classified as root-migration-authoritative merely because their root package name and `packages/webblocks-cms` directory are present.
- Add migration strategy diagnostics and regression coverage proving package-native consumers with an existing `users` table, a pending Laravel starter users migration, and a `packages/webblocks-cms` subtree still skip host application migrations during System Update, while the real maintenance checkout keeps source-maintained root migration authority through its explicit root Composer autoload mapping.

- Prepare v1.32.21 as a package-native updater hotfix by replacing the generic post-update `artisan migrate --force` step with an explicit migration runner: source-maintained checkouts keep root migration authority, while fresh package consumers skip host Laravel application migrations and only run dedicated package update migrations from `packages/webblocks-cms/database/migrations/updates` when present.
- Add regression coverage for the reported 1.32.19 -> 1.32.20 consumer failure where a pending host `0001_01_01_000000_create_users_table.php` migration tried to recreate an existing `users` table during System Update, and verify the update flow still continues through catalog seeding, `block-types:sync-core`, cache clearing, and installed-version persistence.

- Prepare v1.32.20 by fixing fresh-install schema drift for `site_variables`: the package fresh schema now creates the historical `is_enabled` column plus the runtime-supporting `site_id/is_enabled` and `site_id/sort_order/id` indexes so fresh consumer installs match the active runtime query contract.

- Prepare v1.32.19 by making the in-app updater package-native: update ZIP validation now requires the package-root `fklavyenet/webblocks-cms` artifact shape with package-relative PSR-4 mappings, no longer accepts the retired root-managed `artisan` archive contract, and applies validated package contents only into `packages/webblocks-cms/`.
- Replace root-wide updater file copying with package-subtree replacement so stale package files are removed safely during update apply while install-owned root paths such as `.env`, `storage/`, `project/`, `public/site/`, root config overrides, and the root Laravel shell remain untouched during the current transition.

- Prepare v1.32.18 as a consumer package hotfix by building release artifacts from the package subtree root instead of the maintenance-repository root, so installed Composer metadata now autoloads `WebBlocks\\Cms\\...` from package-relative `src/` and `database/seeders/` paths.
- Add installed-package release artifact coverage that validates the shipped `composer.json` manifest and PSR-4 class path expectations for updater support classes such as `WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException`, preventing maintenance-repo-only autoload paths like `packages/webblocks-cms/src/` from leaking into consumer releases.

- Prepare v1.32.17 as a fresh-consumer updater hotfix by making backup operations bootstrap the package-owned `backups` disk root when a new consumer install has not added the maintenance-repo filesystem disk config yet, so the mandatory pre-update backup can be created before in-app updates apply.
- Preserve sanitized backup failure detail across the operational update boundary by recording the real pre-update backup failure reason in `system_update_runs` and `system_backups` without exposing secrets, credentials, or sensitive absolute paths, and add focused fresh-consumer and updater regression coverage for backup readiness, idempotent storage creation, and failure-detail persistence.

- Prepare v1.32.16 as a hotfix for the package updater artifact boundary by guarding the `WebBlocks\Cms\Support\System\Updates\UpdateException` path and neighboring updater support classes that consumer installs autoload from `vendor/fklavyenet/webblocks-cms/src/...` during in-app updates.
- Add focused package metadata, bootstrap, and release-archive integrity coverage so package-owned updater support classes resolve to real files and the release artifact includes the updater support files needed by consumer Composer autoloading.

- Prepare v1.32.15 as a proactive package admin view and component boundary sweep so package-owned runtime no longer silently relies on root `admin.*`, `layouts.admin`, or root admin component names that exist only in this maintenance repository and fail in fresh consumer installs.
- Add a static package boundary audit across package `src/`, `resources/views/`, and `routes/` with a narrow per-file allowlist for the remaining intentional block admin compatibility fallbacks, and extend fresh-consumer smoke coverage to representative package-owned admin routes plus both fallback block admin form render paths.

- Prepare v1.32.14 as a fresh-consumer admin hotfix for the package-owned Blocks runtime by making admin block form resolution prefer `webblocks-cms::admin.blocks.types.*` and the package fallback form, so unknown or product-owned block types no longer fail on missing root `admin.blocks.types.fallback` views in consumer installs.
- Extend package boundary coverage for block admin form fallback handling with static assertions around the package block resolver, bootstrap checks for the package fallback Blade files, and authenticated post-install smoke coverage that renders the generic fallback block form through the package namespace.

- Prepare v1.32.13 as a follow-up fresh-consumer admin hotfix by locking the System Updates screen to the package view namespace, confirming the package-owned `updates.blade.php` exists, and extending boundary coverage so `/admin/system/updates` stays renderable in a fresh Laravel consumer without any root `resources/views/admin/system/updates*.blade.php` files.
- Expand static package-boundary assertions for the System admin slice so package controllers and package Blade views cannot regress to root `admin.*` render paths, layouts, includes, or root-only auth components, with explicit checks for the updates, backups, search, settings, icons, block-types, page-layouts, and slot-types screens.
- Add focused post-install consumer smoke coverage for `/admin/system/updates` and bootstrap assertions for the package-owned System views so the exact external namespace failure is caught before release.

- Prepare v1.32.12 as a follow-up fresh-consumer admin hotfix by removing the remaining root-auth component dependency from the package-owned Users admin form, keeping `/admin/users` and `/admin/users/create` renderable in a fresh Laravel consumer without root Blade component wrappers.
- Strengthen package admin boundary coverage with broader authenticated consumer smoke tests across the active package-routed admin screens and tighter static assertions that package admin runtime code does not fall back to root `admin.*` views, includes, layouts, or the root `x-auth-password-field` component.

- Prepare v1.32.11 as a fresh-Laravel consumer hotfix by removing package runtime autoload exposure for maintenance-repo `App\...`, `Database\...`, and `Project\...` classes, keeping only package PSR-4 mappings in consumer installs so Composer ambiguous class warnings no longer come from the CMS package.
- Extend `webblocks:install` to create only the required Laravel database support tables for the configured session and cache drivers, using idempotent `Schema::hasTable()` checks for `sessions`, `cache`, and `cache_locks` without running host Laravel migrations or touching unrelated migration state.
- Fix package-owned admin Blade and controller boundaries so package views and package renderers use `webblocks-cms::...` component and view namespaces instead of root `admin.*` wrappers, covering the reported `/admin/sites` and `/admin/navigation` failures plus related package-owned partial chains.
- Add focused regression coverage for consumer Composer metadata, installer-created support tables, post-install `/admin/sites` and `/admin/navigation` readiness, and static package namespace assertions for package-owned views and PHP renderers.

- Fix the package fresh-install migration for MySQL and MariaDB consumer installs by replacing the auto-generated `shared_slot_revisions.restored_from_shared_slot_revision_id` foreign key name with the explicit short constraint `ss_revisions_restored_from_fk`, avoiding identifier-length failures during `webblocks:install` on fresh Laravel consumers.

- Fix the root `composer.json` package metadata so tagged releases install correctly as `fklavyenet/webblocks-cms` through Composer by exposing `WebBlocks\\Cms\\` package autoloading and Laravel provider discovery at the repository root, while preserving the maintenance-repo workflow through explicit local provider loading.

- Add the first package-consumer install path for `fklavyenet/webblocks-cms` with a package-owned `webblocks:install` command that safely patches `App\Models\User`, runs the focused fresh-install CMS schema, installs `public/cms` assets, seeds baseline catalogs and settings, records the installed version, and creates the first active `super_admin` without requiring Breeze, Jetstream, Laravel UI, Fortify, or manual host route edits.
- Keep maintenance-repo package migrations inert by default while adding focused consumer install coverage for command registration, fresh-install migration discovery, safe backup-first `User.php` patching, idempotent reruns, first-admin creation, and post-install login, admin, and public home readiness.

## 1.32.7

- Fix the updater duration deprecation warning by normalizing measured millisecond durations before they are persisted onto the update run record or passed into the int-typed `UpdateResult` DTO, keeping updater behavior and output unchanged while removing the implicit float-to-int conversion path.
- Add focused regression coverage for updater duration normalization so fractional measured durations are stored and reported consistently without warning-producing implicit casts.

## 1.32.6

- Fix the post-update reporting compatibility bug where a successful update could still be marked failed at the final `UpdateResult` construction step when the pre-update backup arrived as the root `App\Models\SystemBackup` wrapper instead of the package model.
- Keep the updater or reporting boundary intentionally narrow by accepting both root and package `SystemBackup` instances in `UpdateResult`, and add focused regression coverage proving a completed update result can safely carry the root pre-update backup wrapper.

## 1.32.5

- Complete this package-authoritative runtime cleanup pass by moving the remaining package-owned helper implementations such as `BlockTranslationWriter` and `CoreBlockTypeCatalogSyncer` into `packages/webblocks-cms/src/Support/Blocks/`, switching package internals like demo media kind resolution to package-owned support classes, and leaving matching root `App\Support\...` entrypoints only as compatibility wrappers.
- Remove the dormant old `admin/layouts`, `admin/layout-types`, and `admin/page-types` CRUD surfaces from the active package or root runtime without restoring those legacy screens, while keeping the accepted root-owned boundaries unchanged for `App\Models\User`, `App\Support\WebBlocks`, install/auth/profile entrypoints, install-update guards, root migration authority, root `public/cms/...` runtime asset paths, and required legacy aliases.
- Update package bootstrap, admin, public, and shared-slot coverage to assert package-authoritative `WebBlocks\Cms\...` runtime classes instead of stale root-wrapper expectations, including package-owned models, support services, icon sync wiring, and contact notification mailables where behavior is intentionally package-owned now.
- Fix the real package-transition regressions that remained after the authority shift by allowing `App\Models\User::hasSiteAccess()` to accept package `Site` instances, preserving required package-root compatibility bindings for backup or restore or promotion flows, and keeping contact, export/import, and promotion behavior aligned with the active runtime boundary.
- Fix SQLite restore, transaction, and installed-version test lifecycle blockers in the system backup or restore layer, add focused regression coverage for the SQLite restore path, and keep the full `ddev artisan test` suite green for the accepted dirty `main` release scope.
- Refresh the release notes and transition docs so they describe the final state honestly: safely movable CMS-owned source is package-owned, root wrappers remain only where backward-compatible entrypoints are still intentional, and package transition consolidation is complete for this pass without broadening migration, updater, installer, auth/User, or runtime asset ownership.

## 1.32.4

- Finalize the package-transition consolidation metadata and diagnostics so `WebBlocksCmsServiceProvider` and `webblocks:package-status` now report the real package-owned route, view, model, seeder, and movable asset-source authority alongside the intentional root compatibility wrappers.
- Clarify in focused tests and docs that the safely movable CMS-owned source is now package-owned, while active runtime asset URLs still use root `public/cms/...` compatibility paths and the remaining boundaries stay install/auth, the app-owned `User` model, and root migration or update authority.
- Fix the late full-suite package-transition regressions by restoring the real package-owned site transfer and site promotion views, removing recursive package/root Blade wrapper loops, and preserving the promotion screen preselection and cancel-action behavior expected by the existing admin contract.
- Restore release-gate compatibility for contact notifications and legacy transitional block catalogs by routing contact mail through the root wrapper mailable entrypoint and keeping legacy draft compatibility rows for `text`, `tabs`, `slider`, `menu`, `faq-list`, `showcase-list`, and `contact-info`.

## 1.32.3

- Move the remaining safe operational admin route batch into `packages/webblocks-cms` for Slot Types and System Settings, adding package-owned controllers, the System Settings request, and package-owned admin views while preserving root `App\...` and root Blade compatibility wrappers.
- Update package admin route authority, package-status reporting, and focused bootstrap or route coverage so the active `admin.slot-types.*` and `admin.system.settings.*` surfaces now execute through the package without changing install, update, backup/restore, export/import, promotion, auth/User, migration, config, or runtime asset URL ownership.
- Copy admin CSS and JS source authority into `packages/webblocks-cms/public/cms/`, including `css/admin.css`, `js/admin/**`, and `js/admin-sortable-list.js`, while keeping root `public/cms/...` runtime files and admin layout asset URLs unchanged as compatibility paths.
- Extend package asset readiness, bootstrap coverage, and transition docs so package-owned admin asset source files are tracked through the existing `webblocks-cms-assets` and package-status boundary without moving brand assets or replacing root runtime asset authority yet.
- Move `resources/views/layouts/admin.blade.php` into package view authority as `webblocks-cms::layouts.admin`, keep the root layout as a compatibility wrapper, and update package-owned admin views to extend the package layout namespace while leaving root `public/cms` admin asset and brand runtime authority unchanged.
- Extend focused bootstrap, dashboard, and package-status coverage so the package-owned admin layout and root wrapper are verified without moving admin CSS/JS, brand assets, auth/profile/install/app/guest layouts, migrations, updater, backup/restore, export/import, promotion, or release flow.
- Move the remaining shared admin partial edge cases into `packages/webblocks-cms/resources/views/admin/partials`, including flash messages and page actions, while preserving root Blade compatibility wrappers and keeping admin shell/assets root-owned.
- Extend shared admin partial rendering coverage and package-status reporting so package-owned admin views use the package namespace for flash and page-actions partials while root include paths remain available.
- Move the selected shared admin partial/component layer into `packages/webblocks-cms/resources/views`, including page headers, listing filters, pagination, audit actor output, and form actions, while preserving root Blade compatibility wrappers.
- Update package-owned admin views and package-status reporting so moved shared admin partials/components resolve through the `webblocks-cms::` namespace while the admin shell, admin CSS/JS, brand assets, auth/profile/install views, migrations, updater, backup/restore, export/import, promotion, and release flow remain unchanged.
- Add the admin shell and asset ownership audit, documenting that selected shared admin partials are the safest next package batch while the admin shell and active admin CSS/JS stay blocked by explicit asset publishing and brand override strategy.

## 1.32.2

- Move the focused operational admin runtime slice into `packages/webblocks-cms`, including Dashboard, Contact Messages admin review, Visitor Reports, and System Search controllers, directly supporting query/status helpers, and package-owned admin views while preserving root compatibility wrappers.
- Extend focused operational admin and package-status coverage so active routes use package controllers, package views resolve through `webblocks-cms::`, root wrappers remain present, and excluded update, backup/restore, export/import, promotion, auth/User, migration, config, and public asset boundaries stay unchanged.
- Move the Site and Locale admin runtime slice into `packages/webblocks-cms`, including Site, Site Domain, Site Variable, and Locale controllers, form requests, directly supporting models and support services, and package-owned admin views while preserving root compatibility wrappers.
- Extend focused Site/Locale admin and package-status coverage so active routes use package controllers, package views resolve through `webblocks-cms::`, root wrappers remain present, and excluded install/update, migration, export/import, promotion, auth/User, and public asset boundaries stay unchanged.
- Add package-owned `webblocks-cms.php` config defaults for diagnostics, admin, public, and migration boundary switches, keeping diagnostics, public status routes, admin status routes, and package migrations disabled by default while enabling package admin route loading for active package-owned admin routes.
- Extend focused provider and console coverage so `webblocks:package-status` reports the new package config defaults, active package admin route loading, disabled status slices, and the still-disabled migration boundary.
- Harden the package-owned icon catalog runtime batch by having `webblocks:package-status` and focused bootstrap coverage verify the package-owned active admin route and icon-sync command while root wrappers remain available only for backward-compatible imports.
- Move the `icons:sync-webblocks-ui` command implementation into package `src/Console/` while keeping the root `App\Console\Commands\SyncWebBlocksUiIconsCommand` class as a compatibility wrapper.
- Escalate the icon catalog slice from compatibility-wrapper execution to package authority by registering `icons:sync-webblocks-ui` from the package service provider and pointing the active icon catalog admin route at the package controller directly, while leaving root wrappers in place only for backward-compatible imports.
- Start the Resource Authority phase by moving the active icon catalog admin index and edit-modal views into the package view namespace, updating the package controller to render `webblocks-cms::admin.system.icons.index`, and leaving root Blade files as compatibility wrappers.
- Move the active icon catalog admin route definitions from root `routes/web.php` into package `routes/admin.php`, making the package route file authoritative for that admin surface while keeping the package admin status route separately disabled by default.
- Complete the first Install / Update / Starter boundary pass by adding a package-owned public asset marker and starter stubs, making `webblocks-cms-assets` and `webblocks-cms-stubs` publish real package resources while package migrations intentionally remain disabled and root-compatible.
- Continue the runtime-authority phase by making package `routes/admin.php` and `routes/public.php` the active CMS admin and public route trees while reducing root `routes/web.php` to install, auth, profile, and compatibility loading of package-owned CMS route files.
- Move the public page, search, contact-message, and privacy-consent entry slice into package-owned controllers, form request, and `webblocks-cms::public.*` entry views while keeping root `App\Http\...` classes as compatibility wrappers.
- Extend focused public, search, bootstrap, runtime-slice, and package-status coverage so the new package-owned route authority and public entry slice are verified without changing current root migration, asset, or System Update authority.
- Refine the package architecture documentation and README so they describe the current package-owned runtime route authority honestly, including the remaining root-owned blockers such as models, broader support code, admin view trees, and assets.
- Document the post-Step-1 blocker map and next extraction recommendation: keep migrations, assets, and System Update root-authoritative for now, and prepare the next large package batch around the remaining public rendering layer plus its page or block or shared-slot model and support dependencies rather than another small isolated slice.
- Continue the public rendering authority migration by moving the active public layout, page shell, slot shell, and public search views into package `resources/views/` under the `webblocks-cms::` namespace while reducing the root public entry Blade files to compatibility wrappers.
- Move the first public-rendering support batch into package `src/Support/`, including page-route resolution, public page presentation, shared-slot presentation, slot-wrapper resolution, trusted HTML overlay extraction, public overlay/body-end registries, public search query orchestration, site resolution, visitor event logging, and site asset resolution, while keeping root `App\Support\...` classes as compatibility wrappers.
- Keep the public runtime model layer root-owned for now because Pages, Blocks, Sites, Locales, Search index records, Visitor events, and related relationships still cross admin and public runtime boundaries too broadly for a safe extraction in this batch.
- Make the package public asset boundary more concrete by adding the public layout CSS and JS used by the moved package-owned layout into `packages/webblocks-cms/public/cms/`, while keeping active runtime asset URLs rooted at `public/cms/...` for compatibility in the current phase.
- Extend `webblocks:package-status` and focused bootstrap or runtime coverage so they report the new package-owned public view and support authority honestly, including the remaining root compatibility layer for models, broader block renderers, migrations, and runtime asset paths.
- Move the full active public block renderer partial tree into `packages/webblocks-cms/resources/views/pages/partials/blocks/`, making the package namespace authoritative for shipped core block renderers while leaving root `resources/views/pages/partials/blocks/*` files as thin compatibility wrappers.
- Keep install-specific or custom root block renderers available through the existing `Block::publicRenderView()` root fallback, and add focused coverage proving package block partials resolve first while safe missing-renderer fallback behavior remains unchanged.
- Extend package-status and bootstrap coverage so public block renderer authority and public asset readiness are reported explicitly: package block partials and package public assets are present, root compatibility wrappers and root `public/cms/...` assets still exist, active runtime asset URLs still point at the root compatibility path, and root models, root migrations, and System Update remain unchanged blockers.
- Start the Public Model Compatibility Foundation batch by moving `Locale`, `Site`, `SiteDomain`, `ContactMessage`, `PublicSearchIndex`, `VisitorEvent`, and `SystemSetting` into package `src/Models/` while keeping root `App\Models\...` classes as compatibility wrappers.
- Update package-owned public runtime imports and package-status reporting so package public controllers, route patterns, public site resolution, search querying, visitor logging, and related support now prefer `WebBlocks\Cms\Models\...` where those model slices are package-owned, while `Page`, `PageTranslation`, `PageSlot`, `Block`, and `User` remain root-owned for now.
- Document the partial model authority boundary honestly: root migrations remain authoritative, System Update and install flow remain unchanged, runtime asset URLs still use root `public/cms/...` compatibility paths, and consumer or starter validation remains blocked by the remaining root-owned page or block model surface.
- Move the `Page`, `PageTranslation`, `PageSlot`, and `Block` model core into package `src/Models/` while keeping root `App\Models\...` wrappers as compatibility entrypoints.
- Update package-owned runtime typing, package status output, and focused bootstrap coverage so the package model foundation now includes the full page and block core while root migrations, root asset paths, System Update, and the app-owned `User` model remain unchanged.
- Move the active admin runtime for Pages, Blocks, Media, Shared Slots, Navigation, Block Types, and Page Layouts into `packages/webblocks-cms`, including the package-authoritative controllers, form requests, support services, and admin view trees that now back those routes.
- Keep root `App\...` classes and root `resources/views/admin/...` files as compatibility wrappers for the moved admin slices so existing imports, route references, and downstream overrides do not break during the transition.
- Extend package bootstrap and package-status coverage for the larger admin runtime boundary, and keep the one root rich-text editor partial concrete because compatibility checks still read that root Blade file directly instead of resolving it through the package view namespace.
- Refine the package architecture documentation so it now describes the broader admin runtime authority honestly: the editorial admin surfaces above are package-owned, while Sites, Users, System, install flow, migrations, root runtime assets, and System Update remain root-authoritative blockers.
- Fix the release-gate compatibility boundary exposed by the full suite after the package transition: contact form notification flow now accepts the package-authoritative `ContactMessage` model through the existing root notifier and mailable entrypoints, and the block-type contracts audit continues to report stable root compatibility view paths even though runtime authority now lives in package views.

## 1.32.1

- Continue the package seeder boundary by moving `CoreCatalogSeeder` into `packages/webblocks-cms/database/seeders/` while keeping root `Database\Seeders\CoreCatalogSeeder` as the compatibility entrypoint for existing installs, tests, and current root seeding flows.
- Extend the read-only `webblocks:package-status` command plus focused provider coverage so they report package-owned `CoreCatalogSeeder` presence alongside the existing package catalog seeders and confirm the root compatibility wrapper remains in place.
- Continue the first package-owned runtime migration with the isolated icon catalog management batch by moving `IconCatalogController`, `IconCatalogItemUpdateRequest`, `IconCatalog`, and `WebBlocksIconManifestSyncer` into `packages/webblocks-cms/src/` while keeping root `App\...` classes as compatibility wrappers for existing routes, commands, requests, and views.
- Extend the read-only `webblocks:package-status` command plus focused provider coverage so they report the package-owned icon runtime batch and confirm the root compatibility wrappers remain in place.
- Refine package resource-ownership documentation around guarded route and view slices, legacy root migrations, reserved package public assets and stubs, and the compatibility rule that current root routes, views, migrations, assets, installer flow, and System Update behavior remain authoritative outside intentionally moved package boundaries.
- Extend the read-only `webblocks:package-status` command so it also reports route and view Composer readiness, root compatibility state for routes, views, migrations, and assets, package Composer metadata and provider discovery readiness, target Composer install and update flow notes, and starter-foundation readiness without mutating runtime or install state.

## 1.32.0

- Start Runtime Migration Phases 1-2 by moving the guarded package diagnostics runtime slice to a package-owned controller under `packages/webblocks-cms/src/Http/Controllers/Diagnostics/`, wiring the package diagnostic route file to that handler, and rendering the existing package diagnostic view while keeping diagnostics route loading off by default behind the explicit package guard.
- Add the first focused package admin runtime slice as one isolated super-admin-only status page under `packages/webblocks-cms/routes/admin.php` with a package-owned controller and Blade view on a reserved `/admin/_webblocks-cms/...` path, keeping normal CMS admin areas root-owned and unaffected.
- Add the first focused package public runtime slice as one isolated static status page under `packages/webblocks-cms/routes/public.php` with a package-owned controller and Blade view on a reserved `/_webblocks-cms/...` path, keeping root public page rendering, search, multisite routing, and block rendering authoritative.
- Extend the read-only `webblocks:package-status` command so it reports diagnostics runtime slice status, package admin slice status, package public slice status, the new route guards, and the continued no-mutation transition rule.
- Add focused bootstrap and runtime coverage proving the new package diagnostics, admin, and public slices stay disabled by default, can be enabled explicitly through their guards, render through package-owned handlers or views, and do not override root admin or public route or view behavior.
- Document `Runtime Migration Phases 1-2` in the package architecture guide, including what moved, what remains root-owned, and why the first real package-owned runtime slices stay isolated and guard-disabled by default.
- Start the next low-risk package transition slice by moving package-owned catalog seeders for icons, page types, layout types, and slot types into `packages/webblocks-cms/database/seeders/`, while keeping root `Database\Seeders\...` classes as compatibility wrappers for existing installs, tests, and update entrypoints.
- Resume low-risk runtime support migration by moving `AdminPagination`, `BlockTypeIndexState`, `MediaIndexState`, and `PageIndexState` into `packages/webblocks-cms/src/Support/` while keeping root `App\Support\...` compatibility wrappers so current controllers, requests, and tests remain stable.
- Extend the read-only `webblocks:package-status` command and package bootstrap coverage so they report package seeder ownership, root compatibility wrappers, low-risk runtime support moves, and Composer path or seeder autoload readiness without changing current migration or update authority.
