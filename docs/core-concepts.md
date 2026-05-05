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

## Shared Slots

Shared Slots are a slot-content ownership layer that sits under the existing page layout model.

- Page layout still owns which slots are available.
- Page shell plus slot name still own the public wrappers for each slot.
- Each page slot source can be `page`, `shared_slot`, or `disabled`.
- `page` means the slot uses blocks owned directly by the page, which remains the current behavior.
- `shared_slot` means the slot references a site-scoped reusable Shared Slot block tree that is rendered dynamically at public runtime.
- `disabled` means the slot wrapper still exists, but no blocks are rendered inside it.
- Shared Slots are site-scoped reusable slot block trees, not copy-based templates.
- Shared Slots do not own page shells or slot wrappers. The page shell still owns the outer shell, and the consuming page slot still owns the wrapper for `header`, `main`, `sidebar`, or other slot regions.
- A valid Shared Slot contributes only the block tree rendered inside that existing page slot wrapper.
- Public Shared Slot rendering is conservative:
- Cross-site shared slot references render nothing.
- If `SharedSlot.public_shell` is set, it must be compatible with the consuming page shell.
- If `SharedSlot.slot_name` is set, it must match the consuming page slot name.
- Null or empty `public_shell` and `slot_name` act as generic matches.

Current scope now includes foundation, public rendering, site-scoped admin management, page slot source assignment, and site portability support for Shared Slots.

- Shared Slots have their own admin listing and metadata forms under the site-content area of the admin.
- Shared Slot block trees are edited through a dedicated Shared Slot block editor that reuses the current block authoring model instead of copying content into consuming page slots.
- Shared Slot blocks remain real `blocks` records connected through `shared_slot_blocks`, with translated text staying in the existing translation tables.
- Deleting a Shared Slot is blocked while any page slot still references it.
- The page editor now exposes a per-slot source selector with three supported modes:
- `Page Content`: `page_slots.source_type = page` and `shared_slot_id = null`.
- `Shared Slot`: `page_slots.source_type = shared_slot` and `shared_slot_id` points to a compatible active Shared Slot from the same site.
- `Disabled`: `page_slots.source_type = disabled` and `shared_slot_id = null`.
- Changing a slot source does not delete or detach the existing page-owned block tree for that slot. If an editor switches a slot to `shared_slot` or `disabled`, the page-owned blocks remain attached so switching back to `Page Content` restores the prior page-specific content.
- The page editor filters Shared Slot choices conservatively. Only active Shared Slots from the same site appear, and optional Shared Slot `public_shell` and `slot_name` constraints must match the consuming page shell and slot name.
- Runtime public rendering guards remain in place even after write-time validation, so invalid, stale, cross-site, inactive, or incompatible assignments still render no shared content publicly.
- Shared Slots now participate in site export/import and site clone. Their metadata, hidden-source-page block trees, translations, nested ordering, and media references are transferred as first-class site content. Consuming page slots keep `shared_slot` references by Shared Slot handle during export and are remapped to target-site Shared Slots during import and clone.
- Hidden Shared Slot source pages remain internal. They are excluded from normal page export payloads, ordinary page listings, and public routing even though their block records still back the Shared Slot editor and portability flows.
- Shared Slot revisions or history remain deferred to a later phase.

## Project Boundary

WebBlocks CMS core contains reusable CMS functionality.

- Keep install-specific code that must survive CMS updates in `project/`.
- Keep site-specific commands, views, routes, config, import helpers, and migration workflows out of core when they are not reusable product features.
- Treat `project/` as the update-safe extension boundary for one install.
- Keep reusable product behavior such as blocks, layouts, admin UI, public renderers, workflow, backup/restore, export/import, and generic project-layer support in core.
- Keep site migration/import helpers and install-specific content sync tooling in `project/`.
- Core release packages do not ship `project/` content.

See `../DEVELOPMENT.md` for the full development and release workflow.
