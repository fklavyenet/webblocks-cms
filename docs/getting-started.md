# Getting Started

## Log In To Admin

Open `/webadmin` and sign in with an active admin account.

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

When you use `Gallery`, treat it as a media-collection block instead of a section-intro block.

- use `Content Header` plus `Plain Text` or `Rich Text` before Gallery when you need a heading or explanatory copy
- Gallery no longer renders its own public intro heading or paragraph
- the old `Gallery Title` and `Description` fields are no longer normal Gallery editor fields
- gallery items are managed as compact list rows with reorder and per-item edit controls instead of the older selected-assets grid
- gallery media selection, ordering, and presentation stay shared across locales
- per-item alt text, caption, overlay title, and overlay text are locale-owned

For reusable public headers, the recommended pattern is:

- build the menu in `Admin -> Navigation`
- create or edit a `Header` Shared Slot for the site
- add `Navbar` to that Shared Slot
- add child blocks inside it such as `Container`, `Cluster`, `Navbar Brand`, `Navbar Navigation`, and `Header Actions`
- choose the navigation menu on the `Navbar Navigation` child block instead of entering links manually

`Navbar` renders only `nav.wb-navbar` and its child blocks. It does not automatically add a `Container`, brand wrapper, menu wrapper, or generated actions area. When constrained width is needed, place a `Container` block inside the Navbar.

`Container` is primarily a width primitive. Legacy containers still render stacked flow by default, but for navbar composition set Container `Flow` to `None` so it renders only `div.wb-container`, then place `Cluster` inside it for horizontal composition.

`Cluster` is the horizontal or grouped layout primitive. Use its settings to control `Width`, `Justify`, `Align`, `Wrap`, and `Gap` without custom CSS.

`Navbar Brand` and `Navbar Navigation` must be inside the Navbar tree, but they do not need to be direct children of `Navbar`. A recommended pattern is `Navbar -> Container (Flow: None) -> Cluster (Width: Full, Justify: Between, Align: Center, Wrap: Nowrap)`, then place `Navbar Brand` plus an inner `Cluster (Justify: End, Align: Center, Wrap: Nowrap)` that holds `Navbar Navigation` and `Header Actions`.

When a `Navbar Navigation` block has visible items, CMS now renders a mobile burger toggle automatically. On mobile the inline desktop links collapse behind that toggle, the brand stays visible, header actions remain available, and the opened menu is rendered below the row through the existing WebBlocks UI dropdown behavior.

`Navbar Brand` supports logo-only usage when a logo image is present. Visible title text is optional in that case, and the accessible label falls back to the configured brand label or the resolved site name.

`Position` is the only built-in Navbar setting: `static`, `sticky`, or `fixed`. Visual variants and navbar styling belong to WebBlocks UI class usage, not Navbar block-specific CMS settings.

In the slot editor:

- the block picker opens on a curated `Common` tab by default, every tab or search result stays in `Name A-Z` order, the modal header shows how many block types are currently listed, and long result sets scroll inside the modal without hiding the search toolbar
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
