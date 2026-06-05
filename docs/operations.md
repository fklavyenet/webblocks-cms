# Operations

## Overview

WebBlocks CMS includes install-level operational tools for updates, backups, and site transfer packages.

`Settings` also lives under the admin `System` navigation because it controls install-level project identity, locale, timezone, privacy, version, and environment settings.

`Admin -> System -> Block Types` also serves as an install-level catalog inspection screen. Its `Support` filter is for block capability and content-source metadata, while its `Usage` filter is for actual live usage counts from the `blocks` table so admins can review used versus unused block type rows.

`Admin -> System -> Visitor Reports` provides privacy-safe traffic reporting next to install-level system settings.

`Maintenance` remains the operational tools group for:

- Search Rebuild
- Backups
- Export / Import
- Update

## Visitor Reports

Visitor Reports is a privacy-safe operational report for public page activity. Anonymous page views can be counted without consent-based identifiers. The report also stores aggregate-only referrer, campaign, device, and bot information:

- referrers are normalized to host/domain plus `direct`, `internal`, or `external` type; full referrer URLs are not stored or shown
- empty referrers are grouped as `Direct / Unknown`
- UTM source, medium, and campaign are stored as normalized values only; the full query string is not stored
- user-agent strings are reduced at request time to device category (`desktop`, `mobile`, `tablet`, `bot`, or `unknown`) plus coarse browser/OS families where available; full user-agent strings are not stored
- known crawler and bot user-agent patterns are counted as bot page views and remain visible in the report instead of being silently removed

Unique visitors, sessions, entry pages, and average pages per session require consent-based session tracking. When a date/site/locale/traffic filter contains page views but no usable session or visitor identifier, the UI shows `Not tracked` instead of `0`. Older rows that cannot be backfilled safely remain `Unknown`, `Direct / Unknown`, or `Not tracked` depending on which aggregate fields exist.

Site-scoped authorization still applies: super admins can see all sites, while site admins and editors can see only assigned site data. The site filter is limited to the user's accessible sites.

## System Updates

System Updates checks the running CMS code version against the configured update service.

The update screen can report states such as:

- update available
- up to date
- local/source version newer than the latest published release
- incompatible update available
- no releases found
- update server unavailable
- invalid or unsupported response

The in-app update flow downloads the release package, applies protected-path rules, runs required update migrations, clears caches, verifies the applied WebBlocks CMS code version against the target release, records the update run, and persists the installed version.

Update availability is based on the latest published release version being newer than the running CMS code version. Historical failed update runs and stale stored installed-version values remain inspectable through the last-run modal, support report, retained-run CLI, or `Update Readiness`, but they do not make a current install actionable by themselves.

The screen defaults to two cards: `Install Update` first and `Update Details` second. `Install Update` is the only primary action area. When a compatible update is available, it shows the current-to-latest version path, compatibility state, the install CTA, and a collapsed `Package Safety Details` accordion. When no update is available, it shows a quiet up-to-date or unavailable message and omits package safety details.

`Update Details` contains WebBlocks UI accordion rows for `Release Notes`, `Update Readiness`, and `Last Update Run`. Update Readiness describes the current install and update service checks; it is not release-note content for the target version. Last Update Run shows only the most recent relevant run summary on the main screen, with details available in a modal. Super admins can download a support report from the same card for shared-hosting support workflows; the report avoids tokens, secrets, absolute local paths, and raw stack traces while including current/latest version, readiness, last-run details, and retained run summaries.

Update History is no longer rendered as a main-screen table and row deletion is not part of the admin UI. Update run records are pruned automatically, retaining the latest five runs by default. The latest failed run is preserved until a newer successful run exists. Operators with terminal access can inspect retained records with `php artisan webblocks:updates:runs`, `php artisan webblocks:updates:runs --last`, or `php artisan webblocks:updates:runs --failed`, and can run controlled pruning with `php artisan webblocks:updates:prune-runs --keep=5`.

The update client supports structured metadata from the update service, including title, summary, highlights, fixes, compatibility notes, migration notes, asset notes, operator notes, and technical notes. These values are rendered as escaped plain text in operator-oriented groups inside the Release Notes accordion. Older releases that only provide `release_notes` still show those notes cleanly, and releases without notes show `No release notes were provided for this release.` Readiness checks, stored installed version, and low-level update server values remain in Update Readiness.

Core catalog synchronization is no longer part of the normal System Update apply chain. Release packages apply code, assets, required update migrations, cache clears, post-apply version verification, run history, and installed-version persistence; broad catalog repair is an explicit maintenance workflow.

For manual maintenance or recovery on an existing install, admins and developers can also run:

```bash
php artisan webblocks:catalog-repair --dry-run --all
php artisan webblocks:catalog-repair --all
php artisan block-types:sync-core
```

`webblocks:catalog-repair` supports `--block-types`, `--slot-types`, `--page-layouts`, `--icons`, and `--all`. It reports created, updated, unchanged, and skipped rows, preserves install-specific custom catalog rows, and can be run repeatedly. `block-types:sync-core` remains as a lower-level compatibility command for the block type catalog.

Published release packages are core product packages. They ship reusable CMS source, assets, migrations, views, routes, config, docs, and tests, but do not ship install-specific project-layer content from `project/`.

CMS release/update publishing is a native/local maintainer workflow:

```bash
composer release:prepare
composer release:publish-update -- --dry-run
composer release:publish-update
```

`release:prepare` builds the package-rooted ZIP, checksum, and publisher payload locally. `release:publish-update` posts the package and metadata directly to `WEBBLOCKS_PUBLISHER_URL`, which defaults to `https://updates.webblocksui.com/api/updates/publish`, then verifies latest update-server metadata for `webblocks-cms` on the selected channel. Publishing reads only `WEBBLOCKS_PUBLISHER_URL`, `WEBBLOCKS_PUBLISHER_TOKEN`, `WEBBLOCKS_PUBLISHER_PRODUCT`, and `WEBBLOCKS_PUBLISHER_CHANNEL`; cached-config runs refresh those canonical publisher keys from the project `.env`, diagnostics only report yes/no configured status, and legacy publisher aliases are intentionally unsupported. Git commits and tags are source-history steps only. GitHub Actions, GitHub releases, GitHub asset URLs, the GitHub API, and the `gh` CLI are not part of CMS update publishing, and `.github` workflows are intentionally absent.

Installed CMS working copies are update consumers. They may fetch source history when needed, but must not push upstream or publish CMS updates from an installation checkout.

## Contact Mail Diagnostics

Contact Form submissions are saved before notification delivery. SMTP failures are recorded on the saved Contact Message as email notification state and do not change the editorial status or spam classification. Scored spam is intentionally stored/quarantined for admin review; only honeypot or too-fast submissions may be discarded before storage with the generic success redirect. A future configurable threshold such as `CONTACT_SPAM_AUTO_DISCARD_SCORE` can be considered after enough production data exists to tune it safely.

Use the secret-free mail diagnostic command when a Contact Message shows notification failure:

```bash
php artisan contact:mail-diagnose
php artisan contact:mail-diagnose --block=137
php artisan contact:mail-diagnose --send-test=operator@example.com
```

The command reports the resolved mailer, host, port, scheme/encryption fields, username, from address, `CONTACT_RECIPIENT_EMAIL`, config-cache state, and optional Contact Form block/site recipient fallbacks. It never prints `MAIL_PASSWORD` or token values. The optional send test reports only success or a sanitized failure detail, so operators can distinguish stale config, host/port/encryption mismatch, username/from mismatch, and invalid mailbox credentials without leaking secrets into terminal logs.

## WebBlocks UI Manager Operator Plugin

WebBlocks UI Manager is an internal/operator plugin for product-specific release operations. It is not bundled into normal CMS runtime packages and ordinary CMS installs should not install it. Build its local artifact from the maintenance repo with:

```bash
php plugins/webblocks-ui-manager/build-plugin.php
```

Upload the generated ZIP through `System -> Plugins` as a super admin, review the installed plugin detail, then explicitly enable it. Uploaded plugins install disabled by default. Disabled or incompatible installs remain inert, and health is not checked while disabled.

If the plugin declares migrations, enablement does not imply setup is complete. The plugin detail screen shows `Setup required` / `Plugin migrations pending` when plugin-owned tables are missing. Use the super-admin `Run Plugin Migrations` action from the plugin detail screen to run only migrations declared by that installed plugin and only from the plugin install path. The action is idempotent, records a setup result in the plugin enabled-state file, and reports secret-free errors. Enabled plugin routes that are visible before setup must render controlled setup guidance instead of raw database errors.

If an install briefly ran v1.32.67 and created `webblocks_ui_manager_*` tables, this patch does not drop them automatically. Manual plugin uninstall also preserves plugin-owned tables. Leave them in place unless an operator has confirmed the plugin is not needed and performs a separate manual database cleanup with a backup.

When manually installed, enabled, and set up, the plugin adds `/webadmin/plugins/webblocks-ui-manager/releases` for WebBlocks UI release metadata, dry-run validation, and local static CDN publishing. CMS `super_admin` users can open enabled plugin routes through the plugin-owned permissions declared in the manifest; non-super-admin roles require explicit plugin permission grants. If `webblocks_ui_manager_releases`, `webblocks_ui_manager_artifacts`, or `webblocks_ui_manager_publish_runs` is missing, the Releases screen shows setup-required guidance and links back to plugin setup instead of querying missing tables. Prepare a release with local WebBlocks UI dist files:

```bash
php artisan webblocks-ui-manager:prepare-release v2.7.9 --artifact=/path/to/webblocks-ui.css --artifact=/path/to/webblocks-icons.css --artifact=/path/to/webblocks-ui.js
```

The command records release metadata, computes SHA-256 checksums, and prepares manifest metadata for the first-party static convention `public/cdn/webblocks-ui/{version}/...`. The expected dist files are configured by `webblocks-plugins.webblocks_ui_manager.expected_dist_files` and default to `webblocks-ui.css`, `webblocks-icons.css`, and `webblocks-ui.js`.

Validate the publish plan without writing files:

```bash
php artisan webblocks-ui-manager:publish-release v2.7.9 --dry-run
```

Apply the local publish after dry-run validation passes:

```bash
php artisan webblocks-ui-manager:publish-release v2.7.9
```

The publish workflow validates source paths, version-to-target path matching, expected dist files, stored checksums, manifest consistency, and idempotency before writing. Existing files with matching checksums are skipped. Existing files with different checksums block the publish. The target is local/project-owned by default through `WEBBLOCKS_UI_MANAGER_CDN_BASE_PATH=cdn/webblocks-ui`; `WEBBLOCKS_UI_MANAGER_CDN_BASE_URL` is optional display/URL metadata. The workflow does not deploy to an external production server, publish update-server metadata, or change CMS core WebBlocks UI asset URLs.

`System -> Plugins` reports disabled, enabled, incompatible, missing-files, and error states separately. Disabled plugins show inactive health, not failure. A plugin configured as enabled but incompatible remains inert: no plugin routes, commands, menus, permissions, settings routes, widgets, assets, block declarations, or health reporter behavior become active.

Manual uninstall is available only for manually uploaded plugins after they have been disabled. It removes the storage-owned installed package directory and enabled-state file, never CMS core files, public `/cms` assets, project files, vendor files, or storage outside the configured plugin root. It does not run destructive migrations or drop plugin-owned database tables.

External production CDN deployment, hosted CDN smoke checks, generic third-party plugin install/update behavior, arbitrary remote installers, Composer package installation, update-server publishing, and marketplace/catalog flows remain intentionally deferred.

## Backup / Restore

Backup / Restore is the environment-level recovery tool.

Backups can include:

- database dump
- CMS-managed uploads from `storage/app/public`
- archive metadata in `manifest.json`

Restore behavior is explicit:

- only completed backups with a valid archive can be restored
- archive download, detail, restore eligibility, and delete actions resolve paths through the same `backups` disk root and block traversal, symlink escapes, and absolute paths outside that root
- missing or unreadable archive files are shown as controlled admin feedback instead of raw filesystem exceptions
- restore creates a fresh pre-restore safety backup first
- restore replaces the current database
- restore replaces `storage/app/public` when uploads are included in the archive
- MySQL/MariaDB restores run the import with temporary foreign-key and unique-check guards, so valid full dumps remain portable even when table creation order differs between environments
- existing installs updated through System Update also run package-owned repair migrations when needed; this keeps schema contracts such as the `pages(id, site_id)` parent key aligned with backups that contain site-scoped `page_translations` foreign keys

Use Backup / Restore when you need to recover the install environment, not just one page.

## Export / Import

Export / Import is the site portability tool.

Use it to move one site's content between installs.

Site transfer packages are stored on the Laravel filesystem disk named `site-transfers`, which defaults to `storage/app/site-transfers`. Fresh Composer consumer installs register this disk automatically and `webblocks:install` prepares the storage directory, so host applications do not need to edit `config/filesystems.php` unless they want to provide a custom disk.

Site imports require the target install's database-backed block type and slot type catalogs to contain the rows referenced by the package. Fresh Composer installs seed these catalogs during `webblocks:install`, explicit catalog maintenance can repair shipped rows with `webblocks:catalog-repair`, and the import runner performs a final idempotent core catalog sync before validation when a package references a missing shipped core row. If a package references an install-specific custom block or slot that is not present on the target, the admin error lists the exact missing identifiers.

The admin workflow now has two related entry points:

- `Admin -> Sites` includes a per-site `Export` row action that opens a modal for the selected site, shows the site name and handle, and can include media files before creating the package.
- `Admin -> Maintenance -> Export / Import` is the combined operational screen for transfer history and transfer actions. It shows `Site Exports` and `Site Imports` together, with `Run Export` and `Run Import` actions in the relevant listing card headers.
- Fresh Composer consumer installs render the Export / Import screens from the package view namespace, so they do not require root `resources/views/admin/site-transfers/*` Blade files.

Relationship between the tools:

- Sites row `Export` creates a package for one selected site and returns to the Sites list with a success message.
- `Export / Import` manages package history and the main export or import operations screen.
- `Sites -> Promote` applies an export or promotion package to an existing target site with dry run, strategy, safety backup, and preserve rules.

Export / Import covers site-scoped content such as:

- site record and locale assignments
- site variables stored in `site_variables`
- pages and page translations
- page assets stored in `page_assets`
- slots and blocks
- Shared Slots and Shared Slot block trees
- page-level Page Layout settings such as `default` and `docs` (still stored internally on `public_shell`)
- block translations
- navigation items, including optional navigation item icon slugs used by public Sidebar Navigation renderers
- optional media files
- canonical site-level public override files at `public/site/{site_handle}/css/site.css` and `public/site/{site_handle}/js/site.js` when `Include media files` is enabled and those files exist

Page Assets travel through site portability in two layers:

- `page_assets` rows are always included in site export and import payloads.
- When `Include media files` is enabled, referenced `/site/...` public files are also packaged under the export archive and restored back into `public/site/...` on import.
- When `Include media files` is disabled, page asset metadata still imports, but the referenced physical files must already exist on the target install.
- Missing page asset files are reported during export and skipped rather than crashing the full package build in the current version.

Site-level public override assets are intentionally narrower than Page Assets: only `css/site.css` and `js/site.js` under the source site handle are included, and import restores them under the final target site handle. Arbitrary `public/site/...` trees, CMS core assets, and `public/storage` are not part of this path.

Shared Slots are exported and imported as first-class site content:

- Shared Slot metadata such as handle, name, slot compatibility, shell compatibility, and active status is included in the package.
- Shared Slots can also carry an optional Page Layout compatibility constraint. The stored field remains `public_shell` for backward compatibility, empty remains generic, and non-empty values still require exact Page Layout handle matches.
- Shared Slot block trees, nested order, translations, and media references travel through the same block and media packaging pipeline used for normal pages.
- Page slots that use `shared_slot` export a stable Shared Slot handle reference and are remapped to the target site's imported Shared Slot during import.
- Page payloads preserve each page's Page Layout so docs-shell pages keep compatible docs Shared Slot assignments after import.
- Install-level Page Layout definitions and Page Layout Slot definitions themselves are not part of site export/import in V1.
- Custom page `public_shell` handles still transfer with the page payload, so target installs should provide matching Page Layout handles when they rely on custom layouts.
- If a matching Page Layout handle does not exist on the target install, public rendering falls back safely.
- Hidden Shared Slot source pages are kept internal and are not treated as ordinary user-facing pages in the package.
- Shared Slot revision history is excluded from export/import, matching the current page revision portability boundary.

Site Variables are also portable site content:

- `site_variables` rows are exported and imported with the site package.
- Variable keys, labels, values, sort order, and enabled state are preserved.
- Public token behavior is not evaluated during export or import; raw values are transferred as stored.

It does not include install-global runtime data such as users, backups, update history, sessions, or contact submissions.

It also does not require the derived public search index as portable content:

- `public_search_index` is runtime-derived data
- export/import payloads do not need search rows to recreate the site
- use `php artisan search:rebuild` after import when you need fresh search rows immediately

## Single Page JSON Import

Single Page JSON Import is the page-scoped admin workflow for creating one new page from a JSON file.

- admin path: `Admin -> Pages -> Import Page`
- scope: one new page in one selected site
- schema: `webblocks.cms.page.v1`
- result: always creates a new draft page in V1
- it does not update an existing page in V1
- it does not import source page revision history
- it does not replace the site-level `Export / Import` workflow

What V1 imports:

- page core fields needed for draft creation, including page-level Page Layout
- page translations keyed by locale code for enabled target-site locales
- page slots, including `shared_slot` references by compatible same-site Shared Slot handle
- page-owned block trees with nested parent-child order
- supported block translation rows for translated block families
- page asset metadata for valid local `/site/...` CSS and JS paths

V1 constraints:

- target-site translated path conflicts block the import before writes
- Shared Slot references must already exist on the target site and be compatible by site, active state, shell, and slot name
- the importer does not create Shared Slots automatically
- unsupported schema values are rejected explicitly
- the importer is transactional and leaves no partial page when validation fails

See `docs/examples/page-import-v1.json` for the sample payload.

## Site Promotion

Site Promotion is the package-based workflow for promoting site-owned content from a source package into an existing target site.

Use it when:

- the target site already exists
- site-owned content needs controlled promotion into that site
- install-level, environment-specific, and live-runtime data must be preserved

How it differs from other tools:

- Export / Import creates or manages site transfer packages and creates a new local site from a package by default
- Site Promotion applies package content into an existing target site
- Site Clone duplicates site-owned content inside the current install without a package
- Backup / Restore is environment recovery and can replace the current database or uploads
- Updates change CMS product code and installed version, not site-owned content

V1 workflow:

- admin path: `Admin -> Sites -> Promote`
- commands: `site-promotion:inspect`, `site-promotion:dry-run`, `site-promotion:apply`
- dry run is required before apply
- apply creates a normal safety backup first
- apply rebuilds the target site's derived search rows after success

Preserved areas include:

- users and roles
- sessions, cache, jobs, and queues
- backups and update history
- visitor reports and contact submissions
- live domains and site domain records
- environment configuration, install secrets, and internal tokens
- derived `public_search_index` rows

Supported strategies:

- `additive_update`: create missing source content and update matching target content without removing extra target content
- `mirror`: create and update matching source content, then archive, deactivate, or remove absent target site-owned content where safe in V1

## Project Imports

Install-specific migration and website import workflows belong in `project/`, not CMS core.

## Search Index

Search V1 adds an install-level operational screen and command for the derived public search index.

- admin screen: `Admin -> Maintenance -> Search Rebuild`
- rebuild command: `php artisan search:rebuild`

The Search Rebuild screen reviews derived public search index coverage for published pages by site and locale, and can safely rebuild the index when derived rows need to be refreshed.

Supported rebuild scopes:

- whole install
- one site with `--site=`
- one locale with `--locale=`
- one page with `--page=`

Search rebuild is non-destructive:

- it deletes and recreates only derived rows inside the requested scope
- it does not modify page, block, translation, Shared Slot, or media content
- it does not require destructive database reset commands

## System Settings

System Settings is the compact install-level configuration screen. Editable groups are split into separate focused cards with their own save actions; Runtime Information is read-only.

It keeps:

- Project Name and Project Tagline for admin-only install context
- default locale
- timezone
- admin listing rows per page for paginated admin listing screens
- CMS Mail mode and custom mail settings for CMS-owned notifications
- cookie or privacy banner settings
- product version information
- environment information

It does not control:

- the fixed WebBlocks CMS admin brand labels
- public site branding
- public site SEO defaults
- public favicon, public search scope, or page translation SEO
- host/root auth mail or site contact form routing
- `.env` file contents or environment variables

Project Identity helps distinguish one CMS install from another in the admin topbar and browser title. Those public-facing values still live on each Site or page translation instead.

`Admin listing rows per page` defaults to `15`, accepts custom numeric values such as `10` or `12`, and changes only the default row count used by paginated admin listing screens. It does not affect public pagination.

CMS Mail defaults to Laravel environment mail configuration. When the mode is changed to CMS custom settings, CMS-owned password reset emails and future CMS-owned system notifications use the database-backed CMS mail settings through a scoped CMS mailer. Custom mail fields are shown only when CMS custom mail mode is selected. Custom CMS mail settings do not overwrite `.env`; disabling custom CMS mail returns CMS mail to the existing Laravel `MAIL_*` configuration. Stored mail secrets are never shown in plain text, blank secret updates keep the existing stored value, and diagnostics report sensitive fields only as configured or not configured. Custom SMTP diagnostics now warn when required send settings are incomplete or invalid, and password reset mail send failures return a controlled CMS mail error while logging only secret-safe technical context. The current diagnostics table is read-only; a super-admin test email action is planned as a follow-up.

## Site Clone

Site Clone duplicates site-owned content from one site into another site inside the same install.

Use Site Clone when:

- you need a second site inside the current install
- you want to duplicate page, slot, block, navigation, and locale structure without creating an export package first
- you want Shared Slots and Shared Slot-backed page slot assignments to move with the cloned site

Site Clone is different from Export / Import:

- Site Clone works inside the current install
- Export / Import is for moving a site package between installs
- Both Site Clone and Export / Import include Shared Slots, Shared Slot block trees, translations, media references, and page-level Page Layout settings, while remapping consuming page slots to target-site Shared Slots instead of leaving cross-site references behind
- Both Site Clone and Export / Import also include site-scoped `site_variables`.
- Shared Slot revision history is not cloned, matching the current page revision clone boundary.

## When To Use Which Tool

### Use Revisions When

- one page needs to be restored
- you need editorial recovery inside the current install

### Use Backup / Restore When

- the environment needs recovery
- the database or uploads need to be rolled back together

### Use Export / Import When

- a site needs to be moved to another install
- you need a portable package for one site

### Use Site Clone When

- you need to duplicate one site into another site inside the same install
- you want to keep the work inside the current environment

### Use System Updates When

- applying a published CMS release to the current install

## Install-Level Vs Site-Level Boundaries

- Updates, backups, restore, and site transfer tools are install-level features
- pages, media, navigation, and editorial workflow are primarily site-scoped content features
- users are install-level accounts, even when some roles are restricted to assigned sites
- release packaging and installed-path preservation are separate concerns: releases do not ship `project/`, while installed `project/` content is preserved across updates
