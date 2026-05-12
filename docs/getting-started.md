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

For reusable public headers, the recommended pattern is:

- build the menu in `Admin -> Navigation`
- create or edit a `Header` Shared Slot for the site
- add `Sticky Navbar` to that Shared Slot
- choose the navigation menu there instead of entering links manually

The first Sticky Navbar release is desktop-first. It supports sticky, fixed, or static placement plus light, transparent, or dark variants, but it does not add a mobile collapse menu yet.

In the slot editor:

- the block picker opens on a curated `Common` tab by default
- the drag handle uses a plain fallback grip marker so sortable rows remain usable even if an icon font entry is unavailable
- `Delete All Blocks` appears only when the current page slot or Shared Slot already contains blocks, and the confirmation modal shows how many top-level and nested blocks will be removed

When you reach a page from a filtered `Pages` list, the admin now keeps that Pages list context while you move through Edit Page, slot editing, translation editing, and save flows, so returning to `Pages` takes you back to the same filters and sort order.

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
