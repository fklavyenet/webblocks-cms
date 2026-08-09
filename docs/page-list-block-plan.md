# Page List Block Plan

This document records the design for the `page-list` block: the first WebBlocks CMS block whose visible content is **derived from a page query** rather than authored by an editor.

**Status: implemented.** Phase 1 as described below has shipped, including the three resolved open questions at the end. Phase 2 shipped by halves: `list_excerpt` is now a real per-locale column on `page_translations` and takes precedence over the SEO description, while the thumbnail still reads the Open Graph image and `list_image_media_id` remains unbuilt. It landed as a column rather than the page setting this plan first sketched — a page setting is a single JSON blob on `pages`, and a card sentence has to differ per locale like every other visible string. It follows the plan format used by [the forms plugin plan](forms-plugin-plan.md), including its rule that a corrected assumption is recorded rather than quietly replaced.

## Why This Block

[The user guides plan](user-guides-plan.md) had to state it plainly: WebBlocks CMS has no collection, query, or archive block. There is nothing that renders "all pages of type X" on the public site. It closed with the honest shape for the missing feature — a dedicated block type scoped by page type, site, and locale — and deferred it as out of scope for a docs series.

That deferral is now the ask. This plan makes the shape concrete.

Two existing blocks sound like they already do this and do not:

- `navigation-auto` (`source_type: navigation`, system) renders a menu the editor built by hand in the Navigation tree. It is a query over `NavigationItem`, not over pages.
- `toc` derives its list at render time — from `header` blocks in the same slot, not from pages.

`toc` is nonetheless the closest architectural precedent: a system block, not a container, no editorial copy, list computed in a model helper (`Block::publicTocHeadingBlocks()`) and rendered from a thin Blade view. `page-list` follows exactly that split.

## What Already Exists To Build On

Everything the block needs to resolve a page into a card exists today. No new column is required for phase 1.

| Need | Existing source |
| --- | --- |
| Which pages | `pages.page_type_id` → `page_types.slug`, plus `pages.status`, `pages.site_id`, `pages.published_at` |
| Hierarchy | None on `pages`. `page_translations.path` is the only tree, so a subtree is a path prefix |
| Per-locale title | `page_translations.name`, falling back to `pages.title` |
| Per-locale URL | `Page::publicPath($localeCode)` → `PageRouteResolver::pathFor()` |
| Per-locale description | `page_translations.list_excerpt`, falling back to `seo_description` (the excerpt column was added in phase 2; phase 1 used the SEO description alone) |
| Thumbnail | `page_translations.og_image_media_id` |
| Render context | `Block::renderSite()`, `Block::renderLocaleCode()`, `Block::renderPage()` |

Two of these are compromises worth naming up front rather than discovering during implementation:

**`seo_description` is doing double duty.** It is written for search engines and social cards, and an editor who tuned it for Google may not want it verbatim under a card title. It was the only per-locale prose that existed per page, so phase 1 used it and truncated it. Phase 2 added `page_translations.list_excerpt` above it in a fallback chain: an authored excerpt renders whole (it is capped at 300 characters on the way in, and cutting a sentence somebody wrote for this exact card is worse than a taller card), a borrowed SEO description is still trimmed to 160.

A third tier — deriving the opening prose from the page's own block tree — was considered and not built. The machinery looks like it already exists: `wbcms_public_search_index` stores per-locale derived text with an `excerpt` column, maintained on every block save. It is the wrong text. `PublicSearchIndexer::buildContent()` deliberately concatenates the title, slug, path and every block's fields into one haystack, so a real stored excerpt reads `QuizTem quiztem /products/quiztem QuizTem product highlights fklavye-product-slider:quiztem ...`. Correct for matching, unusable as prose. Deriving a readable excerpt means walking the tree for the first genuine paragraph block and deciding what counts as one — a contract worth writing deliberately, not by reusing the search index.

**`og_image_media_id` is a share image, not a thumbnail.** Its natural aspect ratio is 1.91:1 for Open Graph, which is not a card thumbnail ratio. It renders acceptably inside a card header, but it is the wrong long-term source.

Phase 2 fixed the first of these. `list_excerpt` became a nullable `text` column on `page_translations` — not the page setting sketched here, because `pages.settings` is one JSON blob for the whole page and a card sentence has to differ per locale. The cost was the predictable one: a translated column has to be carried by every surface that copies a translation, so page duplicate, site move, revisions, site clone, export, import, promotion, JSON import, the translation form, and the Internal Content API all had to learn the field, or it would vanish silently on the first clone.

The thumbnail half is still open. `list_image_media_id` would need the same treatment plus a media picker, and the Open Graph image renders acceptably through the `card` transform in the meantime.

## Block Contract

| Field | Value |
| --- | --- |
| Slug | `page-list` |
| Name | Page List |
| Category | `content` |
| `source_type` | `pages` (new value; today's catalog has `static`, `navigation`, `form`, `engagement`) |
| `is_system` | `true` |
| `is_container` | `false` |
| `status` | `published` |
| Translation family | none — like `navigation-auto`, `translation_state` is `shared` |

`is_system: true` is the load-bearing flag. It is what tells the admin that editorial content fields are unused, and it is the same flag `navigation-auto`, `toc`, `rating` and `comments` carry.

The block is **not** a container. Its output is generated; giving it children would create two conflicting sources for the same list.

### Settings

All settings live in `block.settings` JSON. No new table.

| Key | Type | Default | Notes |
| --- | --- | --- | --- |
| `scope` | enum `page_type` \| `path_prefix` \| `subtree_of_current` | `page_type` | Chooses which of the next two keys applies |
| `page_type` | page type slug | — | Required when `scope = page_type` |
| `path_prefix` | string | — | Required when `scope = path_prefix`; matched against `page_translations.path` |
| `sort` | enum `published_desc` \| `published_asc` \| `title_asc` \| `path_asc` | `published_desc` | |
| `limit` | int 1–48 | `12` | Hard cap, not a paginator |
| `layout` | enum `cards` \| `links` | `cards` | `cards` → `wb-grid` of `wb-card`; `links` → `wb-link-list` |
| `columns` | enum `2` \| `3` \| `4` | `3` | `cards` only; reuses `Block::gridColumnsClass()` |
| `show_thumbnail` | bool | `true` | `cards` only |
| `show_description` | bool | `true` | |
| `exclude_current` | bool | `true` | Keeps an index page out of its own list |

`subtree_of_current` derives the prefix from the current page's own translation path, so one block on `/guides` lists everything under it without hardcoding the prefix — and it keeps working after the page is moved or its slug changes.

There is no pagination and no "load more". A hard `limit` with a documented cap is honest; a paginated public block needs a query-string contract, a canonical-URL story, and SEO review, and none of that belongs in a first version.

### Filters That Are Not Optional

These are enforced in the query, never exposed as settings:

1. **Published only** — `status = published`. The precedent is `NavigationUnpublishedPageFilterTest`: archiving a page drops its link from every menu. A list block that leaked drafts would be a content incident.
2. **Site scope** — `site_id = $block->renderSite()?->id`. Never cross-site.
3. **Locale** — a page appears only if it has a translation for the render locale with a resolvable path. No half-translated rows, no silent fallback into another language.
4. **Shared Slot source pages excluded** — `page_type != shared-slot-source`. These are internal by design and are already excluded from ordinary page listings and public routing.
5. **Current page excluded** when `exclude_current`.

### Renderer Contract

Empty result renders **nothing** — no wrapper, no empty-state copy. This matches `navigation-auto` and `toc`, both of which guard on `isNotEmpty()`. An empty-state message on a public page is an editor-facing artifact leaking to visitors.

`cards` layout owns a `wb-grid` root and emits one `wb-card` per page, composing `wb-card-header` / `wb-card-body` / `wb-card-footer` exactly as the hand-built card grid in the guides plan does — same classes, no new CSS in this package, no CMS-side reimplementation of WebBlocks UI anatomy.

`links` layout owns a `wb-link-list` root, matching the existing `link-list` renderer's markup.

Both roots carry `data-wb-public-block-type`, like every other public block root.

### Query Shape

One query, eager loading in the same pass:

```
Page::query()
  ->where('site_id', $siteId)
  ->where('status', 'published')
  ->where('page_type', '!=', 'shared-slot-source')
  ->when($scope === 'page_type', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('slug', $pageTypeSlug)))
  ->whereHas('translations', fn ($t) => $t->where('locale_id', $localeId)->when($prefix, ...))
  ->with(['translations' => ..., 'site'])
  ->limit($limit)
```

Two performance notes for implementation:

- `Page::publicPath()` delegates to `PageRouteResolver::pathFor()`, which reads the translations relation. With translations eager-loaded this stays O(1) per page; without it, a 12-card grid is 12 extra queries. Eager loading is a correctness requirement here, not a nicety.
- `title_asc` and `path_asc` sort on the translation row, not on `pages`. `Page::scopeOrderByDefaultTranslation()` already exists for the admin and is the right thing to reuse or mirror.

There is no public page render cache in `src/Support/Pages/`, so publishing a page shows up in the list on the next request. Nothing to invalidate. If a render cache is ever introduced, this block becomes a cache-invalidation source and that must be designed then.

## Files To Touch

Ordered as the work should be done. Steps 1–3 make the block exist and render; 4–6 make it editable; 7–10 make it discoverable and translated; 11–12 close it out.

| # | File | Change |
| --- | --- | --- |
| 1 | `src/Support/Blocks/CoreBlockTypeCatalogSyncer.php` | Catalog row for `page-list`, new `sort_order` in the content band |
| 2 | `src/Models/Block.php` | `pageListPages()` helper plus settings accessors, modeled on `publicTocHeadingBlocks()` |
| 3 | `resources/views/pages/partials/blocks/page-list.blade.php` | Public renderer, both layouts |
| 4 | `src/Http/Requests/Admin/BlockRequest.php` | Validation rules, and a normalization branch that nulls editorial fields and writes `settings` — the `navigation-auto` branch is the template |
| 4b | `src/Http/Requests/Admin/PageRequest.php` | The same normalization for the inline block builder. Discovered during implementation: inline block fields are validated and normalized here, not in `BlockRequest`, which is why `navigation-auto`'s inline menu selector never reaches storage. Skipping this would have shipped the same dead field |
| 5 | `resources/views/admin/blocks/types/page-list.blade.php` | Admin form |
| 6 | `resources/views/admin/blocks/types/page-list-inline.blade.php` | Inline builder form (resolved by convention from `inline-block-fields.blade.php`) |
| 7 | `src/Support/BlockTypes/BlockTypeContractRegistry.php` | Documented contract entry — without it the block is reported as *undocumented* by block-types discovery |
| 8 | `src/Support/InternalContentApi/BlockSettingsPatchPolicy.php` | Settings allowlist with per-key types, so the API can author the block. `limit` needed a new `['int', min, max]` rule kind, added alongside it in `InternalContentResourceController::sanitizeSettingValue()` |
| 9 | `src/Support/Search/BlockSearchTextExtractorRegistry.php` | Return `''` — derived content must not be indexed as if it were this page's own copy |
| 10 | `resources/lang/{en,de,es,fr,it,tr}/admin.php` | `blocks.page_list.*` keys, all six locales in the same commit |
| 11 | `resources/views/admin/pages/partials/slot-block-picker.blade.php` | Add the slug to the content tab list |
| 12 | `src/Http/Controllers/Admin/PageController.php` (~961), `SharedSlotController.php` (~616) | Add `page-list` to the `translation_state = 'shared'` slug list |

Docs to update in the same change: `docs/inventory.md` (category table at line 208 plus a per-block contract table), `docs/public-block-render-markup.md`, `docs/block-ui-renderer-contract.md`, `docs/ai-page-building-guide.md`, and the closing note in `docs/user-guides-plan.md` that predicted this block.

### No Migration Needed

The block type row is created by `CoreBlockTypeCatalogSyncer`, which existing sites run through `CatalogRepairer` during System Update. Adding the catalog row is sufficient; do not hand-write a migration for it. This is also why `source_type: pages` is safe — the column is a free-form string.

### Tests

`tests/Feature/PageListBlockRenderTest.php`, covering the five non-optional filters plus `limit`, sorting, both scopes, both layouts, and the empty-render guard. The nearest existing models are `NavigationUnpublishedPageFilterTest` (the published-only rule) and `TocHeadingScanTest` (a derived list computed from a model helper).

`tests/Feature/BlockPatchSettingsContractTest.php` needs no new row: it already fails when a contract-declared settings field appears in neither `PATCHABLE` nor `CLOSED`, and every `page-list` field is patchable.

## Scope Boundaries

Explicitly **not** in this block:

- Pagination or infinite scroll.
- Tags, categories, or any taxonomy — none exists in the CMS, and inventing one inside a block is the wrong place for it.
- Cross-site listing.
- Manual pinning or per-item reordering. If an editor needs a curated order, `link-list` already does that better.
- A "read more" excerpt rendered from page body blocks. Deriving prose from another page's block tree is a much larger contract than reading one field.

## Open Questions

1. **Resolved: category is `content`.** The block lists links, which argues navigation; it renders cards with images and prose, which argues content. `content` wins because the card layout is the primary use and the picker's navigation tab is already dense. Reversible at any time: `category` is in `CoreBlockTypeCatalogSyncer::updatableColumns()`, so a later change propagates to existing sites on the next System Update.
2. **Resolved: `page_type` is single-select.** A `/guides` index spanning two page types is plausible, and an array is a small query change — but a noticeably more complex admin form, validation rule, and API contract. Ship single-select; widen only on real demand.
3. **Resolved: `subtree_of_current` matches the whole subtree.** Pages have no parent relation; the only hierarchy is `page_translations.path`, so prefix matching gives the full subtree for free while direct-children-only would need a segment-count guard. A `depth` setting is deferred until asked for.
