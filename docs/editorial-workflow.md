---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/editorial-workflow
cms_title: Editorial Workflow
cms_layout: docs
cms_source_id: webblocks-cms:docs/editorial-workflow.md
---

# Editorial Workflow

## Workflow Statuses

Pages use four workflow statuses:

- `draft`
- `in_review`
- `published`
- `archived`

New pages start as `draft`.

## What The Statuses Mean

### `draft`

- working content
- not public
- editors can edit content in this state

### `in_review`

- ready for review
- not public
- editors cannot keep editing page content until the page is moved back to `draft`

### `published`

- public
- routable on the public site
- page visibility still works together with block-level visibility rules inside the page

### `archived`

- retired from live use
- not public
- can be moved back to `draft` or published again by an allowed role

## Who Can Move Between Statuses

### `editor`

Allowed actions:

- `draft` -> `in_review`
- `in_review` -> `draft`

Not allowed:

- publishing
- archiving

### `site_admin`

Allowed actions for assigned sites:

- `draft` -> `in_review`
- `draft` -> `published`
- `in_review` -> `draft`
- `in_review` -> `published`
- `in_review` -> `archived`
- `published` -> `draft`
- `published` -> `archived`
- `archived` -> `draft`
- `archived` -> `published`

### `super_admin`

`super_admin` follows the same workflow abilities as `site_admin`, but across the whole install.

## Public Visibility Rules

Only pages with status `published` are public.

- `draft` pages return `404`
- `in_review` pages return `404`
- `archived` pages return `404`

This applies across normal public routes, multisite routing, and localized routes.

Page workflow status and block status are separate. The normal Edit Page -> Overview `Publish` action publishes the page record only. It does not silently publish draft or in-review blocks inside the page. If a page has unpublished page-owned blocks, the publish action opens a modal with an unchecked `Also publish all unpublished page-owned blocks` option. Leaving it unchecked preserves the page-only behavior; checking it publishes eligible page-owned blocks in the same workflow action.

The Overview tab also shows an `Unpublished page content` helper when page-owned blocks are still draft or in review. `Publish page-owned blocks` publishes only those page-owned blocks and does not change the page workflow status.

Shared Slot content is excluded from these page-owned block publishing workflows. Shared Slot-backed header, footer, or other slots must be reviewed and published separately; WebBlocks CMS does not cascade page publishing into Shared Slot block trees.

## Workflow And Page Editing

Workflow state also affects who can keep editing page content.

- editors can edit page content only while the page is in `draft`
- once a page leaves `draft`, content editing requires a `site_admin` or `super_admin`, or a workflow move back to `draft`
- `site_admin` and `super_admin` users can continue working across workflow states they are allowed to manage

## Practical Flow

1. editor creates a page in `draft`
2. editor finishes content and submits it for review
3. site admin or super admin reviews the page
4. site admin or super admin publishes the page
5. later updates can move the page back to `draft`, then through review again

## Staged Updates For Published Pages

Internal operator workflows can prepare updates for a published page without moving the source page back to `draft`. The staged update workflow creates a separate draft staging page linked to the published source page. The published source page stays public at its existing path while the staged page can be previewed through `/webadmin/pages/{page}/preview`.

Promotion is explicit. When a staged update is promoted, WebBlocks CMS copies approved page-owned slot content back to the published source page, writes promoted page-owned blocks as `published`, preserves the source page path and status, and leaves Shared Slot-backed slots out of scope. After recording the source page's pre/post-promote safety versions, it deletes the technical staged page in the same transaction. An abandoned active staged draft can be discarded through its dedicated Internal Content API action without granting broad page-delete authority.
