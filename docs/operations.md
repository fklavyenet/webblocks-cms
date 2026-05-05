# Operations

## Overview

WebBlocks CMS includes install-level operational tools for updates, backups, and site transfer packages.

These tools live under the admin `System` navigation because they affect the install as a whole, not just one page or one content editor workflow.

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
- slots and blocks
- block translations
- navigation items
- optional media/assets

It does not include install-global runtime data such as users, backups, update history, sessions, or contact submissions.

## Site Clone

Site Clone duplicates site-owned content from one site into another site inside the same install.

Use Site Clone when:

- you need a second site inside the current install
- you want to duplicate page, slot, block, navigation, and locale structure without creating an export package first

Site Clone is different from Export / Import:

- Site Clone works inside the current install
- Export / Import is for moving a site package between installs

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
