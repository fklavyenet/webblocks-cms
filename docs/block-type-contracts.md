# Block Type Contracts

## Purpose And Scope

This document began as the Phase 1 inventory of the currently shipped published core block types in WebBlocks CMS, now also documents the Phase 2 read-only admin contract view, and records the Phase 3 gap-standardization fixes completed so far, including the Layout + Card cleanup for `section`, `container`, `grid`, `cluster`, `card`, and `content_header`.

Phase 1 is read-only documentation only.

- It does not redesign block forms.
- It does not add a DB-driven form builder.
- It does not migrate block content.
- It does not change public rendering.
- It does not change how `Admin -> System -> Block Types` edits work today.

The current Block Types admin screen remains a catalog and metadata screen. In Phase 2 it can now open a read-only Contract modal for each listed row, but it is still not a dynamic form builder or schema editor.

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

Published core block types currently documented here: `36`.

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

## Phase 2 Admin View

Phase 2 exposes contract details read-only in `Admin -> System -> Block Types`.

- each row can open a `Block Type Contract` modal
- the modal is informational only and does not submit updates
- it shows catalog, admin form, storage, translation, media or relationship, child, renderer, and gap details from shipped code
- it does not make Block Types admin a schema editor or form builder
- custom or draft block types can still open the modal, but may show `No shipped contract is documented for this block type yet.` when no core contract is defined

## Published Block Contract Matrix

### Content

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `header` | Header | `content` | `resources/views/admin/blocks/types/header.blade.php` | `title` via text translation rows | `variant` heading level; `settings.alignment`; shared anchor in `settings.anchor` with legacy `url` fallback | Same-page TOC reads anchored Header blocks | Not a container | `resources/views/pages/partials/blocks/header.blade.php` | Owns its root heading element | `clear` | `SyncCoreBlockTypesCommandTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Canonical heading block; shared anchor contract is clear but still has legacy `url` fallback behavior. |
| `plain_text` | Plain Text | `content` | `resources/views/admin/blocks/types/plain_text.blade.php` | `content` via text translation rows | `settings.alignment` | None | Not a container | `resources/views/pages/partials/blocks/plain_text.blade.php` | Owns its root `<p>` | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Simple translated body-copy primitive. |
| `rich-text` | Rich Text | `content` | `resources/views/admin/blocks/types/rich-text.blade.php` | `content` via text translation rows | None | None | Not a container | `resources/views/pages/partials/blocks/rich-text.blade.php` | Owns its `.wb-rich-text` root when content exists | `clear` | `RichTextBlockTest`, `PublicRichContentTest`, `BlockTranslationIntegrityTest` | Safe HTML storage and translation ownership are clear. |
| `code` | Code | `content` | `resources/views/admin/blocks/types/code.blade.php` | `title`, `subtitle`, `content` via text translation rows | `settings.language` | None | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/code.blade.php` | Owns its `<pre><code>` root | `clear` | `PublicRichContentTest`, `BlockTypePhaseThreeContractsTest`, `PageBuilderExperienceTest` | Phase 3 aligns translated code title, label, and snippet body with the existing text-translation architecture and now ignores arbitrary historical child trees in public output. |
| `button_link` | Button Link | `content` | `resources/views/admin/blocks/types/button_link.blade.php` | `title` label via text translation rows | `settings.url`; `settings.target`; shared `variant` | None | Not a container | `resources/views/pages/partials/blocks/button_link.blade.php` | Owns its `<a.wb-btn>` root | `clear` | `PublicEditorialBlocksRenderingTest` | Shared URL and target with translated label are consistent. |
| `card` | Card | `content` | `resources/views/admin/blocks/types/card.blade.php` | `eyebrow`, `title`, `subtitle`, `content`, `meta` via text translation rows | `settings.url`; `settings.target`; `settings.variant` | Child footer actions are nested blocks; legacy single-action fallback uses translated `meta` | Container; allowed children are `cluster` and `button_link` | `resources/views/pages/partials/blocks/card.blade.php` | Owns its root card element | `transitional` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest`, `BlockTranslationIntegrityTest` | Phase 3 keeps Card as a transitional content/container block: translated visible copy, shared URL/target/variant, nested child action composition, and the legacy single-action fallback all remain intentional. |
| `stat-card` | Stat Card | `content` | `resources/views/admin/blocks/types/stat-card.blade.php` | `title`, `subtitle`, `content` via text translation rows | Canonical `url` remains shared on the block row | None | Not a container | `resources/views/pages/partials/blocks/stat-card.blade.php` | Owns its stat-card root | `clear` | `StatCardTest`, `BlockTranslationIntegrityTest` | Phase 3 keeps the existing optional URL field and now renders a simple public link when present. |
| `image` | Image | `content` | `resources/views/admin/blocks/types/image.blade.php` | `caption`, `alt text` via image translation rows | Shared `media_id`; shared canonical `url` | Direct image media relation via `media_id` | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/image.blade.php` | Owns its semantic `<figure>` root when media exists | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest`, `BlockTranslationIntegrityTest` | Phase 3 aligns Image with the existing image-translation architecture so caption and alt text are locale-owned while selected media and optional link URL remain shared. |
| `gallery` | Gallery | `content` | `resources/views/admin/blocks/types/gallery.blade.php` | `title`, `subtitle` via text translation rows | Ordered `block_media` gallery-item relations | Ordered `block_media` rows with role `gallery_item`; legacy settings fallbacks remain readable | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/gallery.blade.php` | Owns its gallery wrapper root and registers one viewer modal under `#wb-overlay-root` | `transitional` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest`, `PublicEditorialBlocksRenderingTest` | Phase 3 aligns editor save behavior with ordered `block_media` ownership while intentionally keeping legacy settings-based gallery item fallbacks readable for older saved content. |
| `download` | Download | `content` | `resources/views/admin/blocks/types/download.blade.php` | `title`, `subtitle` via text translation rows | Shared `media_id`; shared `variant` | Direct download media relation via `media_id` | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/download.blade.php` | Owns its CTA wrapper root when media exists | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 keeps the selected download media and button variant shared while moving visible label and helper copy into the existing text-translation path. |
| `file` | File | `content` | `resources/views/admin/blocks/types/file.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/file.blade.php` | Owns its file-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 productizes the shipped File renderer with a dedicated admin form and keeps shared file-source ownership explicit between Media and external URL fallback. |
| `video` | Video | `content` | `resources/views/admin/blocks/types/video.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with safe external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/video.blade.php` | Owns its video-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 adds the missing admin form and save path so uploaded video media, safe external provider URLs, translated visible copy, and public fallback behavior now describe the same contract. |
| `audio` | Audio | `content` | `resources/views/admin/blocks/types/audio.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/audio.blade.php` | Owns its audio-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 adds the missing admin form and save path so uploaded audio media, translated visible copy, and safe no-empty-controls rendering now match the documented contract. |
| `table` | Table | `content` | `resources/views/admin/blocks/types/table.blade.php` | `title`, `content` via text translation rows | Shared `variant`; renderer also checks `settings.rows` fallback | None | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/table.blade.php` | Owns its table wrapper root | `mostly clear` | `PublicRichContentTest`, `BlockTypePhaseThreeContractsTest`, `PageBuilderExperienceTest` | Phase 3 aligns translated title and row-copy ownership with the existing text-translation architecture and now ignores arbitrary historical child trees in public output; the legacy `settings.rows` fallback path remains documented. |
| `quote` | Quote | `content` | `resources/views/admin/blocks/types/quote.blade.php` | None in the registry today | Canonical `title`, `subtitle`, `content`, `variant` | None | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/quote.blade.php` | Owns its quote root | `clear` | `PublicRichContentTest`, `PageBuilderExperienceTest` | Phase 3 stops treating Quote as a layout/container wrapper in public output while preserving existing saved child rows in admin block trees. |

### Layout

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `section` | Section | `layout` | `resources/views/admin/blocks/types/section.blade.php` | None | `settings.layout_name`; `settings.spacing` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/section.blade.php` | Owns its root `<section>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 keeps Section layout-only: shared layout settings stay canonical, it owns the semantic `<section>` root, and it does not move user-facing copy into arbitrary settings. |
| `container` | Container | `layout` | `resources/views/admin/blocks/types/container.blade.php` | None | `settings.layout_name`; `settings.width`; `settings.flow` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/container.blade.php` | Owns its root container `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Legacy default still falls back to stacked flow when unset, while explicit `Flow: None` remains the layout-neutral composition path. |
| `cluster` | Cluster | `layout` | `resources/views/admin/blocks/types/cluster.blade.php` | None | `settings.layout_name`; `settings.gap`; `settings.alignment`; `settings.items_alignment`; `settings.wrap`; `settings.width` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/cluster.blade.php` | Owns its root cluster `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Shared layout settings stay explicit and renderer-owned, including the existing navbar-compatible full-width, between, center, nowrap composition path without adding navbar-specific logic. |
| `grid` | Grid | `layout` | `resources/views/admin/blocks/types/grid.blade.php` | None | `settings.layout_name`; `settings.columns`; `settings.gap` | Child blocks only | Container; no explicit child whitelist | `resources/views/pages/partials/blocks/grid.blade.php` | Owns its root grid `<div>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Shared grid settings stay canonical, and public output remains limited to shipped `wb-grid-*` and supported `wb-gap-*` class mappings. |

### Pattern

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `content_header` | Content Header | `pattern` | `resources/views/admin/blocks/types/content_header.blade.php` | `title`, `subtitle`, `meta` via text translation rows | Shared `variant` heading level; `settings.alignment` | `meta` is stored as structured list content | Not a container | `resources/views/pages/partials/blocks/content_header.blade.php` | Owns its root `<header>` | `clear` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest`, `BlockTranslationIntegrityTest` | Phase 3 keeps Content Header semantic and locale-owned for visible heading, intro, and meta copy, while heading level and alignment remain shared. |
| `alert` | Alert | `pattern` | `resources/views/admin/blocks/types/alert.blade.php` | `title`, `content` via text translation rows | `settings.variant` | None | Not a container | `resources/views/pages/partials/blocks/alert.blade.php` | Owns its alert root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared variant and translated copy line up cleanly. |

### Navigation

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `link-list` | Link List | `navigation` | `resources/views/admin/blocks/types/link-list.blade.php` | `title`, `subtitle`, `content` via text translation rows | None | Child `link-list-item` blocks | Container; only `link-list-item` children | `resources/views/pages/partials/blocks/link-list.blade.php` | Owns its `.wb-link-list` root when child rows exist | `clear` | `LinkListBlockTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 now renders the existing translated intro copy above the public link list without changing child item behavior. |
| `link-list-item` | Link List Item | `navigation` | `resources/views/admin/blocks/types/link-list-item.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `url` | Parent `link-list` relationship | Not a container | `resources/views/pages/partials/blocks/link-list-item.blade.php` | Owns its row link root | `clear` | `LinkListBlockTest`, `BlockTranslationIntegrityTest` | Shared URL with translated row copy is consistent. |
| `toc` | TOC | `navigation` | `resources/views/admin/blocks/types/toc.blade.php` | None | Canonical `title` only | Same-page published `header` blocks with valid anchors | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/toc.blade.php` | Owns its generated TOC wrapper when headings exist | `clear` | `PublicRichContentTest`, `PageBuilderExperienceTest` | Phase 3 keeps TOC focused on discovered page headings only and no longer treats arbitrary child blocks as public TOC content. |
| `breadcrumb` | Breadcrumb | `navigation` | `resources/views/admin/blocks/types/breadcrumb.blade.php` | None | Shared `settings.home_label`; `settings.include_current` | Current page, site, and locale breadcrumb context | Not a container | `resources/views/pages/partials/blocks/breadcrumb.blade.php` | Owns its `<nav.wb-breadcrumb>` root | `clear` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest` | Phase 3 preserves the exposed breadcrumb settings through the shared BlockRequest path so form behavior now matches persistence and rendering. |
| `header-actions` | Header Actions | `navigation` | `resources/views/admin/blocks/types/header-actions.blade.php` | None | Shared `settings.show_mode_toggle`; `settings.show_accent_toggle`; `settings.show_search` | Search route plus client-side UI hooks | Not a container | `resources/views/pages/partials/blocks/header-actions.blade.php` | Owns its inner action cluster only | `clear` | `PublicEditorialBlocksRenderingTest` | System utility block; no translation ownership by design. |
| `sticky-navbar` | Navbar | `navigation` | `resources/views/admin/blocks/types/sticky-navbar.blade.php` | None | `settings.layout_name`; `settings.sticky_mode` | Nested navbar child blocks | Container; allowed children are `container`, `cluster`, `header`, `plain_text`, `rich-text`, `button_link`, `navbar-brand`, `navbar-navigation`, `header-actions`, `search-form` | `resources/views/pages/partials/blocks/sticky-navbar.blade.php` | Public renderer owns the outer `<nav.wb-navbar>` root | `clear` | `PublicEditorialBlocksRenderingTest`, `PublicLayoutStructureTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 aligns the persisted `sticky-navbar` slug with `Block::ownsPublicRoot()` so Navbar no longer receives an extra generic public block wrapper. |
| `navbar-brand` | Navbar Brand | `navigation` | `resources/views/admin/blocks/types/navbar-brand.blade.php` | `title`, `subtitle` via text translation rows | Shared `settings.url`; `settings.target`; `settings.aria_label` | Shared logo media via `media_id`; site home URL fallback | Not a container | `resources/views/pages/partials/blocks/navbar-brand.blade.php` | Owns the inner brand link only | `clear` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTranslationIntegrityTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 aligns admin save behavior with the shipped renderer contract: explicit saved URL wins, otherwise the current site home path is used when available, then `/` as the final safe fallback. |
| `navbar-navigation` | Navbar Navigation | `navigation` | `resources/views/admin/blocks/types/navbar-navigation.blade.php` | None | Canonical `title` as shared ARIA label; `settings.menu_key` | Shared `NavigationItem` menu tree | Not a container | `resources/views/pages/partials/blocks/navbar-navigation.blade.php` | Owns the inner navigation wrapper only | `clear` | `PublicEditorialBlocksRenderingTest` | Shared menu binding and ARIA label are current product-owned behavior. |
| `sidebar-brand` | Sidebar Brand | `navigation` | `resources/views/admin/blocks/types/sidebar-brand.blade.php` | `title`, `subtitle` via text translation rows | Shared `settings.url`; `settings.target`; `settings.aria_label` | Shared logo media via `media_id`; site home URL fallback | Not a container | `resources/views/pages/partials/blocks/sidebar-brand.blade.php` | Owns the inner brand link only | `clear` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTranslationIntegrityTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 gives Sidebar Brand the same logo-only accessible-name fallback order as Navbar Brand and the same conservative shared URL fallback contract. |
| `sidebar-navigation` | Sidebar Navigation | `navigation` | `resources/views/admin/blocks/types/sidebar-navigation.blade.php` | `title` via text translation rows | Shared `settings.menu_key`; `settings.layout_name`; `settings.show_icons`; `settings.active_matching` | Either CMS `NavigationItem` tree or manual child blocks | Container; only `sidebar-nav-item` and `sidebar-nav-group` children | `resources/views/pages/partials/blocks/sidebar-navigation.blade.php` | Owns its `<nav.wb-sidebar-nav>` root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared menu-mode settings and manual child mode are both explicit. |
| `sidebar-nav-item` | Sidebar Nav Item | `navigation` | `resources/views/admin/blocks/types/sidebar-nav-item.blade.php` | `title` via text translation rows | Shared `settings.url`; `settings.target`; `settings.icon`; `settings.active_mode`; `settings.manual_active` | Shared icon catalog slug; parent sidebar relationship | Not a container | `resources/views/pages/partials/blocks/sidebar-nav-item.blade.php` | Owns its sidebar link root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared link behavior plus translated label line up well. |
| `sidebar-nav-group` | Sidebar Nav Group | `navigation` | `resources/views/admin/blocks/types/sidebar-nav-group.blade.php` | `title` via text translation rows | Shared `settings.icon`; `settings.initially_open`; `settings.layout_name` | Child `sidebar-nav-item` blocks; icon catalog slug | Container; only `sidebar-nav-item` children | `resources/views/pages/partials/blocks/sidebar-nav-group.blade.php` | Owns its `.wb-nav-group` root | `clear` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTranslationIntegrityTest`, `BlockTypePhaseThreeContractsTest` | Phase 3 keeps the shipped WebBlocks UI nav-group wrapper contract while nested manual child links now reuse the same sidebar item semantics for href, target, icon, and active-state output. |
| `search-form` | Search Form | `navigation` | `resources/views/admin/blocks/types/search-form.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `variant`; `settings.show_button` | Search route, site, and locale context | Not a container | `resources/views/pages/partials/blocks/search-form.blade.php` | Owns its `<form role="search">` root | `clear` | `SearchFormTest`, `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared button-display settings and translated labels are explicit. |
| `sidebar-footer` | Sidebar Footer | `navigation` | `resources/views/admin/blocks/types/sidebar-footer.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `settings.variant` | None | Not a container | `resources/views/pages/partials/blocks/sidebar-footer.blade.php` | Owns its inner footer block root | `clear` | `PublicEditorialBlocksRenderingTest`, `BlockTranslationIntegrityTest` | Shared variant with translated copy is straightforward. |

### Advanced

| Slug | Label | Category | Admin form source | Translatable fields | Shared/settings fields | Media/relationship fields | Child/container behavior | Public renderer source | Renderer root contract | Current status | Tests / coverage | Known gaps / notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `html` | HTML (Trusted) | `advanced` | `resources/views/admin/blocks/types/html.blade.php` | None | Canonical trusted HTML `content` | Public overlay and body-end registries can receive extracted fragments | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/html.blade.php` | Owns a wrapper `<div>` around trusted markup and can also emit out-of-band overlay or body-end content | `mostly clear` | `PublicEditorialBlocksRenderingTest`, `PublicRichContentTest`, `PageBuilderExperienceTest` | Phase 3 stops treating Trusted HTML as a public child-container wrapper. Trusted markup can still affect shared overlay or body-end output beyond the visible root. |

## Validation And Persistence Overview

Published block types do not each have their own dedicated request class today.

- `App\Http\Requests\Admin\BlockRequest` is the shared edit request path
- slug-specific branches inside that request normalize fields into current canonical storage
- `App\Support\Blocks\BlockPayloadWriter` persists the normalized block payload
- `App\Support\Blocks\BlockTranslationWriter` moves locale-owned fields into translation rows for registered families
- `App\Support\Blocks\BlockTranslationResolver` resolves translated or fallback values back onto a renderable block instance

That shared request path is one reason Phase 1 documents contracts first before any schema-driven edit work.

## Gaps And Backlog

Current remaining high-signal gaps after the current Phase 3 fixes:

- `gallery` still preserves a legacy settings-based fallback item path when canonical ordered `block_media` rows are missing
- `table` renderer still supports a legacy `settings.rows` fallback path even though the core admin form writes translated row copy
- published and draft catalogs coexist, so future admin surfacing must stay explicit about published core contracts versus draft or install-specific rows

## Recommended Phase 3

Recommended later standardization work for block groups:

- define stable product-owned block groups such as layout, content, navigation, pattern, and advanced in one source of truth
- align picker groupings, docs groupings, and Block Types admin groupings to that same source
- decide which currently draft or transitional blocks should become supported, archived, or explicitly legacy
- standardize which contracts are translation-backed versus intentionally shared-only before any schema-driven form work starts

## Phase 1 Summary

Phase 1 establishes the current published contract inventory without changing block editing behavior, block storage, or public rendering.

That was the intended stopping point for the Phase 1 release.

## Phase 2 Summary

Phase 2 makes the documented contract visible in the Block Types admin as read-only information while keeping block edit behavior, storage, renderers, and picker behavior unchanged.

## Phase 3 Summary

Phase 3 starts resolving low-risk documented contract gaps without adding a schema editor, dynamic form builder, or DB-driven block form system.

- `code` now follows the existing text-translation path for title, label, and snippet body while keeping syntax language shared
- `table` now follows the existing text-translation path for title and row copy while keeping table style shared
- `breadcrumb` now preserves the exposed shared settings on save
- `stat-card` now uses the existing optional URL in the public renderer with a simple safe link
- `link-list` now renders the existing translated intro copy above the child item list
- `sticky-navbar` now aligns persisted Navbar root ownership with `Block::ownsPublicRoot()` so the public shell does not add an extra generic wrapper
- `image` now follows the existing image-translation path for caption and alt text while keeping selected media and optional link URL shared
- `gallery` now follows the existing text-translation path for visible title and description copy while writing canonical ordered gallery media to `block_media` and preserving legacy fallback items for old content
- `download` now follows the existing text-translation path for visible label and helper copy while keeping selected media and button variant shared
- `file`, `video`, and `audio` now have first-class admin forms and request normalization so translated visible copy and shared media or URL sources round-trip through the same contract they already render publicly
- `image`, `gallery`, `download`, `file`, `video`, and `audio` no longer accept new arbitrary child placement and no longer render arbitrary historical child trees publicly, while existing child rows remain preserved in admin block trees
- `code`, `table`, `quote`, `toc`, and `html` no longer accept new normal child placement and no longer render arbitrary historical child trees publicly, while existing child rows remain preserved in admin block trees
- `navbar-brand` and `sidebar-brand` now share a conservative saved-URL or site-home fallback contract, and both preserve safe accessible naming for logo-only output without forcing visible text
- `sidebar-nav-group` now reuses the same manual sidebar item output semantics as `sidebar-nav-item` for nested child links while preserving the existing WebBlocks UI nav-group wrapper contract
- `section`, `container`, `grid`, `cluster`, `card`, and `content_header` now use the same shipped contract source across the registry, read-only admin contract modal, audit output, docs, and focused regression coverage
- layout primitives keep shared layout settings and child structure on canonical block storage instead of translation rows or arbitrary copy fields
- `card` keeps its intentional transitional contract: translated visible copy, shared URL/target/variant, nested child action composition, and the legacy single-action fallback all remain documented and tested
- `content_header` keeps translated title, intro, and meta copy with shared heading level and alignment, and its semantic `<header class="wb-content-header">` root remains renderer-owned without a generic wrapper
