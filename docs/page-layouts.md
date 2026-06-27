---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/page-layouts
cms_title: Page Layouts
cms_layout: docs
cms_source_id: webblocks-cms:docs/page-layouts.md
---

# Page Layouts

Page Layouts are install-level definitions that manage the outer public shell choice and managed slot wrappers for pages.

## V1 Scope

- Admin path: `Admin -> System -> Page Layouts`
- Access: `super_admin` only
- V1 supports list, create, edit, activate, deactivate, ordering, managed Page Layout Slot CRUD, Edit Page slot comparison, and safe missing-slot apply
- V1 does not support deleting system layouts
- Pages still store the selected layout handle in `pages.settings.public_shell` for backward compatibility in this release
- The visible Page Layout fields are `Name`, `Handle`, `Description`, `Status`, `Sort Order`, and `Body Class`
- `Shell Type`, `Slot Schema JSON`, and `Wrapper Schema JSON` are deprecated compatibility fields and are no longer part of the admin form
- Raw `Slot Schema JSON` and `Wrapper Schema JSON` are no longer the admin editing model

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
- Admin editing groups these fields into `Slot Identity`, `Wrapper Markup`, `Advanced Trusted Layout HTML`, and `Status / Ordering`
- Validation allows only safe HTML ids, safe whitespace-separated classes, and trusted wrapper snippets with script, event-attribute, `javascript:`, `iframe`, `object`, and `embed` content rejected
- System layouts keep their system slot mapping stable: system Page Layout Slots do not allow changing the underlying slot name or Slot Type
- Non-system and non-required Page Layout Slots can be removed
- `CSS Classes` expects space-separated class tokens
- Wrapper classes may include layout hints such as `wb-sidebar`, `wb-dashboard-main`, or `wb-stack` when those classes fit the selected layout
- Public Navbar sticky behavior should come from the shipped `wb-navbar` primitive rather than site-level header sticky rules
- When a default public header slot contains only a Navbar block, CMS promotes the slot wrapper to the `nav.wb-navbar` root so the Navbar sticks without site-specific CSS
- `Before Slot HTML` renders before the slot wrapper
- `Slot Start HTML` renders inside the wrapper before blocks
- `Slot End HTML` renders inside the wrapper after blocks
- `After Slot HTML` renders after the slot wrapper
- Advanced trusted layout HTML is for wrapper-adjacent layout markup only and is not for scripts

## Edit Page Compare And Apply

- Edit Page now shows a `Page Layout Slots` card near slot management
- The card compares active managed Page Layout Slots for the selected Page Layout against the current page's Page Slots
- The comparison reports at least:
- Layout Slots defined by the selected Page Layout
- Page Slots already present on the page
- missing Layout Slots
- extra Page Slots not defined by the selected Page Layout
- Disabled Page Slots
- Shared Slot-backed Page Slots
- `Add Missing Layout Slots` only appears when missing Layout Slots exist
- The action is explicit. Saving normal page settings does not silently sync slots.

## Safety Rules

- `Add Missing Layout Slots` creates only missing active Page Layout Slots on the current page
- It does not delete extra Page Slots
- It does not delete blocks
- It does not change existing `page_slots.source_type`
- It does not clear existing `shared_slot_id`
- It does not enable Disabled Page Slots automatically
- It does not silently reorder or rewrite existing Page Slots
- Extra Page Slots are preserved and reported for safety, even if the selected Page Layout does not define them
- Shared Slot compatibility stays exact by stored `public_shell` handle

## New Page Defaults

- New pages use the selected Page Layout's active managed Page Layout Slots when possible
- Legacy `slot_schema` fallback remains only as a safe compatibility fallback when relational or built-in managed slot definitions are unavailable
- New pages should not require admins to manually add the standard slots after choosing a Page Layout

## Existing Page Layout Changes

- Changing the selected Page Layout on an existing page does not automatically delete or rewrite slots during normal save
- After save, Edit Page shows the updated comparison so editors can review missing or extra slots safely
- The current release intentionally prefers explicit `Add Missing Layout Slots` over automatic mutation

## Body Class

- `Body Class` is an optional whitespace-separated token list added to the public `<body>`
- Built-in seeded values are currently `layout-default` and `layout-docs`
- Public rendering keeps the base `wb-public-body` class, appends a stable `wb-page-{slug}` class from the resolved Page Translation slug, and appends Page Layout body classes when available
- The root homepage uses `wb-page-home`; for example `/contact` with slug `contact` renders `wb-page-contact`, and `/docs/internal-content-api` uses the translation slug `internal-content-api` rather than the full path
- The page slug class is normalized to lowercase `a-z`, `0-9`, and hyphen tokens so site-level CSS can target one page without content-only hooks or broad global selectors
- Layout-specific CSS can target combinations such as `body.layout-docs` plus slot ids and classes defined by Page Layout Slots
- Prefer a Navbar block for sticky public headers; CMS promotes single-Navbar default header slots to the `nav.wb-navbar` root so WebBlocks UI owns the sticky offset and stacking behavior

## Ownership Boundaries

- Page Layout owns the outer public shell choice
- Page Layout also owns the public body-class tokens for that shell
- Page Layout Slots own region wrapper metadata and ordering
- Page Layout Slots own the public wrapper element, id, classes, and optional wrapper-adjacent trusted HTML for each region
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
- Adding missing Layout Slots creates new Page Slots as `Page Content` by default and does not broaden Shared Slot compatibility behavior

## Portability Boundary

- Site export/import and clone still transfer page-level `public_shell` handles as page configuration
- Install-level Page Layout definitions and Page Layout Slot definitions are not included in site export/import in V1
- If a transferred page uses a custom layout handle, the target install should have a matching Page Layout handle for the intended behavior
- If no matching layout exists on the target install, rendering falls back safely
