# Operations

## Overview

WebBlocks CMS includes install-level operational tools for updates, backups, and site transfer packages.

`Settings` also lives under the admin `System` navigation because it controls install-level project identity, locale, timezone, privacy, version, and environment settings.

`Maintenance` remains the operational tools group for:

- Visitor Reports
- Search
- Backups
- Export / Import
- Update

## System Updates

System Updates checks the installed CMS version against the configured update service.

The update screen can report states such as:

- update available
- already up to date
- incompatible update available
- no releases found
- update server unavailable
- invalid or unsupported response

The in-app update flow downloads the release package, applies protected-path rules, runs maintenance and migration commands, and records the update run before persisting the installed version.

Published release packages are core product packages. They ship reusable CMS source, assets, migrations, views, routes, config, docs, and tests, but do not ship install-specific project-layer content from `project/`.

## Backup / Restore

Backup / Restore is the environment-level recovery tool.

Backups can include:

- database dump
- CMS-managed uploads from `storage/app/public`
- archive metadata in `manifest.json`

Restore behavior is explicit:

- only completed backups with a valid archive can be restored
- restore creates a fresh pre-restore safety backup first
- restore replaces the current database
- restore replaces `storage/app/public` when uploads are included in the archive

Use Backup / Restore when you need to recover the install environment, not just one page.

## Export / Import

Export / Import is the site portability tool.

Use it to move one site's content between installs.

Export / Import covers site-scoped content such as:

- site record and locale assignments
- pages and page translations
- page assets stored in `page_assets`
- slots and blocks
- Shared Slots and Shared Slot block trees
- page-level Public Shell settings such as `default` and `docs`
- block translations
- navigation items
- optional media/assets

Page Assets travel through site portability in two layers:

- `page_assets` rows are always included in site export and import payloads.
- When `Include media/assets` is enabled, referenced `/site/...` public files are also packaged under the export archive and restored back into `public/site/...` on import.
- When `Include media/assets` is disabled, page asset metadata still imports, but the referenced physical files must already exist on the target install.
- Missing page asset files are reported during export and skipped rather than crashing the full package build in the current version.

Shared Slots are exported and imported as first-class site content:

- Shared Slot metadata such as handle, name, slot compatibility, shell compatibility, and active status is included in the package.
- Shared Slot block trees, nested order, translations, and media references travel through the same block and asset packaging pipeline used for normal pages.
- Page slots that use `shared_slot` export a stable Shared Slot handle reference and are remapped to the target site's imported Shared Slot during import.
- Page payloads preserve each page's `Public Shell` so docs-shell pages keep compatible docs Shared Slot assignments after import.
- Hidden Shared Slot source pages are kept internal and are not treated as ordinary user-facing pages in the package.
- Shared Slot revision history is excluded from export/import, matching the current page revision portability boundary.

It does not include install-global runtime data such as users, backups, update history, sessions, or contact submissions.

It also does not require the derived public search index as portable content:

- `public_search_index` is runtime-derived data
- export/import payloads do not need search rows to recreate the site
- use `ddev artisan search:rebuild` after import when you need fresh search rows immediately

## Project Imports

Install-specific migration and website import workflows belong in `project/`, not CMS core.

For the WebBlocks UI docs migration workflow:

- prepare or reconcile the docs-shell dependency pages first with `ddev artisan project:webblocksui-setup-site`
- create a safety dump before database-affecting project imports, for example `ddev export-db --file=before-webblocksui-fast-html-docs-import.sql.gz`
- import curated native docs payloads explicitly when needed with `ddev artisan project:webblocksui-import docs-architecture`, `docs-foundation`, `docs-layout`, `docs-primitives`, and `docs-icons`
- import the remaining static docs pages quickly with `ddev artisan project:webblocksui-import remaining-docs-html`

The fast remaining-docs workflow is intentionally non-destructive and project-specific:

- it reads `storage/project/webblocksui.com/docs-html-remaining.json`
- it fetches source pages from `https://ui.webblocksui.com`
- it extracts only the primary docs `<main>` content
- it strips static shell markup such as navbar, sidebar, duplicate docs navigation, overlay roots, and scripts
- it keeps the docs shell, header Shared Slot, sidebar Shared Slot, and docs navigation dynamic in CMS
- it imports the extracted fragment as one trusted `html` block in the page-owned `main` slot
- it skips existing pages by default and preserves the known curated/native docs imports
- `--force-html` is explicit and conservative; it does not replace those curated/native docs pages

## Search Index

Search V1 adds an install-level operational screen and command for the derived public search index.

- admin screen: `Admin -> Maintenance -> Search`
- rebuild command: `ddev artisan search:rebuild`

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

System Settings is the compact install-level configuration screen.

It keeps:

- Project Name and Project Tagline for admin-only install context
- default locale
- timezone
- cookie or privacy banner settings
- product version information
- environment information

It does not control:

- the fixed WebBlocks CMS admin brand labels
- public site branding
- public site SEO defaults
- public favicon, public search scope, or page translation SEO

Project Identity helps distinguish one CMS install from another in the admin topbar and browser title. Those public-facing values still live on each Site or page translation instead.

## Site Clone

Site Clone duplicates site-owned content from one site into another site inside the same install.

Use Site Clone when:

- you need a second site inside the current install
- you want to duplicate page, slot, block, navigation, and locale structure without creating an export package first
- you want Shared Slots and Shared Slot-backed page slot assignments to move with the cloned site

Site Clone is different from Export / Import:

- Site Clone works inside the current install
- Export / Import is for moving a site package between installs
- Both Site Clone and Export / Import include Shared Slots, Shared Slot block trees, translations, media references, and page-level `Public Shell` settings, while remapping consuming page slots to target-site Shared Slots instead of leaving cross-site references behind
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
