# Getting Started

## Log In To Admin

Open `/admin` and sign in with an active admin account.

- `super_admin` can access install-level and site-level areas
- `site_admin` and `editor` can access only the site-scoped admin areas for their assigned sites

## Create Or Edit A Site

If your install uses more than one site, start by confirming which site you are working on.

- `super_admin` users can manage sites in the `System` section
- `site_admin` and `editor` users work only inside their assigned sites

## Create Your First Page

Open `Pages` in the admin sidebar and create a page.

New pages start as `draft`, which means:

- the page is not public yet
- editors can keep editing it freely
- the page can be submitted for review when ready

## Edit Content With Blocks

Use the page builder to add or edit blocks in the page layout.

At a high level, you can:

- choose the page structure through layouts and slots
- add content blocks to the page
- attach media where supported
- build or edit site navigation from the Navigation area

Media and navigation are managed separately from the page editor, but they work together with page content.

## Workflow Basics

Pages move through these statuses:

- `draft`
- `in_review`
- `published`
- `archived`

Typical flow:

1. create the page in `draft`
2. edit the page and its blocks
3. submit it for review
4. publish it when approved

Editors can prepare content and submit it for review. Publishing requires a `site_admin` or `super_admin`.

## Publishing Basics

Only `published` pages are public.

- `draft`, `in_review`, and `archived` pages return `404` on public routes
- a published page still respects block-level visibility rules inside the page

## Open The Public Page

After publishing, open the page through its public route or use the admin preview/open link for that page.

In multisite installs, public URLs follow the resolved site and locale context.

## Next Areas To Learn

- Users and roles: `docs/users-and-permissions.md`
- Workflow and approvals: `docs/editorial-workflow.md`
- Revision recovery: `docs/revisions.md`
- Backups, updates, export/import: `docs/operations.md`
