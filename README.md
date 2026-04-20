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

## Application Identity

Project metadata is normalized to the CMS brand:

- Composer package: `fklavyenet/webblocks-cms`
- Application name: `WebBlocks CMS`
- Application slogan: `A modern block-based CMS`

## Navigation Items Note

- Navigation Items now use a tree editor instead of a CRUD table.
- `menu_key`, `parent_id`, and `position` define menu structure and ordering.
- Navigation Auto blocks render data from `navigation_items` by `menu_key`.
- Drag and drop reordering auto-saves immediately after drop.
- Cycle protection and a maximum depth of 3 levels are enforced.

## Contact Form Block

- `contact_form` is a reusable block type, not a special page feature.
- Public submissions are always stored in `contact_messages` before email delivery is attempted.
- Notification recipients resolve from block-level `recipient_email`, then `CONTACT_RECIPIENT_EMAIL`.
- Message statuses are `new`, `read`, `replied`, `archived`, and `spam`.
- Anti-spam protection uses a honeypot field, a minimum submit-time check, and Laravel rate limiting on the submission route.
- In local DDEV development, Mailpit is available at the DDEV mail URL and is the preferred way to verify contact notifications.
- Set `CONTACT_RECIPIENT_EMAIL` to a local inbox target, submit `/p/contact`, then confirm both the saved `contact_messages` row and the notification state in the admin detail view.
- If mail delivery fails locally, the message record remains saved and the detail view shows the notification recipient, status, and captured error text.

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
- Publisher payload fields are based on the last known CMS-side publisher contract and currently include:
  - `product`
  - `version`
  - `channel`
  - `released_at`
  - `notes`
  - `source.type`
  - `source.url`
  - `source.reference`
  - `meta.app_name`
  - `meta.app_version`
  - `meta.commit`
  - `meta.tag`
  - `meta.php_version`
  - `meta.laravel_version`
  - `meta.artifact_name`
  - `meta.artifact_url`
  - `meta.checksum`
  - `meta.checksum_algorithm`
- Required GitHub Actions secret:
  - `WEBBLOCKS_PUBLISH_TOKEN`: bearer token used for `POST https://updates.webblocksui.com/api/updates/publish`
- Optional GitHub Actions secret:
  - `WEBBLOCKS_PUBLISH_URL`: override publish endpoint, defaults to `https://updates.webblocksui.com/api/updates/publish`
- The workflow is designed to fail clearly when the release already exists, the tag is invalid, the token is missing, the archive cannot be built, or the publisher rejects the request.

## Admin Dashboard Path

- The canonical admin dashboard URL is now `/admin`.
- `/admin/dashboard` remains available as a backward-compatible redirect to `/admin`.
- Auth redirects and admin entry points now resolve to the canonical `/admin` path.

## Backup Manager V1

- Admin path: `/admin/system/backups`
- Backup records persist in `system_backups` with running, completed, or failed state plus summary, output log, and triggering user.
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
- Not supported yet:
  - restore UI or one-click restore
  - scheduled backups
  - cloud or remote storage sync
  - incremental/differential or encrypted backup flows

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
