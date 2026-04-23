# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel application aligned with the WebBlocks UI philosophy.

## Overview

This project provides:

- a public-facing site shell
- an admin dashboard shell
- authentication flows
- reusable page, layout, block, and navigation management
- consistent Blade views and layout patterns for the WebBlocks CMS experience
- direct WebBlocks UI CDN integration

## Installation

### Generic Laravel install

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Notes:

- `php artisan db:seed` installs the core CMS catalogs and records the current app version as the installed version for a fresh install.
- `php artisan storage:link` is required if your site will serve files from `storage/app/public`.
- Runtime directories under `storage/framework`, `storage/logs`, and `bootstrap/cache` are now created automatically on first run.

### Optional DDEV local install

```bash
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
ddev artisan db:seed
ddev artisan storage:link
```

Then open:

- public site: `https://<your-project>.ddev.site`
- admin: `https://<your-project>.ddev.site/admin`

### Seed choices

Use install seeders only on a fresh install. They are intentionally blocked once a site already contains pages, blocks, or navigation content.

- Core CMS only:

```bash
php artisan db:seed
```

- Starter install:

```bash
php artisan db:seed --class=Database\\Seeders\\StarterInstallSeeder
```

- Showcase install:

```bash
php artisan db:seed --class=Database\\Seeders\\ShowcaseInstallSeeder
```

- Development/demo users:

```bash
php artisan db:seed --class=Database\\Seeders\\DevelopmentUserSeeder
```

Recommended local demo setup:

```bash
php artisan db:seed
php artisan db:seed --class=Database\\Seeders\\DevelopmentUserSeeder
php artisan db:seed --class=Database\\Seeders\\StarterInstallSeeder
php artisan storage:link
```

The full showcase site is no longer installed by the default seed path.

## Boundary Model

### Core

- reusable CMS engine and admin infrastructure
- public rendering engine and shared neutral templates
- core schema and product-owned catalogs

### Site/Application

- install-specific branding and env values
- runtime pages, blocks, navigation, media, and contact submissions
- any custom install-specific block types
- single-site public customizations loaded from `public/site/...`

### Starter/Showcase

- optional starter content
- optional showcase content
- demo media config/import tooling

## Catalog Ownership

The following catalogs are treated as product-owned core definitions:

- `PageType`
- `LayoutType`
- `SlotType`
- system `BlockType` records

First-pass rule:

- the CMS seeds and maintains these records as core catalogs
- runtime site content should reference them, not treat them as ordinary page/content data
- system block types are read-only in the admin
- non-system block types may still be created as install-specific extensions

## Stack

- This is a Laravel application.
- WebBlocks UI assets are loaded via CDN in the layout templates.
- The application uses server-rendered Blade views.
- The public site can optionally load single-site custom assets from:
  - `public/site/css/site.css`
  - `public/site/js/site.js`

## Site-Specific Public Assets

- WebBlocks CMS now supports multisite installs in core.
- `public/site/css/site.css` and `public/site/js/site.js` remain optional install-level public overrides.
- In a multisite install, treat these files as shared install assets and keep site-specific routing/content/domain behavior in CMS data instead of core templates.
- These files are optional. Public pages continue to work when they do not exist.
- The shared CMS/core public layout stays generic; install-specific visual customization should go into these `public/site` files instead of core public templates.

## Multisite And Locale Foundation

- Phase 1 adds a DB-first foundation for future multisite and multilingual support.
- `sites` stores install sites and seeds one primary `default` site for existing installs.
- `locales` stores enabled locales and seeds `en` as the default enabled locale.
- `site_locales` is the explicit relational pivot that enables locales per site.
- `pages` remains the canonical page entity and is now scoped to a `site_id`.
- `page_translations` stores locale-specific page routing fields such as `name`, `slug`, and canonical `path`.
- Existing installs are backfilled so legacy pages stay live under the primary site and default English locale.
- Public routing keeps default English prefixless and uses a locale prefix for non-default locales such as `/tr/p/about`.

## Block Content Translations

- Phase 3 keeps page and block structure canonical while localizing block content per locale.
- The canonical `blocks` table still owns shared structure and configuration such as parent/child placement, slot assignment, sort order, publish status, variants, URLs, asset references, and system settings.
- Locale-specific block content now lives in explicit relational tables instead of JSON blobs:
  - `block_text_translations`
  - `block_button_translations`
  - `block_image_translations`
  - `block_contact_form_translations`
- Supported translatable block families currently cover the starter/editorial path:
  - text family: `heading`, `text`, `rich-text`, `html`, `section`, `columns`, `column_item`, `callout`, `quote`, `faq`, `tabs`
  - button family: `button`
  - image family: `image`
  - contact form family: `contact_form`
- Shared/system blocks such as `navigation-auto`, `menu`, and `page-title` remain canonical for now.

### Field Ownership

- Text family:
  - translated: `title`, `subtitle`, `content`
  - shared: `variant`, `url`, `settings`, structural fields
- Button family:
  - translated: `title`
  - shared: `url`, `subtitle` target, `variant`
- Image family:
  - translated: caption and alt text
  - shared: `asset_id`, `url`, `variant`
- Contact form family:
  - translated: heading/title, intro text, submit label, success message
  - shared: recipient email override and delivery/storage settings

### Fallback Behavior

- Public rendering resolves block content through a centralized translation resolver.
- Requested locale content is used when a translation row exists.
- If a row is missing, rendering falls back explicitly to the default locale `en`.
- Admin slot editing surfaces translation state per block as `Translated`, `Fallback`, `Missing`, or `Shared`.
- Default locale admin edits update both canonical block fields and the default translation row.
- Non-default locale edits only update translated fields for supported block families and leave shared block config untouched.

### Extending A New Block Type

- Decide whether the new block's editable fields are translated or shared.
- Add the block slug to the correct family in `App\Support\Blocks\BlockTranslationRegistry`.
- Add or update the translation model/table if the family needs new translated columns.
- Ensure admin forms clearly separate translated copy from shared config.
- Cover the behavior with one admin locale-edit test and one public rendering/fallback test.

## Multisite Domain Resolution

- Public site resolution is now host-aware instead of relying on the primary site by default.
- Each `sites` row can carry one canonical `domain` value.
- Stored domains are normalized to host-only lowercase values:
  - protocol is stripped
  - path, query, and fragment input is rejected
  - port suffixes are removed for matching
- Public request flow is now:
  - host resolves the active site
  - locale prefix is validated against that site's enabled locales
  - page translation lookup is scoped by `site_id + locale_id + slug/path`
  - page rendering and generated public URLs continue in that same resolved site context

### Unknown Host Behavior

- Unknown host behavior is explicit and config-driven.
- `config('cms.multisite.unknown_host_fallback')` controls whether unmapped hosts fall back to the primary site.
- Default behavior:
  - local/testing: fallback enabled
  - production: fallback disabled
- When fallback is disabled, unmapped public hosts return `404` instead of silently leaking primary-site content.

### Site-Aware Locale Rules

- A locale prefix only works when that locale is enabled for the resolved site.
- Default locale remains prefixless.
- Non-default locale URLs stay prefixed.
- If a locale is disabled for the resolved site, the request returns `404` rather than downgrading silently to English.
- If a locale-specific page translation is missing, page lookup stays scoped to the resolved site and uses the existing page-translation fallback behavior only within valid site/locale bounds.

### URL Generation

- `Page::publicPath()` remains path-only and locale-aware.
- `Page::publicUrl()` now uses the page's site domain when one is configured.
- Admin preview/open links automatically use the correct site domain when available.
- If no site domain is configured, URL generation falls back to the normal application base URL.

## Admin Multisite Boundaries

- Page management stays site-aware through explicit `site_id` assignment.
- Pages index includes site context and continues to support site filtering.
- Page edit and slot/block flows show clearer site/domain context so editors know which site they are working in.
- Page translation creation is limited to locales enabled for the page's assigned site.
- Site editing keeps the system default locale enabled automatically so a site cannot become unroutable.

## Site Deletion Rules

- Site deletion is explicit through an admin confirmation screen and `php artisan site:delete`.
- The primary site cannot be deleted.
- The last remaining site cannot be deleted.
- Deletion is refused when linked install-level contact messages would be orphaned.
- Deleting a site removes only site-scoped rows such as the site record, locale assignments, pages, page translations, page slots, blocks, block translation rows, and site navigation items.
- Shared media assets and physical files are not blindly deleted during site removal.

## Local Multisite Testing

- The starter seed now creates a second site to prove host-based isolation.
- Demo host setup used by the seeded content:
  - primary site: `primary.ddev.site`
  - campaign site: `campaign.ddev.site`
- In DDEV or another local proxy, map those hosts to the same project and test with host-based requests.
- If your local environment does not support multiple mapped hosts yet, unknown-host fallback remains enabled in local/testing by default so the primary site still works.
- Useful manual checks:
  - primary site English home
  - primary site Turkish page
  - campaign site English home
  - overlapping `/p/about` on primary vs campaign host
  - `/tr/...` on campaign host returning `404`
  - unknown host fallback in local, `404` when config disables fallback

## Application Identity

Project metadata is normalized to the CMS brand:

- Composer package: `fklavyenet/webblocks-cms`
- Application name: `WebBlocks CMS`
- Application slogan: `A modern block-based CMS`

## Navigation Items Note

- Navigation Items now use a tree editor instead of a CRUD table.
- Navigation items are site-scoped and stay isolated per site.
- `menu_key`, `parent_id`, and `position` define menu structure and ordering.
- Navigation Auto blocks render data from `navigation_items` by `menu_key`.
- Drag and drop reordering auto-saves immediately after drop.
- Cycle protection and a maximum depth of 3 levels are enforced.

## Site Clone

- WebBlocks CMS now includes a first-party site clone/import flow for multisite content duplication.
- The primary orchestration lives in `App\Support\Sites\SiteCloneService`.
- CLI entry point:

```bash
php artisan site:clone {source} {target}
```

- The command supports explicit safety and scope flags such as:
  - `--target-name`
  - `--target-handle`
  - `--target-domain`
  - `--with-navigation` / `--without-navigation`
  - `--with-media` / `--without-media`
  - `--copy-media-files`
  - `--with-translations` / `--without-translations`
  - `--overwrite-target`
  - `--dry-run`
- The admin Sites area includes a compact clone screen as a thin wrapper around the same service.
- Clone scope includes:
  - target site creation/update
  - site locale assignments
  - pages and page translations
  - page slots
  - blocks and nested block trees
  - block translation rows
  - site-scoped navigation items
  - asset references, with optional duplicated asset files
- Clone intentionally does not include install-global/runtime data such as users, sessions, backups, update logs, or contact submissions.
- Default media behavior keeps install-global assets shared. Use `--copy-media-files` only when duplicated asset records and files are required.
- See `docs/site-clone.md` for focused usage and safety notes.

## Contact Form Block

- `contact_form` is a reusable block type, not a special page feature.
- Public submissions are always stored in `contact_messages` before email delivery is attempted.
- Notification recipients resolve from block-level `recipient_email`, then `CONTACT_RECIPIENT_EMAIL`.
- Message statuses are `new`, `read`, `replied`, `archived`, and `spam`.
- Anti-spam protection uses a honeypot field, a minimum submit-time check, and Laravel rate limiting on the submission route.
- In local DDEV development, Mailpit is available at the DDEV mail URL and is the preferred way to verify contact notifications.
- Set `CONTACT_RECIPIENT_EMAIL` to a local inbox target, submit `/p/contact`, then confirm both the saved `contact_messages` row and the notification state in the admin detail view.
- If mail delivery fails locally, the message record remains saved and the detail view shows the notification recipient, status, and captured error text.

## Visitor Reports V1

- Admin path: `GET /admin/reports/visitors`
- Visitor Reports is a lightweight, CMS-native page visit reporting feature for public page renders.
- Admin navigation adds a `Reports` group with `Visitor Reports` inside the CMS sidebar.
- Tracking is multisite-aware and locale-aware:
  - every event is scoped to `site_id`
  - page-linked visits store `page_id` when a CMS page is rendered
  - locale context is stored in `locale_id` when the resolved page translation has a locale
- V1 stores one row per tracked visit in `visitor_events` with the following fields:
  - `site_id`
  - `page_id` nullable
  - `locale_id` nullable
  - `path`
  - `referrer`
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
  - `device_type`
  - `browser_family`
  - `os_family`
  - `session_key`
  - `ip_hash`
  - `visited_at`

### Privacy

- Raw IP addresses are never stored in Visitor Reports.
- `ip_hash` is generated as a one-way HMAC using the visitor IP plus the application key, so the stored value is not reversible back to the original IP.
- V1 intentionally avoids collecting extra personal data beyond lightweight visit analytics needed for aggregate reporting.

### Tracking Behavior

- Tracking only runs for public CMS page requests that resolve and render successfully through the public page controller flow.
- Admin routes, API routes, asset requests, and missing pages are not tracked by this feature path.
- Obvious bot user agents are ignored with a lightweight fragment-based filter.
- Visitor session grouping uses a dedicated session-backed visitor key instead of storing raw Laravel session payload data in reports.

### Admin Screen

- The Visitor Reports screen includes:
  - date range filters for Today, Last 7 days, Last 30 days, This month, and custom from/to
  - site filtering for multisite installs
  - locale filtering for enabled locales
  - summary cards for total page views, unique visitors, total sessions, and average pages per session
  - report tables for top pages, top entry pages, top referrers, locale summary, and device summary
- Sidebar group icons use existing WebBlocks UI icon names so the admin navigation renders without fallback question-mark icons.

### Configuration

- Visitor Reports can be enabled or disabled with:

```bash
CMS_VISITOR_REPORTS_ENABLED=true
```

- If the admin screen says the visitor reports migration is missing, run `php artisan migrate` so the `visitor_events` table is created before opening `/admin/reports/visitors`.

## Visitor Reports Phase 2

- Phase 2 extends the existing Visitor Reports flow without changing the V1 public-page tracking boundary.
- New admin reporting blocks now cover campaign attribution and a compact visitor summary on `/admin`.
- Existing site, locale, and date-range filters continue to scope all visitor report aggregates, including the new UTM-based sections.

### UTM Tracking

- Public page requests now capture these optional query parameters when present:
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
- Missing or empty values are stored as `null`.
- UTM values are normalized with basic sanitization and truncated to 255 characters before insert.
- The `visitor_events` schema includes nullable columns for:
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
- UTM tracking can be enabled or disabled independently with:

```bash
CMS_VISITOR_UTM_ENABLED=true
```

### Campaign Reporting

- `/admin/reports/visitors` now includes:
  - Top Campaigns
  - Source Breakdown
  - Medium Breakdown
- These Phase 2 sections are part of the stable updater release for the `0.3.x` line, so existing CMS installs can receive campaign-aware visitor reporting without leaving the normal release channel.
- Each campaign row shows:
  - campaign label
  - page views
  - unique visitors
  - sessions
- Null or empty UTM values are grouped under `Direct / None` so reporting stays readable instead of silently dropping unattributed traffic.
- Breakdowns are ordered from highest to lowest traffic and keep the current multisite and locale filter behavior.

### Dashboard Widget

- `/admin` now shows a compact `Visitor Summary` card for the last 7 days.
- The widget includes:
  - total page views
  - unique visitors
  - top page path
- If visitor tracking is disabled or the migration is missing, the widget renders an empty-state message instead of failing.

### Release Readiness

- Phase 2 is documented in `README.md`, `CHANGELOG.md`, and the release notes used by the publish workflow.
- Stable updater releases continue to be distributed through tagged GitHub releases and WebBlocks Publisher so installed CMS sites can detect and apply the update in-app.

### Privacy

- Phase 2 does not introduce raw personal identifiers.
- `ip_hash` remains one-way and non-reversible.
- UTM values are stored exactly for lightweight attribution reporting, so campaign naming should avoid embedding personal data in URLs.

### V2 Limits

- Phase 2 adds simple attribution reporting only.
- There is still no full acquisition funnel, first-touch/last-touch attribution model, ad-network API sync, or advanced marketing analytics workspace.
- The dashboard widget is intentionally compact and limited to a 7-day operational snapshot.

### V1 Limits

- V1 focuses on readable aggregate reports inside the CMS and is intentionally not a full analytics platform.
- The baseline reporting model remains intentionally lightweight and does not include deep bot detection, heatmaps, funnels, or advanced attribution modeling.

### Test Coverage

- Feature coverage includes:
  - public page renders creating `visitor_events`
  - localized page renders storing the resolved locale
  - authenticated admin access to the Visitor Reports screen
  - site and date-range filter behavior in the reports query layer
  - obvious bot traffic being ignored
  - UTM parameter capture, sanitization, and null normalization
  - campaign/source/medium report aggregation under filters
  - dashboard visitor summary rendering on `/admin`
- Migration/backfill coverage now also accounts for the presence of the `visitor_events` table in the multisite foundation test path.

## Demo Media

- Demo media import remains available through `php artisan demo:import-media`.
- Demo-only source tracking is isolated from the core `assets` schema.
- Starter/showcase media refresh logic is intentionally separate from core migrations.

## System Updates

- Installed CMS version persists in `system_settings` under `system.installed_version`.
- WebBlocks CMS is an update client/consumer and checks a central update service.
- Release publishing and update server responsibilities live in the separate WebBlocks Publisher / `updates.webblocksui.com` project.
- Configure system updates with:
  - `WEBBLOCKS_UPDATES_ENABLED`
  - `WEBBLOCKS_UPDATES_SERVER_URL`
  - `WEBBLOCKS_UPDATES_CHANNEL`
- The admin System Updates screen is now check-only in V1 and can show:
  - update available
  - already up to date
  - incompatible update available
  - no releases found
  - update server unavailable
  - invalid or unsupported response
- The CMS update screen now performs in-app automatic updates. It downloads the published release package into a temporary workspace, verifies the checksum when available, applies the package with protected-path exclusions, runs maintenance and migration commands, records the update run log, and only then persists the installed version.
- A fresh local install now records the current app version during `db:seed`, so the System Updates screen does not pretend an older published release is already installed.

## Automated Releases

- Git tags matching `v*` trigger `.github/workflows/publish-release.yml`.
- The workflow strips the leading `v` to derive the release version, builds `webblocks-cms-<version>.zip`, creates a GitHub Release, and publishes release metadata to WebBlocks Publisher.
- Release notes come from the annotated tag message when present. If the tag has no message, the workflow uses the matching `CHANGELOG.md` section when available and otherwise falls back to a short default note.
- The release archive is a source package and excludes local/runtime content such as `.git`, `.github`, `.ddev`, `node_modules`, `vendor`, `.env*`, logs, caches, and generated storage artifacts.
- The current publish workflow sends a multipart payload with:
  - `product`
  - `version`
  - `channel`
  - `minimum_client_version`
  - `release_notes`
  - `notes`
  - `source_reference`
  - `checksum_sha256`
  - `package`
- The first structural multisite + multilingual release pins `minimum_client_version=0.1.8` so legacy single-site installs on `0.1.8` remain explicitly eligible for the stable upgrade path.
- Required GitHub Actions secret:
  - `WEBBLOCKS_PUBLISH_TOKEN`: bearer token used for `POST https://updates.webblocksui.com/api/updates/publish`
- Optional GitHub Actions secret:
  - `WEBBLOCKS_PUBLISH_URL`: override publish endpoint, defaults to `https://updates.webblocksui.com/api/updates/publish`
- The workflow is designed to fail clearly when the release already exists, the tag is invalid, the token is missing, the archive cannot be built, or the publisher rejects the request.

## Admin Dashboard Path

- The canonical admin dashboard URL is now `/admin`.
- `/admin/dashboard` remains available as a backward-compatible redirect to `/admin`.
- Auth redirects and admin entry points now resolve to the canonical `/admin` path.

## Auth Password Toggle

- Auth password fields now use a shared Blade password-field pattern mapped to existing WebBlocks UI field classes, including `wb-form-group`, `wb-input-group`, `wb-input-addon-btn`, `wb-field-error`, and the WebBlocks icon set.
- The reusable password field is used across sign in, registration, password reset, confirm password, and profile password forms.
- The reveal behavior uses small data-attribute driven JavaScript with icon-only trailing actions, `aria-label`, `aria-pressed`, and `aria-controls` updates instead of inline handlers or new frontend dependencies.

## Users And Access Control

- WebBlocks CMS includes a first-party admin-managed user system for install-level CMS and system accounts.
- Users remain install-level accounts, not public membership users, and are not included in Export / Import packages.
- The user system now covers:
  - Users Phase 1: create, edit, delete, active/inactive state, and `last_login_at` tracking
  - Users Phase 1.5: compact search, role/status filters, and admin UX polish
  - Users Phase 2: role-based authorization and site-scoped admin access
- Users admin routes:
  - `GET /admin/users`
  - `GET /admin/users/create`
  - `POST /admin/users`
  - `GET /admin/users/{user}/edit`
  - `PUT/PATCH /admin/users/{user}`
  - `DELETE /admin/users/{user}`
- User records now support:
  - `role`
  - `is_admin`
  - `is_active`
  - `last_login_at`
- Site assignments now persist in the `site_user` pivot with one row per allowed site.
- Roles:
  - `super_admin`
  - `site_admin`
  - `editor`
- Role meaning:
  - `super_admin`: full install-level access, including Users, sites, locales, settings, updates, backups, export/import, and all site content
  - `site_admin`: can access `/admin` and manage site-scoped CMS areas only for assigned sites
  - `editor`: can access `/admin` and work in site-scoped CMS areas only for assigned sites, without install-level system access
- Access-control boundary:
  - only `super_admin` users can open `/admin/users`
  - only `super_admin` users can create, edit, activate/deactivate, change roles, assign sites, and delete users from that screen
  - only `super_admin` users can access install-level system screens such as sites, locales, settings, updates, backups, and export/import
  - `site_admin` and `editor` users can access `/admin` and site-scoped CMS flows only for assigned sites
  - server-side scoping is enforced for pages, navigation, media, visitor reports, contact messages, and major block/page editing flows
  - self-profile editing remains available at `/profile`
- Login behavior:
  - inactive users cannot authenticate
  - blocked inactive logins return a friendly validation message
  - successful logins update `last_login_at`
- Safety rules:
  - the last active `super_admin` cannot be deleted
  - the last active `super_admin` cannot be deactivated or demoted
  - admins cannot delete themselves from the admin Users screen
  - the last active `super_admin` also cannot delete their own account from `/profile`
- Site assignment rules:
  - `site_admin` and `editor` users must have at least one assigned site
  - `super_admin` users do not require assigned sites and always have access to all sites
- Public `/register` remains available in the current project line. New self-registered users are created as active editors unless promoted later by a `super_admin`.
- Upgrade note:
  - run `php artisan migrate` so the new `role` column and `site_user` assignments are added before opening `/admin/users`
  - existing installs are backfilled so legacy `is_admin=true` users become `super_admin`
  - existing non-admin users become `editor`
  - existing non-super-admin users receive primary-site access as the compatibility baseline

## Editorial Workflow V1

- Editorial Workflow V1 applies to pages as the canonical content unit.
- Pages now use a first-party workflow status model:
  - `draft`: editable working content, not public
  - `in_review`: ready for review, not public
  - `published`: public
  - `archived`: retired from live use, not public
- New pages default to `draft`.
- Role permissions:
  - `super_admin`: can create, edit, submit for review, publish, move back to draft, and archive across allowed content
  - `site_admin`: can create, edit, submit for review, publish, move back to draft, and archive for assigned sites
  - `editor`: can create and edit draft content, submit for review, and move an `in_review` page back to draft, but cannot publish or archive
- Public rendering now requires the page workflow status to be `published`.
- `draft`, `in_review`, and `archived` pages return `404` on public routes, including multisite and localized routes.
- Existing block-level `draft` / `published` behavior still applies inside a published page.
- The page workflow is the outer gate:
  - page must be `published`
  - then block-level public visibility rules are applied
- Admin page editing now exposes one clear workflow model instead of a separate page publish toggle.
- Workflow actions live on the page edit screen and show only the actions allowed for the current role and page status.
- For safety, editors cannot keep changing a page after it leaves `draft`; non-draft page editing, translation editing, and slot/block editing require a `site_admin` or `super_admin` to move the page back to draft first.

## Backup Manager V1

- Admin path: `/admin/system/backups`
- Backup records persist in `system_backups` with running, completed, or failed state plus summary, output log, and triggering user.
- Restore run records persist separately in `system_backup_restores`, including source archive, restored parts, safety backup reference, output log, and failure details.
- Backup archives are stored locally under `storage/app/backups/<Y>/<m>/<d>/webblocks-cms-backup-YYYY-MM-DD-HHMMSS.zip`.
- Each archive includes:
  - `database/database.sql`
  - `uploads/public/...` from the CMS-managed `storage/app/public` area
  - `manifest.json` with app/version/driver/archive metadata
- Database dump behavior:
  - SQLite: PHP-generated SQL export from the active connection, including schema and row inserts
  - MySQL/MariaDB: environment-aware execution with `CMS_BACKUP_EXECUTION=auto|direct|ddev`
  - `auto` uses `ddev exec` in local DDEV projects and keeps the existing direct `mysqldump` / `mariadb-dump` flow elsewhere
  - Override example: `CMS_BACKUP_EXECUTION=direct` to force host execution or `CMS_BACKUP_EXECUTION=ddev` to always run inside DDEV
- Restore behavior:
  - `php artisan system:backup:restore {backup}` restores a backup by record ID or by relative archive path on the `backups` disk
  - the command requires confirmation unless `--force` is passed
  - the admin backup details screen includes an explicit restore action for completed backups only
  - restore validates `manifest.json` and `database/database.sql` before doing anything destructive
  - if the manifest says uploads are included, `uploads/public/...` must also exist in the archive
  - restore creates a fresh pre-restore safety backup of the current environment before importing the selected archive
  - if the safety backup fails, restore aborts
  - restore replaces the current database with `database/database.sql`
  - restore replaces `storage/app/public` with `uploads/public` from the archive when present
  - restore reruns `php artisan storage:link` and `php artisan optimize:clear` after the archive is applied
  - restore never deletes the source backup archive
- Local DDEV notes:
  - MySQL/MariaDB restore uses the same `CMS_BACKUP_EXECUTION=auto|direct|ddev` strategy family as backup creation
  - in local DDEV projects, `auto` prefers `ddev exec mysql` or `ddev exec mariadb` when appropriate
- Known limitations:
  - restore is intentionally explicit and not scheduled or automatic
  - restore currently targets the active configured database connection plus `storage/app/public`
  - cloud or remote backup storage sync is still not included
  - incremental/differential and encrypted backup flows are still not included

## Export / Import

- Admin paths:
  - `GET /admin/site-transfers/exports`
  - `GET /admin/site-transfers/imports`
  - `GET /admin/site-transfers/imports/create`
- Export / Import is a portable site package feature for migration and transfer between installs.
- It is intentionally distinct from Backup / Restore:
  - Backup / Restore is an environment-level safety and recovery tool.
  - Export / Import is a site-level portability tool.
- V1 scope:
  - export one site into a `.zip` package
  - include site record, locale assignments, pages, page translations, page slots, blocks, block translations, navigation items, and optional media/assets
  - import a validated package as a new local site
  - handle site handle collisions safely by generating `-imported` style suffixes when needed
  - never make an imported site primary automatically
  - never silently reuse an already claimed local domain
- Package structure uses explicit transport JSON files such as:
  - `manifest.json`
  - `data/site.json`
  - `data/locales.json`
  - `data/site_locales.json`
  - `data/pages.json`
  - `data/page_translations.json`
  - `data/page_slots.json`
  - `data/blocks.json`
  - `data/block_assets.json`
  - `data/block_*_translations.json`
  - `data/navigation_items.json`
  - `data/asset_folders.json`
  - `data/assets.json`
  - `files/public/...` when media is included
- Import validation rejects:
  - missing or invalid `manifest.json`
  - unsupported product or format version
  - corrupted JSON payload files
  - dangerous archive paths such as traversal entries
- Stored package archives live on the dedicated `site-transfers` disk under `storage/app/site-transfers/...` and are only downloaded through authenticated admin responses.

### Admin Usage

- Exports screen:
  - choose a site
  - optionally enable `Include media/assets`
  - run export and download the completed package from the details screen or index action
- Imports screen:
  - upload a `.zip` import package
  - review the manifest preview and counts
  - enter the new local site name, optional handle override, and optional domain
  - run import to create a new local site from the package

### Artisan Commands

- Export one site:

```bash
php artisan site:export {site} {--with-media}
```

- Import one package:

```bash
php artisan site:import {archive} {--name=} {--handle=} {--domain=} {--force}
```

### Limitations

- V1 import is `Create new site from package` only.
- Replace/merge/overwrite import modes are intentionally not included yet.
- Package import recreates site-scoped content and optional media, but does not import install-global runtime data such as users, sessions, system backups, update history, or contact submissions.
- Catalog references such as block types, slot types, layouts, and page types are resolved against the local install instead of being exported as site-owned content.

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
