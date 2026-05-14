# Block Type Contracts

## Purpose And Scope

This document is the Phase 1 inventory of the currently shipped published core block types in WebBlocks CMS.

Phase 1 is read-only documentation only.

- It does not redesign block forms.
- It does not add a DB-driven form builder.
- It does not migrate block content.
- It does not change public rendering.
- It does not change how `Admin -> System -> Block Types` edits work today.

The current Block Types admin screen remains a catalog and metadata screen. It is not yet a dynamic contract viewer or dynamic form builder.

## Definition

A Block Type Contract is the current technical agreement for how one block type behaves across CMS layers:

- catalog identity: slug, label, category, status, and system or container metadata
- admin form source: which Blade partial currently edits the block and which fields it exposes
- validation and request handling: how `App\Http\Requests\Admin\BlockRequest` currently normalizes and validates block payloads
- storage ownership: which values live on `blocks`, dedicated translation rows, `block_media`, or related records
- translation ownership: which user-facing fields are locale-owned
- shared ownership: which settings or relationships remain shared across locales
- media or relationship ownership: direct `media_id`, ordered `block_media`, navigation lookups, or same-page relationships
- child support: whether the block is a container and whether child types are restricted
- public renderer source: which public Blade partial renders the block today
- renderer root contract: whether the block owns its public root markup or relies on the generic wrapper path
- portability and revisions: whether the current storage shape should continue to travel through revisions, clone, export/import, and promotion
- tests and gaps: known focused coverage, unclear behavior, or compatibility debt

## Current Contract Terms

Status terms used below:

- `clear`: admin form, request handling, storage, and renderer mostly line up
- `mostly clear`: current contract is understandable but has one notable caveat
- `transitional`: current contract intentionally carries a compatibility path or mixed ownership pattern
- `needs review`: current code paths disagree or behavior is underdocumented enough that Phase 2 should surface it more clearly
- `legacy/fallback`: published behavior depends on a fallback or compatibility path

## Storage Ownership Rules

Current and future contract work should keep these ownership rules explicit:

- user-facing copy belongs in translation rows when the block is locale-owned
- shared operational or settings data belongs in shared block settings or explicit relationships
- media should use `media_id` or `block_media` ownership paths where applicable
- avoid moving user-facing content into arbitrary settings JSON
- keep compatibility storage paths documented when they still affect public output or imports

## Shipped Sources

This Phase 1 inventory is based on the shipped source, not guesses.

- catalog source: `app/Support/Blocks/CoreBlockTypeCatalogSyncer.php`
- block edit request normalization: `app/Http/Requests/Admin/BlockRequest.php`
- persistence helpers: `app/Support/Blocks/BlockPayloadWriter.php`, `app/Support/Blocks/BlockTranslationWriter.php`, `app/Support/Blocks/BlockTranslationResolver.php`
- translation registry: `app/Support/Blocks/BlockTranslationRegistry.php`
- admin forms: `resources/views/admin/blocks/types/*.blade.php`
- public renderers: `resources/views/pages/partials/blocks/*.blade.php`
- coverage references: block catalog, rendering, translation, and slug-specific tests under `tests/Feature/`

Published core block types currently documented here: `30`.

## Audit Command

Phase 1 adds a safe developer audit command:

```bash
ddev artisan block-types:contracts-audit
ddev artisan block-types:contracts-audit --json
```

The command is read-only.

- it does not modify the database
- it does not depend on installed site content
- it reads the shipped core catalog definitions
- it verifies shipped admin form and public renderer file presence
- it reports translation-family metadata and basic container support

The command is a freshness aid for catalog and file-presence drift. It is not a substitute for the fuller contract notes in this document.

## Published Block Contract Matrix

### Content

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `header` | Header | `content` | `resources/views/admin/blocks/types/header.blade.php` | `title` via text translation rows | `variant` heading level; `settings.alignment`; shared anchor in `settings.anchor` with legacy `url` fallback | Same-page TOC reads anchored Header blocks | Not a container | `resources/views/pages/partials/blocks/header.blade.php` | Owns its root heading element | `clear` | `SyncCoreBlockTypesCommandTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Canonical heading block; shared anchor contract is clear but still has legacy `url` fallback behavior. |
| `plain_text` | Plain Text | `content` | `resources/views/admin/blocks/types/plain_text.blade.php` | `content` via text translation rows | `settings.alignment` | None | Not a container | `resources/views/pages/partials/blocks/plain_text.blade.php` | Owns its root `<p>` | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Simple translated body-copy primitive. |
| `rich-text` | Rich Text | `content` | `resources/views/admin/blocks/types/rich-text.blade.php` | `content` via text translation rows | None | None | Not a container | `resources/views/pages/partials/blocks/rich-text.blade.php` | Owns its `.wb-rich-text` root when content exists | `clear` | `RichTextBlockTest`, `PublicRichContentTest`, `BlockTranslationIntegrityTest` | Safe HTML storage and translation ownership are clear. |
| `code` | Code | `content` | `resources/views/admin/blocks/types/code.blade.php` | None in the registry today | Canonical `title`, `subtitle`, `content`; `settings.language` | None | Not a normal container | `resources/views/pages/partials/blocks/code.blade.php` | Owns its `<pre><code>` root | `needs review` | `PublicRichContentTest` | Admin copy presents translated fields, but `code` is not in `BlockTranslationRegistry`; renderer also appends children even though the block is not a normal container. |
| `button_link` | Button Link | `content` | `resources/views/admin/blocks/types/button_link.blade.php` | `title` label via text translation rows | `settings.url`; `settings.target`; shared `variant` | None | Not a container | `resources/views/pages/partials/blocks/button_link.blade.php` | Owns its `<a.wb-btn>` root | `clear` | `PublicEditorialBlocksRenderingTest` | Shared URL and target with translated label are consistent. |
| `card` | Card | `content` | `resources/views/admin/blocks/types/card.blade.php` | `eyebrow`, `title`, `subtitle`, `content`, `meta` via text translation rows | `settings.url`; `settings.target`; `settings.variant` | Child footer actions are nested blocks; legacy single-action fallback uses translated `meta` | Container; allowed children are `cluster` and `button_link` | `resources/views/pages/partials/blocks/card.blade.php` | Owns its root card element | `transitional` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Current contract intentionally keeps both nested child actions and the older single-action fallback. |
| `stat-card` | Stat Card | `content` | `resources/views/admin/blocks/types/stat-card.blade.php` | `title`, `subtitle`, `content` via text translation rows | Canonical `url` is editable today | None | Not a container | `resources/views/pages/partials/blocks/stat-card.blade.php` | Owns its stat-card root | `needs review` | `StatCardTest`, `BlockTranslationIntegrityTest` | Admin form stores an optional URL, but the current public renderer does not use it. |
| `table` | Table | `content` | `resources/views/admin/blocks/types/table.blade.php` | None in the registry today | Canonical `title`, `content`, `variant`; renderer also checks `settings.rows` fallback | None | Not a normal container | `resources/views/pages/partials/blocks/table.blade.php` | Owns its table wrapper root | `needs review` | `PublicRichContentTest` | Admin copy presents table text as translated, but storage remains canonical/shared; renderer also still supports children and a `settings.rows` fallback path not written by the core form. |
| `quote` | Quote | `content` | `resources/views/admin/blocks/types/quote.blade.php` | None in the registry today | Canonical `title`, `subtitle`, `content`, `variant` | None | Not a normal container | `resources/views/pages/partials/blocks/quote.blade.php` | Owns its quote root | `needs review` | `PublicRichContentTest` | Renderer can still append children even though the block is not a normal container; no focused current quote-only test was found. |

### Layout

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `section` | Section | `layout` | `resources/views/admin/blocks/types/section.blade.php` | None | `settings.layout_name`; `settings.spacing` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/section.blade.php` | Owns its root `<section>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest` | Shared layout wrapper contract is straightforward. |
| `container` | Container | `layout` | `resources/views/admin/blocks/types/container.blade.php` | None | `settings.layout_name`; `settings.width`; `settings.flow` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/container.blade.php` | Owns its root container `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest` | Legacy default still falls back to stacked flow when unset, but current ownership is explicit. |
| `cluster` | Cluster | `layout` | `resources/views/admin/blocks/types/cluster.blade.php` | None | `settings.layout_name`; `settings.gap`; `settings.alignment`; `settings.items_alignment`; `settings.wrap`; `settings.width` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/cluster.blade.php` | Owns its root cluster `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest` | Shared layout settings are explicit and renderer-owned. |
| `grid` | Grid | `layout` | `resources/views/admin/blocks/types/grid.blade.php` | None | `settings.layout_name`; `settings.columns`; `settings.gap` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/grid.blade.php` | Owns its root grid `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest` | Shared grid settings are explicit and renderer-owned. |

### Pattern

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `content_header` | Content Header | `pattern` | `resources/views/admin/blocks/types/content_header.blade.php` | `title`, `subtitle`, `meta` via text translation rows | Shared `variant` heading level; `settings.alignment` | `meta` is stored as structured list content | Not a container | `resources/views/pages/partials/blocks/content_header.blade.php` | Owns its root `<header>` | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Translated text plus shared heading-level contract is clear. |
| `alert` | Alert | `pattern` | `resources/views/admin/blocks/types/alert.blade.php` | `title`, `content` via text translation rows | `settings.variant` | None | Not a container | `resources/views/pages/partials/blocks/alert.blade.php` | Owns its alert root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared variant and translated copy line up cleanly. |

### Navigation

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `link-list` | Link List | `navigation` | `resources/views/admin/blocks/types/link-list.blade.php` | `title`, `subtitle`, `content` via text translation rows | None | Child `link-list-item` blocks | Container; only `link-list-item` children | `resources/views/pages/partials/blocks/link-list.blade.php` | Owns its `.wb-link-list` root when child rows exist | `transitional` | `LinkListBlockTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Intro copy is editable and translated, but current public renderer outputs only child rows. |
| `link-list-item` | Link List Item | `navigation` | `resources/views/admin/blocks/types/link-list-item.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `url` | Parent `link-list` relationship | Not a container | `resources/views/pages/partials/blocks/link-list-item.blade.php` | Owns its row link root | `clear` | `LinkListBlockTest`, `BlockTranslationIntegrityTest` | Shared URL with translated row copy is consistent. |
| `toc` | TOC | `navigation` | `resources/views/admin/blocks/types/toc.blade.php` | None | Canonical `title` only | Same-page published `header` blocks with valid anchors | Not a normal container | `resources/views/pages/partials/blocks/toc.blade.php` | Owns its generated TOC wrapper when headings exist | `mostly clear` | `PublicRichContentTest` | Contract is understandable, but renderer still appends children even though TOC is not a normal container. |
| `breadcrumb` | Breadcrumb | `navigation` | `resources/views/admin/blocks/types/breadcrumb.blade.php` | None | Intended shared `settings.home_label`; `settings.include_current` | Current page, site, and locale breadcrumb context | Not a container | `resources/views/pages/partials/blocks/breadcrumb.blade.php` | Owns its `<nav.wb-breadcrumb>` root | `needs review` | `PublicEditorialBlocksRenderingTest` | Renderer reads breadcrumb settings, but `BlockRequest` currently nulls `settings` for this slug, so admin form values do not persist. |
| `header-actions` | Header Actions | `navigation` | `resources/views/admin/blocks/types/header-actions.blade.php` | None | Shared `settings.show_mode_toggle`; `settings.show_accent_toggle`; `settings.show_search` | Search route plus client-side UI hooks | Not a container | `resources/views/pages/partials/blocks/header-actions.blade.php` | Owns its inner action cluster only | `clear` | `PublicEditorialBlocksRenderingTest` | System utility block; no translation ownership by design. |
| `sticky-navbar` | Navbar | `navigation` | `resources/views/admin/blocks/types/sticky-navbar.blade.php` | None | `settings.layout_name`; `settings.sticky_mode` | Nested navbar child blocks | Container; allowed children are `container`, `cluster`, `header`, `plain_text`, `rich-text`, `button_link`, `navbar-brand`, `navbar-navigation`, `header-actions`, `search-form` | `resources/views/pages/partials/blocks/sticky-navbar.blade.php` | Public renderer owns the outer `<nav.wb-navbar>` root | `mostly clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest` | Renderer clearly owns a root, but `Block::ownsPublicRoot()` does not currently include `sticky-navbar`. |
| `navbar-brand` | Navbar Brand | `navigation` | `resources/views/admin/blocks/types/navbar-brand.blade.php` | `title`, `subtitle` via text translation rows | Shared `settings.url`; `settings.target`; `settings.aria_label` | Shared logo media via `media_id`; site home URL fallback | Not a container | `resources/views/pages/partials/blocks/navbar-brand.blade.php` | Owns the inner brand link only | `mostly clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Public renderer can fall back to site home URL, but the admin request still requires a URL on default-locale edits. |
| `navbar-navigation` | Navbar Navigation | `navigation` | `resources/views/admin/blocks/types/navbar-navigation.blade.php` | None | Canonical `title` as shared ARIA label; `settings.menu_key` | Shared `NavigationItem` menu tree | Not a container | `resources/views/pages/partials/blocks/navbar-navigation.blade.php` | Owns the inner navigation wrapper only | `clear` | `PublicEditorialBlocksRenderingTest` | Shared menu binding and ARIA label are current product-owned behavior. |
| `sidebar-brand` | Sidebar Brand | `navigation` | `resources/views/admin/blocks/types/sidebar-brand.blade.php` | `title`, `subtitle` via text translation rows | Shared `settings.url`; `settings.target` | Shared logo media via `media_id` | Not a container | `resources/views/pages/partials/blocks/sidebar-brand.blade.php` | Owns the inner brand link only | `mostly clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Similar to Navbar Brand, but there is no separate shared accessible-label fallback for logo-only output. |
| `sidebar-navigation` | Sidebar Navigation | `navigation` | `resources/views/admin/blocks/types/sidebar-navigation.blade.php` | `title` via text translation rows | Shared `settings.menu_key`; `settings.layout_name`; `settings.show_icons`; `settings.active_matching` | Either CMS `NavigationItem` tree or manual child blocks | Container; only `sidebar-nav-item` and `sidebar-nav-group` children | `resources/views/pages/partials/blocks/sidebar-navigation.blade.php` | Owns its `<nav.wb-sidebar-nav>` root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared menu-mode settings and manual child mode are both explicit. |
| `sidebar-nav-item` | Sidebar Nav Item | `navigation` | `resources/views/admin/blocks/types/sidebar-nav-item.blade.php` | `title` via text translation rows | Shared `settings.url`; `settings.target`; `settings.icon`; `settings.active_mode`; `settings.manual_active` | Shared icon catalog slug; parent sidebar relationship | Not a container | `resources/views/pages/partials/blocks/sidebar-nav-item.blade.php` | Owns its sidebar link root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared link behavior plus translated label line up well. |
| `sidebar-nav-group` | Sidebar Nav Group | `navigation` | `resources/views/admin/blocks/types/sidebar-nav-group.blade.php` | `title` via text translation rows | Shared `settings.icon`; `settings.initially_open`; `settings.layout_name` | Child `sidebar-nav-item` blocks; icon catalog slug | Container; only `sidebar-nav-item` children | `resources/views/pages/partials/blocks/sidebar-nav-group.blade.php` | Owns its `.wb-nav-group` root | `needs review` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Nested group rendering bypasses some `sidebar-nav-item` helper behavior, so child icon and active-mode rules are not fully shared across both paths. |
| `search-form` | Search Form | `navigation` | `resources/views/admin/blocks/types/search-form.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `variant`; `settings.show_button` | Search route, site, and locale context | Not a container | `resources/views/pages/partials/blocks/search-form.blade.php` | Owns its `<form role="search">` root | `clear` | `SearchFormTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared button-display settings and translated labels are explicit. |
| `sidebar-footer` | Sidebar Footer | `navigation` | `resources/views/admin/blocks/types/sidebar-footer.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `settings.variant` | None | Not a container | `resources/views/pages/partials/blocks/sidebar-footer.blade.php` | Owns its inner footer block root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared variant with translated copy is straightforward. |

### Advanced

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `html` | HTML (Trusted) | `advanced` | `resources/views/admin/blocks/types/html.blade.php` | None | Canonical trusted HTML `content` | Public overlay and body-end registries can receive extracted fragments | Not a normal container | `resources/views/pages/partials/blocks/html.blade.php` | Owns a wrapper `<div>` around trusted markup and can also emit out-of-band overlay or body-end content | `needs review` | `PublicEditorialBlocksRenderingTest` | Renderer still appends children even though the block is not a normal container; trusted markup can also affect shared overlay or body-end output beyond the visible root. |

## Validation And Persistence Overview

Published block types do not each have their own dedicated request class today.

- `App\Http\Requests\Admin\BlockRequest` is the shared edit request path
- slug-specific branches inside that request normalize fields into current canonical storage
- `App\Support\Blocks\BlockPayloadWriter` persists the normalized block payload
- `App\Support\Blocks\BlockTranslationWriter` moves locale-owned fields into translation rows for registered families
- `App\Support\Blocks\BlockTranslationResolver` resolves translated or fallback values back onto a renderable block instance

That shared request path is one reason Phase 1 documents contracts first before any schema-driven edit work.

## Gaps And Backlog

Major current gaps found during the Phase 1 audit:

- `code` admin copy presents translated fields, but the block is not in `BlockTranslationRegistry`
- `table` admin copy presents translated fields, but the block is not in `BlockTranslationRegistry`
- `breadcrumb` form exposes shared settings, but `BlockRequest` currently clears `settings` for the slug, so those values do not persist
- `stat-card` admin form stores an optional URL, but the public renderer does not currently use it
- `link-list` intro fields are editable and translated, but the current public renderer outputs only child rows
- non-container renderers still append child blocks for `code`, `table`, `quote`, `toc`, and `html`
- `sticky-navbar` public root ownership is real in the renderer, but `Block::ownsPublicRoot()` does not currently reflect it
- `navbar-brand` public URL fallback is broader than the current default-locale request requirement
- `sidebar-brand` logo-only accessibility handling is weaker than `navbar-brand`
- `sidebar-nav-group` does not fully reuse `sidebar-nav-item` rendering helpers for nested items
- published and draft catalogs coexist, so future admin surfacing must stay explicit about published core contracts versus draft or install-specific rows

## Recommended Phase 2

Recommended next step for `Admin -> System -> Block Types`:

- add a read-only contract details view for each block type from shipped sources
- show catalog metadata, translation family, admin form source, public renderer source, and child support
- show whether the block owns translated fields, shared settings, direct media, ordered media, or relationship lookups
- surface current-status classification and known gaps directly in the admin
- keep the existing edit modal and update behavior unchanged for install-specific block types
- do not turn the Block Types screen into a dynamic block editor yet

## Recommended Phase 3

Recommended later standardization work for block groups:

- define stable product-owned block groups such as layout, content, navigation, pattern, and advanced in one source of truth
- align picker groupings, docs groupings, and Block Types admin groupings to that same source
- decide which currently draft or transitional blocks should become supported, archived, or explicitly legacy
- standardize which contracts are translation-backed versus intentionally shared-only before any schema-driven form work starts

## Phase 1 Summary

Phase 1 establishes the current published contract inventory without changing block editing behavior, block storage, or public rendering.

That is the intended stopping point for this release.
