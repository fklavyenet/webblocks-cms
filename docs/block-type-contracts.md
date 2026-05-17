# Block Type Contracts

## Purpose And Scope

This document began as the Phase 1 inventory of the currently shipped published core block types in WebBlocks CMS, now also documents the Phase 2 read-only admin contract view, and records the Phase 3 gap-standardization fixes completed so far, including the Layout + Card cleanup for `section`, `container`, `grid`, `cluster`, `card`, and `content_header`, the Marketing / Structured Content cleanup for `hero`, `columns`, `column_item`, `cta`, `feature-grid`, and `feature-item`, and the Legacy / Transitional cleanup that keeps old compatibility slugs documented honestly without promoting them into the published core catalog.

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

Published core block types currently documented here: `42`.

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
| `card` | Card | `content` | `resources/views/admin/blocks/types/card.blade.php` | `eyebrow`, `title`, `subtitle`, `content`, `meta` via text translation rows; `image_alt`, `image_caption` via image translation rows | Shared `media_id`; `settings.url`; `settings.target`; `settings.variant`; `settings.image_position`; `settings.image_align`; `settings.image_aspect` | Direct image media relation via `media_id`; child footer actions remain nested blocks and the legacy single-action fallback still uses translated `meta` | Container; allowed children are `cluster` and `button_link` | `resources/views/pages/partials/blocks/card.blade.php` | Owns its root card element | `transitional` | `PublicEditorialBlocksRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest`, `BlockTranslationIntegrityTest`, `MediaVisualBlockContractsTest` | Card image visibility is now controlled by selected media instead of a separate no-image placement choice. When media exists, blank, missing, or legacy `none` placement values fall back safely to `top`, the image figure renders inside `.wb-card-body`, the figure consumes WebBlocks UI `wb-card-media` plus alignment and aspect modifiers, default image fit stays non-cropping through the shipped UI media frame behavior, no-image cards remain valid, child Cluster or Button Link actions stay supported, the legacy single-action fallback remains intentional, and split left or right media layouts are still out of scope for this patch. |
| `stat-card` | Stat Card | `content` | `resources/views/admin/blocks/types/stat-card.blade.php` | `title`, `subtitle`, `content` via text translation rows | Canonical `url` remains shared on the block row | None | Not a container | `resources/views/pages/partials/blocks/stat-card.blade.php` | Owns its stat-card root | `clear` | `StatCardTest`, `BlockTranslationIntegrityTest` | Phase 3 keeps the existing optional URL field and now renders a simple public link when present. |
| `image` | Image | `content` | `resources/views/admin/blocks/types/image.blade.php` | `caption`, `alt text` via image translation rows | Shared `media_id`; shared canonical `url` | Direct image media relation via `media_id` | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/image.blade.php` | Owns its semantic `<figure>` root when media exists | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest`, `BlockTranslationIntegrityTest` | Phase 3 aligns Image with the existing image-translation architecture so caption and alt text are locale-owned while selected media and optional link URL remain shared. |
| `gallery` | Gallery | `content` | `resources/views/admin/blocks/types/gallery.blade.php` plus `resources/views/admin/blocks/types/partials/gallery-items-editor.blade.php` | Per-gallery-item `alt_text`, `caption`, `overlay_title`, and `overlay_text` via `block_gallery_item_translations` | Shared gallery presentation settings plus ordered `block_media` gallery-item relations | Ordered `block_media` rows with role `gallery_item`; locale-owned gallery item copy lives on `block_gallery_item_translations`; legacy stored block `title`/`subtitle` values may still exist but are ignored by public rendering | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/gallery.blade.php` | Owns its gallery wrapper root and registers one viewer modal under `#wb-overlay-root` when lightbox is enabled | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest`, `PageBuilderExperienceTest`, `SiteCloneServiceTest`, `SiteExportImportTest`, `SitePromotionTest`, `ReconstructionIntegrityTest`, `SharedSlotRevisionTest` | Gallery is now a media-collection block. The normal admin form uses a compact list-row editor instead of the older selected-assets grid. Its nested `Add Gallery Items` picker stays on the shared admin `#wb-overlay-root` contract so WebBlocks UI owns the stacked modal lifecycle, and long compact result lists keep natural row height while the modal body remains the scroll container. Gallery no longer owns intro heading or paragraph output; editors should place `content_header` plus `plain_text` or `rich-text` before Gallery when section copy is needed. Legacy settings-based fallback items remain readable for older content. |
| `download` | Download | `content` | `resources/views/admin/blocks/types/download.blade.php` | `title`, `subtitle` via text translation rows | Shared `media_id`; shared `variant` | Direct download media relation via `media_id` | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/download.blade.php` | Owns its CTA wrapper root when media exists | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 keeps the selected download media and button variant shared while moving visible label and helper copy into the existing text-translation path. |
| `file` | File | `content` | `resources/views/admin/blocks/types/file.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/file.blade.php` | Owns its file-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 productizes the shipped File renderer with a dedicated admin form and keeps shared file-source ownership explicit between Media and external URL fallback. |
| `video` | Video | `content` | `resources/views/admin/blocks/types/video.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with safe external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/video.blade.php` | Owns its video-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 adds the missing admin form and save path so uploaded video media, safe external provider URLs, translated visible copy, and public fallback behavior now describe the same contract. |
| `audio` | Audio | `content` | `resources/views/admin/blocks/types/audio.blade.php` | `title`, `content` via text translation rows | Shared `media_id`; shared canonical `url` | Direct media relation via `media_id` with external URL fallback | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/audio.blade.php` | Owns its audio-card root | `clear` | `MediaVisualBlockContractsTest`, `PublicMediaBlocksTest` | Phase 3 adds the missing admin form and save path so uploaded audio media, translated visible copy, and safe no-empty-controls rendering now match the documented contract. |
| `table` | Table | `content` | `resources/views/admin/blocks/types/table.blade.php` | `title`, `content` via text translation rows | Shared `variant`; renderer also checks `settings.rows` fallback | None | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/table.blade.php` | Owns its table wrapper root | `mostly clear` | `PublicRichContentTest`, `BlockTypePhaseThreeContractsTest`, `PageBuilderExperienceTest` | Phase 3 aligns translated title and row-copy ownership with the existing text-translation architecture and now ignores arbitrary historical child trees in public output; the legacy `settings.rows` fallback path remains documented. |
| `quote` | Quote | `content` | `resources/views/admin/blocks/types/quote.blade.php` | None in the registry today | Canonical `title`, `subtitle`, `content`, `variant` | None | Not a container; historical child rows are preserved but new child placement is not supported | `resources/views/pages/partials/blocks/quote.blade.php` | Owns its quote root | `clear` | `PublicRichContentTest`, `PageBuilderExperienceTest` | Phase 3 stops treating Quote as a layout/container wrapper in public output while preserving existing saved child rows in admin block trees. |
| `hero` | Hero | `content` | `resources/views/admin/blocks/types/hero.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `variant`; `settings.layout`; `settings.title_tag` | Child `button` blocks for managed CTAs | Container; only `button` children | `resources/views/pages/partials/blocks/hero.blade.php` | Owns its promo `<section>` root | `transitional` | `PublicHeroBlockRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Hero is now a published source-backed core contract. Intro copy is locale-owned, CTA labels are translated on child buttons, and CTA URLs remain shared. Legacy content fallbacks remain readable where canonical translated fields are empty. |
| `columns` | Columns | `content` | `resources/views/admin/blocks/types/columns.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `variant` | Child `column_item` blocks | Container; only `column_item` children | `resources/views/pages/partials/blocks/columns.blade.php` | Owns its structured-content `<section>` root | `clear` | `PublicColumnsRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Columns is now a published first-class structured content block. Intro copy is locale-owned while variant, child order, and child URLs stay shared. |
| `column_item` | Column Item | `content` | `resources/views/admin/blocks/types/column_item.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared canonical `url` | Parent `columns` relationship | Not a container | `resources/views/pages/partials/blocks/column_item.blade.php` | Parent-driven item root varies by Columns variant | `clear` | `PublicColumnsRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Column Item is now a published supporting child contract for Columns. Locale edits update item copy without overwriting shared URLs. |
| `cta` | CTA | `content` | `resources/views/admin/blocks/types/cta.blade.php` | `title`, `subtitle`, `content` via text translation rows | Shared `variant` | Child `button` blocks for managed CTAs | Container; only `button` children | `resources/views/pages/partials/blocks/cta.blade.php` | Owns its promo `<section>` root | `clear` | `PublicHeroBlockRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | CTA is now a published promo contract with locale-owned copy and CTA labels, shared CTA URLs, and a root-owning public renderer. |
| `feature-grid` | Feature Grid | `content` | `resources/views/admin/blocks/types/feature-grid.blade.php` | `title`, `subtitle`, `content` via text translation rows | None beyond shared child structure | Child `feature-item` blocks with legacy-compatible `column_item` support | Container; allowed children are `feature-item` and `column_item` | `resources/views/pages/partials/blocks/feature-grid.blade.php` | Delegates to the Columns cards presentation and still uses the generic public wrapper | `transitional` | `PublicHeroBlockRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Feature Grid is now a published compatibility alias because it has real shipped admin and render paths, but it still intentionally delegates to the Columns cards contract. |
| `feature-item` | Feature Item | `content` | `resources/views/admin/blocks/types/feature-item.blade.php` | `title`, `content` via text translation rows | Shared canonical `url` | Parent `feature-grid` relationship | Not a container | `resources/views/pages/partials/blocks/feature-item.blade.php` | Delegates to the Column Item cards presentation | `transitional` | `PublicHeroBlockRenderingTest`, `PageBuilderExperienceTest`, `BlockTypePhaseThreeContractsTest` | Feature Item is now a published supporting child contract for Feature Grid while still sharing the existing Column Item card shell. |

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
- `hero` still preserves legacy field fallbacks when canonical translated intro fields are empty
- `feature-grid` and `feature-item` are now published because they are source-backed, but they remain intentionally transitional delegate contracts over the shared Columns or Column Item presentation paths
- `tabs`, `slider`, `menu`, and `faq-list` still exist as legacy draft catalog rows with compatibility forms or renderers, but they are not published core contracts and should continue to fail safely in the contract modal and audit output
- `showcase-list` and `contact-info` still exist only as public-render compatibility paths rather than shipped published core catalog blocks; their settings-driven links now follow the same safe public URL rules as other block renderers
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
- `gallery` now uses locale-owned `block_gallery_item_translations` for per-item alt text, caption, overlay title, and overlay text while keeping ordered gallery media and presentation settings shared, preserving legacy fallback items for old content, and excluding legacy gallery title/description from normal editing and public output
- `download` now follows the existing text-translation path for visible label and helper copy while keeping selected media and button variant shared
- `file`, `video`, and `audio` now have first-class admin forms and request normalization so translated visible copy and shared media or URL sources round-trip through the same contract they already render publicly
- `image`, `gallery`, `download`, `file`, `video`, and `audio` no longer accept new arbitrary child placement and no longer render arbitrary historical child trees publicly, while existing child rows remain preserved in admin block trees
- `code`, `table`, `quote`, `toc`, and `html` no longer accept new normal child placement and no longer render arbitrary historical child trees publicly, while existing child rows remain preserved in admin block trees
- `navbar-brand` and `sidebar-brand` now share a conservative saved-URL or site-home fallback contract, and both preserve safe accessible naming for logo-only output without forcing visible text
- `sidebar-nav-group` now reuses the same manual sidebar item output semantics as `sidebar-nav-item` for nested child links while preserving the existing WebBlocks UI nav-group wrapper contract
- `section`, `container`, `grid`, `cluster`, `card`, and `content_header` now use the same shipped contract source across the registry, read-only admin contract modal, audit output, docs, and focused regression coverage
- `hero`, `columns`, `column_item`, `cta`, `feature-grid`, and `feature-item` are now published in the shipped core catalog, documented in the shared contract registry, and covered as source-backed marketing or structured content contracts instead of remaining draft-only or underdocumented
- locale-only edits for managed CTA buttons and structured child items now preserve shared URLs while still updating translated labels or copy
- layout primitives keep shared layout settings and child structure on canonical block storage instead of translation rows or arbitrary copy fields
- `card` keeps its intentional transitional contract: translated visible copy stays in text rows, optional image alt/caption use the existing image-translation rows, shared `media_id` plus shared URL/target/variant/image presentation settings stay canonical, selected media is now enough to render the image, blank or legacy `none` image placement values fall back safely to `top`, the figure renders inside `.wb-card-body`, `image_align` and `image_aspect` map to WebBlocks UI `wb-card-media` modifiers, nested child action composition remains intact, and the legacy single-action fallback plus existing no-image rendering both remain documented and tested
- `content_header` keeps translated title, intro, and meta copy with shared heading level and alignment, and its semantic `<header class="wb-content-header">` root remains renderer-owned without a generic wrapper
- `testimonial` and `stats` remain documented honestly as alias-only behavior that delegates to existing Quote or Columns render paths rather than as standalone published core contracts
- `tabs`, `slider`, `menu`, and `faq-list` remain draft or alias-era compatibility slugs rather than published core contracts, while `showcase-list` and `contact-info` remain public-only compatibility renderers with no shipped core contract entry
