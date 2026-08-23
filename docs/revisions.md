---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/revisions
cms_title: Revisions
cms_layout: docs
cms_source_id: webblocks-cms:docs/revisions.md
---

# Revisions

## Overview

Page revisions are page-scoped editorial safety snapshots.

They are designed for recovering the content and structure of one page without treating that task as a full backup or a site transfer.

Shared Slot revisions are separate site-scoped editorial safety snapshots for reusable Shared Slot content.

Both revision types can also carry compact audit metadata alongside the snapshot itself:

- actor user reference when a real admin user is known
- `source` such as `admin`, `console`, `project_import`, `system`, or `restore`
- `event` such as `page_created`, `page_updated`, `workflow_changed`, `block_created`, `block_updated`, `block_deleted`, `block_reordered`, `slot_changed`, or `revision_restored`

Older revisions may not have this metadata. The admin UI renders those rows as `Not recorded` instead of guessing.

## What Gets Captured

Revision snapshots include:

- page core fields
- page translations
- page slots
- page block tree
- block translation rows
- media ID references

For page translations, the snapshot includes locale-aware SEO override fields as part of the editorial translation state. Restoring a page revision restores those page SEO values alongside localized name, slug, and path data.

## Automatic Capture Points

Revisions are created automatically when these areas change:

- page fields and default translation
- workflow status
- page translation records
- slot structure
- block creation, updates, deletion, and ordering
- Internal Content API staged update create, replace, and promote operations

When possible, the revision entry also records who triggered the change and what kind of workflow produced it.

For published-page staged updates, revisions are captured for the staged draft as it is created or replaced, and the published source page receives pre-promote and post-promote safety snapshots when staged content is promoted.

## Review And Restore Behavior

The Version History screen describes each saved version in page terms: saved time, actor, source, page state, and the structural changes from the preceding version. Opening **Review** shows the selected version beside the current page, including translations, slots, blocks, assets, layout, workflow, and reference-health checks. A version with missing required references is blocked from restore.

Restore is a two-step candidate workflow rather than a direct write:

1. **Prepare Restore Preview** creates a private technical draft from the selected snapshot; the current page remains unchanged.
2. The operator opens the normal CMS preview for that candidate and checks the rendered result.
3. **Apply This Version** verifies that the current page has not changed since the candidate was prepared.
4. A fresh pre-restore safety revision is created, the selected snapshot is applied to the current page, and a post-restore revision is linked to the source revision.
5. The technical candidate page is removed. The operator can instead discard it at any time without changing the current page.

Candidate pages use collision-free internal translation paths, remain drafts, and are excluded from normal page listings and site export. Their preview retains the source page's body class so page-scoped CSS can be reviewed accurately.

If somebody edits the source page after the candidate was prepared, apply stops with a stale-candidate error. The operator must discard the candidate, review the current state, and prepare a new one. This prevents an old preview from silently overwriting newer work.

The Internal Content API exposes the same workflow:

- `GET /webadmin/api/pages/{page}/versions` and `GET /webadmin/api/pages/{page}/versions/{version}` require `content.read`.
- `POST /webadmin/api/pages/{page}/versions/{version}/candidate` and candidate discard require `content.apply`.
- `POST /webadmin/api/pages/{page}/version-candidates/{candidate}/apply` requires both `content.apply` and `content.publish` because it can replace a published page.

There is deliberately no direct revision-restore API endpoint.

## Shared Slot Revisions

Shared Slot revisions are intentionally separate from page revisions.

- They are site-scoped and attached to one Shared Slot.
- They capture Shared Slot metadata plus the reusable Shared Slot block tree, including nested structure, translation rows, and media references.
- They do not capture unrelated editorial pages that reference the Shared Slot.
- They do not store page slot assignments as part of the Shared Slot revision snapshot.
- They carry the same compact actor, source, and event metadata pattern used by page revisions when the caller can provide it.

### Shared Slot Restore Behavior

Restore works in place on the current Shared Slot.

When a Shared Slot revision is restored:

1. a fresh pre-restore safety revision is created first
2. the selected Shared Slot snapshot is applied to the current Shared Slot
3. a new post-restore revision entry is recorded and linked to the source revision
4. the Shared Slot id stays the same and existing `page_slots.shared_slot_id` references remain intact

Because Shared Slots are reusable references, restoring one can affect every page that currently uses that Shared Slot.

## Access Rules

- `super_admin`: can view and restore revisions
- `site_admin`: can view and restore revisions within assigned sites
- `editor`: can view revisions within assigned sites, but cannot restore

The same access pattern applies to Shared Slot revisions.

## Revisions Vs Other Recovery Tools

### Revisions

- page-scoped editorial recovery
- focused on one page's content and structure
- Shared Slot revisions are separate and focused on one reusable Shared Slot's metadata and block tree

### Backup / Restore

- environment-level recovery
- restores the current database and uploads from a backup archive

### Export / Import

- site portability between installs
- used to move one site's content as a package

These tools are intentionally separate and should not be treated as replacements for one another.
