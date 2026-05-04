# Core Concepts

WebBlocks CMS uses an explicit relational content model built around pages, layouts, slots, and blocks.

## Content Model

The core structure is:

`Page -> Layout -> Slots -> Blocks`

- A page owns routing, site context, workflow state, and layout selection.
- A layout defines the available structural regions.
- Slots are named placement areas inside the layout, such as `header`, `main`, `sidebar`, and `footer`.
- Blocks are the actual content units placed into slots and, when supported, nested under other blocks.

Pages do not store free-form page-builder JSON. Content and relationships are kept in relational tables so structure stays explicit and reviewable.

## Page Builder

Editing happens through slots.

Typical flow:

1. Create a page.
2. Assign a layout.
3. Edit the layout's slots.
4. Add and order blocks inside those slots.

Blocks can have parent-child relationships, which allows grouped content structures without collapsing the model into opaque blobs.

## Blocks

Blocks are the reusable editorial units of the CMS.

- Shared data holds structure, placement, assets, and operational settings.
- Translated data holds user-facing text for each locale.
- User-facing content is not stored in arbitrary JSON blobs.

This keeps content ownership clear across multisite, localization, revisions, and public rendering.

## Public Shells

Public page structure is controlled at the page and slot layer.

- Page `Public Shell` is page-level outer-shell configuration.
- `default` is the standard public shell.
- `docs` is the documentation-oriented shell for layouts with header, sidebar, and main content regions.
- Slot name determines the semantic public wrapper role for that region.
- Slot wrappers are resolved automatically from page shell plus slot name. Unknown slots use the safe default `div` wrapper.
- Blocks render content inside those slot wrappers and must not own the outer page shell.

For docs-style pages, use page shell instead of pushing layout responsibility down into individual content blocks. The normal recipe is `Public Shell = Docs` with `Header`, `Sidebar`, and `Main` slots so the shell can map them to the docs navbar, sidebar, and main wrappers automatically.

## Project Boundary

WebBlocks CMS core contains reusable CMS functionality.

- Keep install-specific code that must survive CMS updates in `project/`.
- Keep site-specific commands, views, routes, and config out of core when they are not reusable product features.
- Treat `project/` as the update-safe extension boundary for one install.

See `../DEVELOPMENT.md` for the full development and release workflow.
