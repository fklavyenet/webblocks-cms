# Page Layouts

Page Layouts are install-level definitions that manage the outer public shell choice and managed slot wrappers for pages.

## V1 Scope

- Admin path: `Admin -> System -> Page Layouts`
- Access: `super_admin` only
- V1 supports list, create, edit, activate, deactivate, ordering, and managed Page Layout Slot CRUD
- V1 does not support deleting system layouts
- Pages still store the selected layout handle in `pages.settings.public_shell` for backward compatibility in this release
- The visible Page Layout fields are `Name`, `Handle`, `Description`, `Status`, `Sort Order`, and `Body Class`
- `Shell Type`, `Slot Schema JSON`, and `Wrapper Schema JSON` are deprecated compatibility fields and are no longer part of the admin form

## Built-in Layouts

- `default` / `Default Layout`: standard public page shell
- `docs` / `Docs Layout`: docs shell with header, sidebar, main, and footer region mapping

These built-in layouts remain backward compatible with older pages, imports, exports, and public rendering.

Each built-in layout now also seeds managed Page Layout Slots:

- `default`: `header`, `main`, `sidebar`, `footer`
- `docs`: `header`, `sidebar`, `main`, `footer`

## How Rendering Works

- A page stores a Page Layout handle in `public_shell`
- Runtime resolves that handle to a managed Page Layout when one exists
- The resolved Page Layout can contribute a validated `body_class` to the public `<body>` element
- Runtime resolves slot wrappers from relational `page_layout_slots` when available
- Each Page Layout Slot references a published CMS `SlotType` row and defines wrapper metadata such as element, id, classes, and trusted wrapper snippets
- If relational Page Layout Slots are not available, built-in fallback definitions keep `default` and `docs` rendering stable

In V1, custom layouts still reuse the existing `default` or `docs` public shell behavior. The runtime infers that behavior conservatively from the managed slot definitions for compatibility.

## Managed Page Layout Slots

Page Layout Slots are the managed region records attached to one Page Layout.

- Admin path: `Admin -> System -> Page Layouts -> Edit -> Page Layout Slots`
- Slot Types are the catalog for adding Page Layout Slots
- Each Page Layout Slot stores `slot_type_id`, `slot_name`, `label`, `description`, `html_element`, `html_id`, `html_classes`, `before_html`, `start_html`, `end_html`, `after_html`, `is_required`, `is_active`, `is_system`, and `sort_order`
- Validation allows only safe HTML ids, safe whitespace-separated classes, and trusted wrapper snippets with script, event-attribute, `javascript:`, `iframe`, `object`, and `embed` content rejected
- System layouts keep their system slot mapping stable: system Page Layout Slots do not allow changing the underlying slot name or Slot Type
- Non-system and non-required Page Layout Slots can be removed

## Body Class

- `Body Class` is an optional whitespace-separated token list added to the public `<body>`
- Built-in seeded values are currently `layout-default` and `layout-docs`
- Public rendering keeps the base `wb-public-body` class and appends the Page Layout body classes when available

## Ownership Boundaries

- Page Layout owns the outer public shell choice
- Page Layout also owns the public body-class tokens for that shell
- Page Layout Slots own region wrapper metadata and ordering
- Slot names own region wrapper semantics such as `header`, `main`, `sidebar`, and `footer`
- Blocks own content rendered inside those wrappers
- Sticky navbar behavior remains owned by WebBlocks UI `.wb-navbar`, not by CMS Page Layouts

## Unknown Or Missing Layouts

- Existing pages are not migrated away from `public_shell`
- Unknown layout handles remain stored as-is
- Admin editing stays safe by showing the current legacy handle as a preserved option
- Public rendering falls back safely instead of crashing
- Unknown handles fall back safely to the default runtime behavior in V1

## Shared Slot Compatibility

Shared Slot compatibility remains conservative in V1.

- Shared Slot admin screens now present this constraint as `Page Layout`, while the stored compatibility field remains `public_shell` for backward compatibility in this release
- Exact `public_shell` handle match is required when a Shared Slot sets `public_shell`
- Empty `public_shell` remains generic
- A custom layout handle that reuses docs-style runtime behavior does not automatically match Shared Slots constrained to `docs`

## Portability Boundary

- Site export/import and clone still transfer page-level `public_shell` handles as page configuration
- Install-level Page Layout definitions and Page Layout Slot definitions are not included in site export/import in V1
- If a transferred page uses a custom layout handle, the target install should have a matching Page Layout handle for the intended behavior
- If no matching layout exists on the target install, rendering falls back safely
