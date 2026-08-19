---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/inventory
cms_title: CMS Inventory
cms_layout: docs
cms_source_id: webblocks-cms:docs/inventory.md
---

# WebBlocks CMS Inventory for AI Page Building

## Purpose

This is the compact, AI-facing design and authoring contract for WebBlocks CMS. Read it before proposing or applying a page design through the Internal Content API.

It answers five questions for every shipped core block:

1. What content remains editable in the CMS admin?
2. Which shared settings and variants are supported?
3. Which child and media relationships are valid?
4. What stable public HTML does the renderer emit?
5. What visual result can the block produce without raw page HTML?

This document summarizes source-backed behavior. Live API discovery remains authoritative for install-specific IDs, enabled plugins, custom block types, locales, layouts, media records, navigation menus, and capabilities.

## Audit Baseline

- Repository: `fklavyenet/webblocks-cms`
- Branch: `main`
- Audited commit: `741a44bc0fe00bf38cae0753bd9edb02978b0dbe`
- Audited release documentation: `1.40.2`
- Audit date: `2026-07-14`
- Repository shape: package-only Composer package
- Published core catalog rows: `51`
- Draft legacy catalog rows: `7`
- AI-writable structured core rows after the policy below is enforced: `50`
- AI-writable raw HTML rows after the policy below is enforced: `0`

### Amendments since the audit

The baseline above is still the last full audit. These entries were corrected
against source afterwards rather than re-auditing every block, so treat anything
outside this list as `1.40.2`-era and confirm it through live API discovery.

- `card` (`1.40.5`): the Card style `variant` exists. This document previously
  stated that no supported Card visual variant field existed, which was wrong
  from `1.40.5` onward.
- `link-list` (`1.40.10`): `settings.row_layout` and `settings.list_frame`.
- `link-list-item` (`1.40.8`): optional `media_id` thumbnail.

Historical repository note: the pre-package-only tree contained `docs/feature-inventory.md`, a broad product-feature discoverability matrix. It was removed when the package-only repository tree was constructed and was not a per-block AI authoring inventory. No `inventory.md` exists in the current tree or reachable repository history.

Source families inspected:

- `src/Support/Blocks/CoreBlockTypeCatalogSyncer.php`
- `src/Support/BlockTypes/BlockTypeContractRegistry.php`
- `src/Support/Blocks/BlockTranslationRegistry.php`
- `src/Models/Block.php`
- `src/Http/Requests/Admin/BlockRequest.php`
- `src/Support/InternalContentApi/InternalContentPlanService.php`
- `src/Support/InternalContentApi/InternalContentApiOperations.php`
- `src/Http/Controllers/InternalContentApi/InternalContentResourceController.php`
- `src/Http/Controllers/InternalContentApi/InternalSharedSlotController.php`
- `src/Http/Controllers/InternalContentApi/InternalApiDiscoveryController.php`
- `routes/admin.php`
- `resources/views/admin/blocks/types/*.blade.php`
- `resources/views/admin/blocks/settings/*.blade.php`
- `resources/views/pages/partials/blocks/*.blade.php`
- `public/cms/css/public.css`
- focused package tests and current product documentation

## Non-Negotiable AI Authoring Rules

1. Use structured blocks. Do not store a page, section, card collection, navigation shell, form, or visual component in Trusted HTML.
2. `html` is a human-only escape hatch. API discovery may identify it as unavailable, but no API mutation may create, update, replace, move, reorder, clone, promote, publish, or delete an HTML block.
3. Do not bypass the HTML restriction through Rich Text, `<style>`, `<script>`, event-handler attributes, iframe markup, SVG markup, encoded markup, or invented settings.
4. Use only fields, enum values, media roles, and child relationships documented here and confirmed by live discovery.
5. Treat an admin-editable field as part of the supported authoring contract. A value recognized only by a renderer or legacy compatibility path is not a normal AI authoring field.
6. If a visual region cannot be expressed with the supported contract, stop and report a capability gap. Do not silently approximate it with unrelated blocks and do not fall back to HTML.
7. Site CSS may refine typography, spacing, color, borders, shadows, and responsive presentation through stable public hooks. It must not become a hidden content store or reconstruct missing semantic markup.
8. Do not target database IDs, generated block IDs, sibling position selectors, or `:nth-child()` for essential design behavior. Prefer block-type attributes, native `wb-*` classes, page body classes, and documented settings.
9. Keep every visible title, paragraph, label, button, badge, image, caption, menu, and form setting editable through its native CMS field or related record.
10. Validate first, apply only after explicit user approval, create drafts first, and leave live system-update actions and live visual testing to the human operator unless separately authorized.

## HTML Block API Policy

The target product contract is:

| Surface | `html` behavior |
| --- | --- |
| CMS admin | Human operators may create and edit reviewed Trusted HTML. |
| Public renderer | Existing published HTML blocks continue to render. |
| API discovery | Report the block as `api_readable: true`, `api_writable: false`, `authoring: human_only`, and explain the restriction. Do not present a writable payload example. |
| Content validate/apply | Reject every new or replacement `html` payload with stable code `block_type_not_api_writable`. |
| Incremental page/shared-slot block create | Reject `html` before normalization or persistence. |
| Existing block PATCH | Reject when the target block type is `html`, even if the submitted field would otherwise be considered safe. |
| Reorder, move, delete, slot replacement, staged update, and promotion | Reject any mutation whose affected subtree or replacement scope contains an existing HTML block. Do not delete it as a side effect. |
| API token capabilities | No capability may override the product-level restriction. |
| Read endpoints | May return the existing block for inspection according to the chosen read policy; read access must never imply write access. |

This policy is enforced in code by a single product policy class, `WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy`. Every API surface consults it instead of repeating the rule: both block normalizers, existing-block PATCH, page and Shared Slot incremental create, page/Shared Slot reorder, subtree delete, Shared Slot clear-all, page and Shared Slot publish, draft slot replacement, staged update creation and promotion, Shared Slot assignment, and API page delete. Rejections happen before any transaction or write, return HTTP 422 with the stable code `block_type_not_api_writable`, and leave no partial changes. No token capability overrides it.

## What “CMS-Manageable” Means

A design is CMS-manageable only when all of the following are true:

- Visible content is stored in native translation fields, settings, Media Library relations, Navigation records, Commerce records, or child blocks.
- The normal block editor exposes the fields needed to maintain the result.
- Public markup comes from a package or plugin renderer, not from page content.
- Presentation uses documented variants, settings, theme tokens, and stable CSS hooks.
- Reordering or editing content does not require editing HTML or CSS.
- Mobile behavior comes from the renderer, WebBlocks UI, or stable site CSS rather than duplicated mobile markup in content.

A renderer may recognize a legacy or internal value that the normal admin form does not expose. Such a value is documented as a compatibility input, not as a recommended AI authoring field.

## Design Decision Table

| Visual need | Preferred contract | Stop condition |
| --- | --- | --- |
| Major page band | `section` with child blocks | Do not put visible copy in Section settings. |
| Width constraint | `container` | Do not use Container as a card or surface. |
| Vertical content rhythm | `stack` | Do not use Container only to obtain vertical flow. |
| Main content plus a compact action or value | `split` | Use exactly two direct children; nest Stack when a side needs multiple blocks. |
| Horizontal actions or compact items | `cluster` | Do not use Grid for a single button row. |
| Responsive repeated cells | `grid` with structured children | Do not use Grid to fake a semantic table. |
| Page title, intro, badge, icon, metadata | `content_header` | It always owns an H1; do not use it for ordinary nested headings. |
| Marketing intro | `hero` | Hero supports left, centered, and split layouts; split renders the hero media as a foreground image beside the copy. Report a gap when the design requires a second editable foreground image or arbitrary nested content. |
| Conversion band | `cta` | Current CTA does not accept normal structured children other than managed legacy button children. |
| Repeated feature or stat items | `columns` and `column_item` | Prefer `grid` and composable `card` when arbitrary nested content is needed. |
| Composable card | `card` plus Card regions | There is no supported Card visual variant setting; report a gap if stable semantic card variants are required. |
| Single semantic image | `image` | Use Gallery for collections and background media fields for supported backgrounds. |
| Image collection | `gallery` | Do not add a separate HTML lightbox. |
| Slider/carousel | `slider` plus `slide` | Use Gallery when the content is only an image collection. |
| Navigation | Navigation records plus Navbar/Sidebar blocks | Do not hardcode navigation anchors in HTML. |
| Contact form | `contact_form` | Do not use raw `<form>` markup or `mailto:` as the normal form. |
| Ratings/comments | `rating` and `comments` | Do not reproduce engagement storage or forms in HTML. |
| Unsupported one-off composition | Capability-gap report | Never default to `html`. |

## Canonical Content-Plan Shape

Use nested `children` arrays. Do not submit database relationship IDs in a content plan.

```json
{
  "type": "section",
  "settings": {
    "spacing": "lg"
  },
  "children": [
    {
      "type": "container",
      "settings": {
        "width": "lg"
      },
      "children": [
        {
          "type": "plain_text",
          "translations": {
            "content": "Editable copy"
          }
        }
      ]
    }
  ]
}
```

Content-plan conventions:

- Put locale-owned copy directly under `translations` for the selected plan locale.
- Put URL, target, presentation variant, and other shared options under `settings`.
- Put direct Media Library assignment in `media_id`.
- Put Gallery items in `gallery_items` or `gallery_media_ids`.
- Use only nested `children`; do not send `id`, `parent_id`, `block_id`, `slot_type_id`, or `block_type_id`.
- The API currently accepts a broadly shaped `settings` object. That permissiveness is not permission to invent settings; use only the keys listed below.

## Public Rendering Shell

Normal main-slot rendering provides:

```html
<main class="wb-public-main" id="main-content">
  <div class="wb-container wb-container-lg">
    <div class="wb-stack wb-gap-6">
      <!-- page blocks -->
    </div>
  </div>
</main>
```

Blocks marked **root-owning** place `data-wb-public-block-type` on their own semantic root. Other top-level blocks normally receive:

```html
<div class="wb-public-block" data-wb-public-block-type="block-handle">
  <!-- renderer output -->
</div>
```

Underscores normalize to hyphens in `data-wb-public-block-type`; for example `content_header` becomes `content-header`.

## Quick Catalog Index

The current published core catalog contains 52 rows:

| Group | Handles |
| --- | --- |
| Layout and composition | `section`, `container`, `cluster`, `grid`, `card`, `card_header`, `card_body`, `card_footer`, `slider`, `slide` |
| Editorial and marketing | `header`, `plain_text`, `rich-text`, `content_header`, `hero`, `cta`, `columns`, `column_item`, `feature-grid`, `feature-item`, `stat-card`, `image`, `gallery`, `download`, `file`, `video`, `audio`, `code`, `button_link`, `table`, `quote`, `page-list`, `application` |
| Navigation | `link-list`, `link-list-item`, `navigation-auto`, `toc`, `breadcrumb`, `header-actions`, `sticky-navbar`, `navbar-brand`, `navbar-navigation`, `sidebar-brand`, `sidebar-navigation`, `sidebar-nav-item`, `sidebar-nav-group`, `search-form`, `sidebar-footer` |
| Pattern, form, and engagement | `alert`, `contact_form`, `rating`, `comments` |
| Human-only advanced | `html` |

## Layout And Composition Blocks

### `section` — Section

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Major semantic page band and child grouping. |
| Admin-editable content | No visible copy. Optional `settings.layout_name` is editor metadata only. |
| Settings | `spacing`: empty, `sm`, `lg`; optional background `media_id`; `background_position`: center, top, bottom, left, right; `background_overlay`: soft, medium, strong, none. |
| Children | Any supported published child type; at least one renderable child is required by API plans. |
| HTML | Root-owning `<section class="wb-section [wb-section-sm or wb-section-lg] wb-stack" data-wb-public-block-type="section">…</section>`. Background media adds package-owned class/style hooks. |
| Example appearance | A full-width themed band containing a constrained Container, heading, copy, cards, or media. |
| Avoid | Visible text in settings, empty chrome, or using Section as a card. |

### `container` — Container

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Width constraint and optional child flow. |
| Admin-editable content | No visible copy; optional editor-only `layout_name`. |
| Settings | `width`: empty, sm, md, lg, xl, full; `flow`: stack or none. |
| Children | Any supported published child type; at least one child required by API plans. |
| HTML | Root-owning `<div class="wb-container [wb-container-*] [optional wb-stack]" data-wb-public-block-type="container">…</div>`; `wb-stack` requires explicit `flow: stack`. |
| Example appearance | Centered page content with a maximum width; the neutral default composes directly with a Cluster inside Navbar. |
| Avoid | Treating width as a surface, card, or theme role. |

### `stack` — Stack

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Vertical flow and consistent rhythm between direct child blocks. |
| Admin-editable content | No visible copy; optional editor-only `layout_name`. |
| Settings | `spacing`: empty/default, 1, 2, 3, 4, 6, 8. |
| Children | Any supported published child type; at least one child required by API plans. |
| HTML | Root-owning `<div class="wb-stack [wb-stack-{n}]" data-wb-public-block-type="stack">…</div>`. |
| Example appearance | A product name, description, and supporting note arranged from top to bottom. |
| Avoid | Page-width control, horizontal actions, or equal columns. |

### `split` — Split

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Two-sided composition where the first child grows and the second stays content-sized. |
| Admin-editable content | No visible copy; optional editor-only `layout_name`. |
| Settings | `gap`: empty/default, 0, 1, 2, 3, 4, 6, 8; `items_alignment`: center/default, start, end, stretch; `width`: auto/default or full. |
| Children | Exactly two direct children. Put a Stack inside either side when that side needs multiple blocks. |
| HTML | Root-owning `<div class="wb-split …" data-wb-public-block-type="split">…</div>` with allowlisted `wb-*` classes. |
| Example appearance | Product identity on the left and a price plus buy action on the right. |
| Avoid | Repeated equal columns, wrapping button groups, or more than two direct children. |

### `cluster` — Cluster

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Horizontal or inline composition, especially actions and navbar internals. |
| Admin-editable content | No visible copy; optional editor-only `layout_name`. |
| Settings | `gap`: empty, none, xs, sm, md, lg; `alignment`: start/default, center, end, between; `items_alignment`: center/default, start, end, stretch; `wrap`: wrap/default or nowrap; `width`: auto/default or full. |
| Children | Any supported published child type; at least one child required by API plans. |
| HTML | Root-owning `<div class="wb-cluster …" data-wb-public-block-type="cluster">…</div>` with allowlisted `wb-*` classes. |
| Example appearance | A responsive two-button CTA row or brand/navigation/actions row. |
| Avoid | Large repeated card grids. |

### `grid` — Grid

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Responsive multi-column layout. |
| Admin-editable content | No visible copy; optional editor-only `layout_name`. |
| Settings | `columns`: 2, 3, 4; `gap`: empty, 3, 4, 6; `alternate_media_text_sections`: boolean; `alternate_start`: media_left or text_left. |
| Children | Any supported published child type; at least one child required by API plans. |
| HTML | Root-owning `<div class="wb-grid wb-grid-{n} [wb-gap-{n}]" data-wb-public-block-type="grid">…</div>`. Alternating mode may change direct-child order without changing the root. |
| Example appearance | Three Card blocks in a feature row, or paired Image/content groups alternating left and right. |
| Avoid | Semantic tables or a compact action row. |

### `card` — Card

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Composable framed surface. |
| Admin-editable content | No normal visible parent copy; optional editor-only `layout_name`. Legacy no-region Card rows may still render old copy. |
| Settings | Optional Card style on the shared `variant` column: `flat`, `muted`, `highlight`, `accent`; an empty variant renders the default card. Optional background image `media_id`, `background_position`, `background_overlay`. Optional `url` and `target` make the entire composable Card one semantic link. |
| Children | Direct children restricted to `card_header`, `card_body`, `card_footer`; at least one child required by API plans. |
| HTML | Root-owning `<article class="wb-card">…</article>`, or `<a class="wb-card wb-no-decoration">…</a>` when a whole-card URL is configured. Linked Cards must not contain nested interactive controls. |
| Example appearance | Image or icon header, editable body content, and action footer inside one native Card shell. |
| Avoid | Cards nested inside Cards or using legacy parent copy for new content. |

### `card_header` — Card Header

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Header region inside Card. |
| Admin-editable content | No direct copy; child blocks hold content. Optional editor-only `layout_name`. |
| Settings | `icon_slug` from the active content icon catalog; `icon_tone`: default, soft, brand, accent, highlight, bold, quiet. |
| Children | Structured content children. Do not nest Card region blocks. Normal placement is directly under Card. |
| HTML | Root-owning `<div class="wb-card-header" data-wb-public-block-type="card-header">[icon]…</div>`. |
| Example appearance | Card title row with a catalog icon and nested Header/Plain Text. |
| Avoid | Placement outside Card. |

### `card_body` — Card Body

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Main content region inside Card. |
| Admin-editable content | No direct copy; child blocks hold content. Optional editor-only `layout_name`. |
| Settings | No public visual setting beyond editor-only `layout_name`. |
| Children | Structured content children; API plans require at least one. Do not nest Card region blocks. |
| HTML | Root-owning `<div class="wb-card-body" data-wb-public-block-type="card-body">…</div>`. |
| Example appearance | Card copy, Image, Rich Text, or a small Cluster of buttons. |
| Avoid | Placement outside Card. |

### `card_footer` — Card Footer

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Supporting or action region inside Card. |
| Admin-editable content | No direct copy; child blocks hold content. Optional editor-only `layout_name`. |
| Settings | No public visual setting beyond editor-only `layout_name`. |
| Children | Structured content children; API plans require at least one. Do not nest Card region blocks. |
| HTML | Root-owning `<div class="wb-card-footer" data-wb-public-block-type="card-footer">…</div>`. |
| Example appearance | One or two Button Link children aligned by a nested Cluster. |
| Avoid | Placement outside Card. |

### `slider` — Slider

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | Composable carousel that fills its placed container. |
| Admin-editable content | No visible parent copy; optional editor-only `layout_name`. |
| Settings | `height`: auto, fill, viewport, large, medium, small, custom; optional `min_height`; `aspect_ratio`: 16/9, 4/3, 1/1; `interval_ms`: 1000–30000; booleans `autoplay`, `pause_on_hover`, `show_arrows`, `show_dots`, `loop`, `swipe`, `keyboard`; `overlay`: none/default, soft, medium, dark, strong; `content_position`: center/default or six corner positions; `content_width`: medium/default, narrow, wide, full; `text_color`: auto/default, light, dark; `background_fit`: cover/default or contain. Transition is currently normalized to slide. |
| Children | Only `slide`; at least one Slide required. |
| HTML | Root-owning `<section class="wb-slider …" data-wb-slider data-wb-public-block-type="slider">` with viewport, track, optional arrows, and dots. |
| Example appearance | Full-width hero carousel, card-contained slider, or background-media panels with editable child content. |
| Avoid | Static image galleries. |

### `slide` — Slide

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | One panel inside Slider. |
| Admin-editable content | No direct visible copy; optional editor-only `layout_name` and shared `aria_label`. |
| Settings | Background image `media_id`; `background_position`; `background_overlay` (`none`, `soft`, `medium`, `strong` — each renders a distinct scrim since WebBlocks UI 2.22.0; before that `medium` collapsed onto `strong`); `content_position`; `content_width`; `text_color`; `background_fit`. |
| Children | Any supported structured child type; a background-only Slide is allowed. Normal parent is Slider. |
| HTML | Root-owning `<article class="wb-slide …" data-wb-public-block-type="slide">[img.wb-slide-media]<div class="wb-slide-content">…</div></article>`. |
| Example appearance | Background product photo with nested Header, Plain Text, and Button Link content. |
| Avoid | Standalone top-level use when no carousel semantics are intended. |

## Editorial And Marketing Blocks

### `header` — Header

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`. |
| Settings and variants | `settings.variant`: h1–h6; `alignment`: left, center, right; `anchor`: safe same-page ID. |
| Children/media | None. |
| HTML | Root-owning `<h1>` through `<h6>` with optional alignment class and `id`. |
| Example appearance | Semantic section heading that can be indexed by TOC. |
| Avoid | Page intro with metadata; use Content Header. |

### `plain_text` — Plain Text

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.content` as escaped plain text. |
| Settings and variants | `alignment`: left, center, right. |
| Children/media | None. |
| HTML | Generic wrapper plus `<p class="[wb-text-*]">…</p>`. |
| Example appearance | Short paragraph, label, or supporting sentence. |
| Avoid | Lists, links, headings, or formatted body copy. |

### `rich-text` — Rich Text

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.content` through the safe Rich Text editor and sanitizer. |
| Settings and variants | None. Unsupported tags, attributes, and classes are stripped. |
| Children/media | None. |
| HTML | Generic wrapper plus `<div class="wb-rich-text">[sanitized editorial markup]</div>`. |
| Example appearance | Several paragraphs with safe inline emphasis, links, and simple lists. |
| Avoid | Layout markup, `<style>`, scripts, iframes, forms, buttons, tables, or a complete page. |

### `content_header` — Content Header

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` as intro, `translations.eyebrow` as optional badge label, `translations.meta` as metadata items. |
| Settings and variants | `alignment`: left, center, right; `icon_slug`; `icon_tone`; `badge_tone`: neutral, info, success, warning, danger; optional background image and overlay settings. |
| Children/media | No children; direct image `media_id` is background media. |
| HTML | Root-owning `<header class="wb-content-header …">` with optional icon/badge cluster, fixed `<h1 class="wb-content-title">`, subtitle, and metadata row. |
| Example appearance | Page title with optional product badge/icon, concise lead text, and two metadata labels. |
| Avoid | Nested sections where H1 is semantically wrong. |

### `hero` — Hero

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` as eyebrow, `translations.content`. Action buttons are separate child `button_link` blocks with their own admin form. |
| Settings and variants | `variant`: default, muted, soft, accent; `layout`: left or centered; `title_tag`: h1, h2, h3; optional background image and overlay settings. |
| Children/media | Actions are child `button_link` blocks, with no fixed count; direct image `media_id` is background media. |
| HTML | Root-owning `<section class="wb-card wb-promo [wb-card-*]">` containing `.wb-card-body.wb-promo-copy`, eyebrow, promo title/text, and optional `.wb-promo-actions`. |
| Example appearance | A contained promo-card hero with background media and up to two actions. |
| Hard limitation | No structured foreground image, split column, product-price region, trust strip, or arbitrary nested content. Do not claim fidelity to a screenshot requiring those features. |
| Actions | Add `button_link` children; they render inside `.wb-promo-actions`. `primary_cta` / `secondary_cta` `{label, url}` objects remain accepted as a shorthand that writes the first two of those children. Do not reach for a sibling Cluster with Button Link — that renders outside the promo root. `allowed_child_handles` also lists legacy `button`, which has no published catalog row and stays in `unreachable_child_handles`. |

### `cta` — CTA

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` as eyebrow, `translations.content`. Action buttons are separate child `button_link` blocks with their own admin form. |
| Settings and variants | `variant`: default, muted, soft, accent; optional background image and overlay settings. CTA title renders as H2. |
| Children/media | Actions are child `button_link` blocks, with no fixed count; direct image `media_id` is background media. |
| HTML | Root-owning `<section class="wb-card wb-promo [wb-card-*]">` with `.wb-promo-copy` and optional action row. |
| Example appearance | Short conversion band near the end of a page. |
| Actions | Identical to Hero: add `button_link` children, or use the `primary_cta` / `secondary_cta` shorthand. |
| Limitation | `settings.layout=centered` is renderer-compatible but is not exposed by the normal CTA admin form and is not a recommended AI authoring field. |

### `columns` — Columns

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle`, `translations.content`; child item title, badge, content, URL, icon, and tones. |
| Settings and variants | `settings.variant`: cards, plain, stats. |
| Children/media | Only `column_item`. Child count selects stack, 2-column, 3-column, or 4-column layout. |
| HTML | Root-owning `<section class="wb-stack wb-gap-4">` with optional intro and a responsive item grid. |
| Example appearance | Three benefit cards, four compact features, or a simple metric row. |
| Manageability caveat | The stats renderer can use child `subtitle` as the value, but normal Column Item admin forms do not expose that subtitle. AI-created stats that depend on it are not fully manageable and should be avoided until the form contract is aligned. |

### `column_item` — Column Item

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`, optional `translations.eyebrow` badge; shared `settings.url`, `icon_slug`, `icon_tone`, `badge_tone`. |
| Settings and variants | Presentation is controlled by the parent Columns variant: cards, plain, or stats. |
| Children/media | None; intended only under Columns. |
| HTML | Cards: `.wb-card > .wb-card-body`; plain: `.wb-icon-card`; stats: `.wb-stat`. Optional safe link wraps cards/plain output. |
| Example appearance | Icon-and-copy feature card with an optional badge. |
| Avoid | Standalone use or relying on renderer-only subtitle for a stat value. |

### `feature-grid` — Feature Grid

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `subtitle`, `content`; child feature fields. |
| Settings and variants | No independent presentation variant. Renderer forces the Columns cards presentation and prefers three columns. |
| Children/media | `feature-item` and compatibility `column_item`. |
| HTML | Delegates to Columns and renders a card grid. It is not listed as root-owning by `Block::ownsPublicRoot`, so top-level output may receive a generic wrapper around the delegated Section root. |
| Example appearance | Legacy three-up feature cards. |
| Recommendation | For new pages prefer Columns/Column Item or Grid/Card; use Feature Grid only when its dedicated editor is valuable and the delegated contract is accepted. |

### `feature-item` — Feature Item

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`, optional badge label; shared URL, icon slug/tone, badge tone. |
| Settings and variants | Always delegates to Column Item cards presentation. |
| Children/media | None; intended under Feature Grid. |
| HTML | `.wb-card > .wb-card-body > .wb-icon-card` with optional icon and badge. |
| Example appearance | One icon-led feature card. |
| Recommendation | Prefer canonical Card regions or Column Item for new general-purpose compositions. |

### `stat-card` — Stat Card

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.subtitle` label, `translations.title` value, `translations.content` detail; shared URL. |
| Settings and variants | None. |
| Children/media | None. |
| HTML | Generic wrapper plus `.wb-stat`, `.wb-stat-label`, `.wb-stat-value`, `.wb-stat-meta`, and optional Learn more link. |
| Example appearance | “24h” value with “Dispatch” label and supporting detail. |
| Avoid | Decorative marketing card where arbitrary nested content is needed. |

### `image` — Image

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Locale-owned image `alt_text` and `caption`; shared optional URL. |
| Settings and variants | No visual variant. Focal point and generated variants belong to the Media record. |
| Children/media | Direct image `media_id`; no children. |
| HTML | Root-owning `<figure class="wb-stack wb-gap-2">` with responsive `<img>` output, optional linked image, and `<figcaption>`. |
| Example appearance | Product or editorial image with an editable caption. |
| Avoid | Background treatment, collections, or decorative layout HTML. |

### `gallery` — Gallery

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Ordered Gallery items with locale-owned `alt_text`, `caption`, `overlay_title`, `overlay_text`; optional shared viewer title. Gallery intro copy is intentionally separate. |
| Settings and variants | `variant`: grid, masonry, collage; `columns`: 2–5; `gap`: none, sm, md, lg; `aspect_ratio`: auto, square, 4:3, 16:9, portrait; `captions_mode`: hidden, below, overlay, on-hover; `overlay_mode`: none, gradient, solid; `lightbox_enabled`: boolean. |
| Children/media | `gallery_items` or `gallery_media_ids` referencing image Media records; no block children. |
| HTML | Root-owning `.wb-gallery.wb-gallery--{variant}` with gallery items, responsive media, captions, and optional registry-owned viewer under the canonical overlay root. |
| Example appearance | Equal product grid, natural-height editorial masonry, or featured-first collage. |
| Avoid | Adding heading/description into Gallery; compose Content Header or Rich Text before it. |

### `download` — Download

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title` button label and `translations.subtitle` helper copy. |
| Settings and variants | `settings.variant`: primary, secondary, ghost. |
| Children/media | Direct document/other `media_id`; no children. |
| HTML | Root-owning `.wb-stack.wb-gap-2` with `<a class="wb-btn …" download>` and optional helper paragraph. |
| Example appearance | “Download guide” button with file description. |
| Avoid | External-only file cards; use File. |

### `file` — File

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`; shared URL fallback. |
| Settings and variants | None. |
| Children/media | Direct document/other `media_id`; media wins over safe external URL. |
| HTML | Root-owning muted Card with title, description, download/open button, and file metadata. |
| Example appearance | Downloadable PDF resource card. |
| Avoid | Simple button-only downloads. |

### `video` — Video

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`; shared safe URL fallback. |
| Settings and variants | Source determines native video, YouTube/Vimeo iframe, or open-video button. |
| Children/media | Direct video `media_id`; no children. |
| HTML | Root-owning muted Card containing `<video>`, an allowlisted provider `<iframe>`, or a safe link. |
| Example appearance | Uploaded demo video with editable title and description. |
| Avoid | Arbitrary iframe HTML. |

### `audio` — Audio

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`; shared safe HTTP URL fallback. |
| Settings and variants | None. |
| Children/media | Admin and renderer support selected audio media; no children. |
| HTML | Root-owning muted Card with copy and native `<audio controls>`. |
| Example appearance | Audio lesson or sample player. |
| API gap | The audited content-plan direct-media allowlist omits `audio`, so `media_id` assignment is rejected even though the admin and renderer support it. Use a reviewed safe URL only when appropriate or fix the API contract before AI media assignment. |

### `code` — Code

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` filename/language label, `translations.content` code body. |
| Settings and variants | `settings.language` becomes sanitized `data-language`. |
| Children/media | None. |
| HTML | Generic wrapper plus `<pre><code data-language="…">…</code></pre>`. |
| Example appearance | Copyable-looking command or source snippet. |
| Avoid | Prose, layout, or executable scripts. |

### `button_link` — Button Link

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title` as the button label; shared `settings.url`. At public render time an internal path follows the render locale (rewritten to the target page's translated path when it resolves); the stored value stays shared and raw. |
| Settings and variants | `settings.target`: `_self` or `_blank`; shared `variant`: primary/default or secondary. URL accepts a safe full HTTP(S) URL, site path, anchor, `mailto:`, or `tel:` target. |
| Children/media | None. This is a standalone editorial action and is distinct from the non-catalog managed `button` child used by Hero and CTA. |
| HTML | Generic wrapper plus `<a class="wb-btn wb-btn-primary">` or its secondary-class equivalent; `_blank` adds `rel="noopener noreferrer"`. Empty or unsafe URL emits no anchor. |
| Example appearance | One managed primary or secondary action, or several actions arranged by a Cluster. |
| Avoid | Hardcoded anchors in HTML or substituting it for Hero/CTA's internal managed-action child when the action must render inside that promo root. |

### `table` — Table

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`; `translations.content` as newline-separated, pipe-delimited rows. |
| Settings and variants | `settings.variant`: header-row/default or plain. Legacy `settings.rows` remains readable but is not recommended for new API content. |
| Children/media | None. |
| HTML | Generic wrapper containing `.wb-table-wrap > table.wb-table`, optional `<thead>`, and `<tbody>`. |
| Example appearance | Small comparison or specification table. |
| Avoid | Page layout grids or interactive datasets. |

### `quote` — Quote

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.content` quote, `translations.title` and `translations.subtitle` attribution parts. |
| Settings and variants | `settings.variant`: default or testimonial. |
| Children/media | None. |
| HTML | Generic wrapper with `<blockquote class="wb-stack wb-gap-2">`; testimonial adds a muted Card shell. |
| Example appearance | Editorial quotation or customer testimonial. |
| Avoid | General-purpose callouts. |

## Navigation Blocks

### `link-list` — Link List

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle`, `translations.content`; child link copy. |
| Settings and variants | `settings.row_layout`: `index` (default), `stacked` puts each row description under its title. `settings.list_frame`: `joined` (default), `cards` gives each row its own card. Independent; both are writable through the API. |
| Children/media | Only `link-list-item`. |
| HTML | Generic wrapper with optional intro stack and `.wb-link-list`, plus `wb-link-list--stacked` / `wb-link-list--cards` for the selected styles. |
| Example appearance | Resource index with title, metadata, description, icons, and badges. |

### `link-list-item` — Link List Item

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Required `translations.title`, optional `subtitle`, `content`, and `eyebrow` badge; shared required URL. |
| Settings and variants | `icon_slug`, `icon_tone`, `badge_tone`. |
| Children/media | Optional image `media_id` thumbnail; intended under Link List. |
| HTML | `<a class="wb-link-list-item">` with an optional leading thumbnail or icon (adding `wb-link-list-item--media`), title/meta/badge, and optional description. |
| Example appearance | Documentation/resource row marked “New”. |
| Render guard | Emits only with both a safe URL and title. |

### `page-list` — Page List

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No page copy. Titles, descriptions, and thumbnails come from each listed page's translation: `name`, then `list_excerpt` falling back to `seo_description`, then `og_image_media_id`. |
| Settings and variants | `scope` (`page_type`, `path_prefix`, `subtree_of_current`), `page_type`, `path_prefix`, `sort`, `limit` (1-48), `layout` (`cards`/`links`), `columns`, `show_thumbnail`, `show_description`, `exclude_current`, `clickable_card`. |
| Children/media | Neither. Rows come from a page query; thumbnails resolve from each page translation's Open Graph image. |
| HTML | `wb-grid` of `wb-card` articles (or single-link Card roots when `clickable_card` is enabled), or a `wb-link-list` of `wb-link-list-item` anchors. |
| Example appearance | A three-column index of guide cards, each linking from its title. |
| Render guard | Emits nothing when the query returns no pages, or while the scope is unconfigured. Published status, site, render-locale translation, Shared Slot source pages, and the hosting page are filtered in the query and are not settings. |

### `application` — Application Block

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No editorial copy. Selects a database-registered Embedded Application by stable `application_handle`. |
| Settings and variants | `application_settings` is validated against the selected definition schema. CMS-owned presentation settings are `width`, `loading`, `aspect_ratio`, `min_height`, `show_loading_state`, and `show_failure_state`. |
| Children/media | Neither. Executable assets belong to the registered application definition and cannot be supplied through block content or Media. |
| HTML | Inline applications receive a generated `.wb-application__mount`; iframe applications receive a CMS-owned, sandboxed iframe. CSS and JavaScript declared by ready definitions load once per page. |
| API authoring | Writable through content validate/apply and direct block settings patch. Discover handles with `GET /webadmin/api/applications` and schemas with `/applications/{application}/schema`; these reads require `applications.read`. Registry mutation is not exposed. |
| Render guard | Missing, invalid, or duplicate definitions do not load assets or execute. They render nothing unless the block's translated generic failure state is enabled. |

### `navigation-auto` — Navigation Auto

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No page copy; selected CMS Navigation menu. |
| Settings and variants | `menu_key` from known menu locations. Footer/legal keys render stacked links; primary/default renders clustered button-style links. |
| Children/media | Navigation records, not block children. |
| HTML | Generic wrapper plus semantic `<nav>` and `<ul>` tree. |
| Example appearance | Compatibility navigation menu in a slot. |
| Recommendation | Prefer Navbar Navigation for new shared headers. |
| Contract registry gap | This published handle has an admin form and renderer but no entry in `BlockTypeContractRegistry` at the audit baseline. Do not infer a complete live API contract until discovery confirms it. |

### `toc` — TOC

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Optional shared title. |
| Settings and variants | None. |
| Children/media | Reads published Header blocks in the same slot with valid anchors and H2/H3 variants, in document order. |
| HTML | Generic wrapper with a generated `nav.wb-section-nav` link list — a self-contained WebBlocks UI primitive, not `wb-link-list`. |
| Live behavior | Scroll-position highlighting comes free from the shipped `WBSectionNav` module in the same webblocks-ui.js the public layout already loads; the renderer owns no JavaScript of its own. |
| Example appearance | “Contents” list for a long documentation page. |
| Render guard | Emits nothing when no eligible headings exist. |

### `breadcrumb` — Breadcrumb

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Shared `home_label`; current page title comes from the page. |
| Settings and variants | `include_current`: boolean. |
| Children/media | Uses page/site/locale context. |
| HTML | Generic wrapper plus `<nav class="wb-breadcrumb"><ol class="wb-breadcrumb-list">…</ol></nav>`. |
| Example appearance | Home / Category / Current page. |

### `header-actions` — Header Actions

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No copy. |
| Settings and variants | Booleans `show_search`, `show_mode_toggle`, `show_accent_toggle`, `show_language_switcher`. Public preset/accent controls are currently suppressed by the site-level Public Theme model. |
| Children/media | None. |
| HTML | Generic wrapper plus compact `.wb-topbar-actions` icon controls. |
| Example appearance | Search and light/dark/auto mode actions at the right side of a Navbar. |
| Avoid | Business CTAs. |

### `sticky-navbar` — Navbar

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No direct copy; optional editor-only `layout_name`. |
| Settings and variants | `sticky_mode`: sticky/default, static, fixed. |
| Children/media | Allowed children: container, cluster, header, plain_text, rich-text, button_link, navbar-brand, navbar-navigation, header-actions, search-form. At least one child required by API plans. |
| HTML | Root-owning `<nav class="wb-navbar …" data-wb-public-block-type="sticky-navbar">…</nav>`. |
| Example appearance | Shared header: Navbar → Container → Cluster(between) → Brand + navigation/actions. |
| Avoid | A second custom header shell. |

### `navbar-brand` — Navbar Brand

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle`; shared URL, target, aria label. |
| Settings and variants | `url`; `target`: _self or _blank; `aria_label`. |
| Children/media | Optional image `media_id` for logo. |
| HTML | Generic wrapper plus `<a class="wb-navbar-brand">` with optional image and identity copy. |
| Example appearance | Logo, site name, and concise tagline. |

### `navbar-navigation` — Navbar Navigation

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Shared title as ARIA label; selected Navigation menu. |
| Settings and variants | `menu_key`; `active_indicator`: underline, pill, dot, background, none; `active_matching`: path, section, current-page, exact, off. |
| Children/media | CMS NavigationItem tree. |
| HTML | Generic wrapper plus desktop `.wb-navbar-links`, mobile WebBlocks UI dropdown, active classes, and group dropdowns. |
| Example appearance | Responsive primary navigation with automatic burger menu. |

### `sidebar-brand` — Sidebar Brand

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle`; shared URL, target, aria label. |
| Settings and variants | Same safe link contract as Navbar Brand. |
| Children/media | Optional image `media_id` for logo. |
| HTML | Generic wrapper plus `<a class="wb-sidebar-brand">` with logo and identity copy. |
| Example appearance | Documentation logo/title at the top of a sidebar. |

### `sidebar-navigation` — Sidebar Navigation

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title` as ARIA label; optional editor-only `layout_name`. |
| Settings and variants | Optional `menu_key`; `show_icons`: boolean; `active_matching`: path, current-page, exact. |
| Children/media | Either CMS Navigation records or manual `sidebar-nav-item` / `sidebar-nav-group`; at least one child is required in manual API plans. |
| HTML | Generic wrapper plus `<nav class="wb-sidebar-nav">` and WebBlocks UI sidebar structures. |
| Example appearance | Documentation sidebar with active section indication. |

### `sidebar-nav-item` — Sidebar Nav Item

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Required `translations.title`; shared URL and target. |
| Settings and variants | `icon` from catalog; `active_mode`: exact, path, current-page, manual; `manual_active`: boolean. |
| Children/media | None; intended under Sidebar Navigation or Sidebar Nav Group. |
| HTML | `<a class="wb-sidebar-link">` or nested `.wb-nav-group-item`, with optional icon and active state. |
| Example appearance | Manual documentation link. |

### `sidebar-nav-group` — Sidebar Nav Group

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Required `translations.title`; optional editor-only `layout_name`. |
| Settings and variants | `icon`; `initially_open`: boolean. |
| Children/media | Only `sidebar-nav-item`. |
| HTML | `.wb-nav-group` with button toggle, arrow, icon, and `.wb-nav-group-items`. |
| Example appearance | Collapsible “Guides” group in a docs sidebar. |

### `search-form` — Search Form

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title` label, `translations.content` placeholder, `translations.subtitle` submit label. |
| Settings and variants | `settings.variant`: primary or secondary; `show_button`: boolean. |
| Children/media | None; requires a resolvable site search route. |
| HTML | Generic wrapper plus `<form role="search" class="wb-cluster …">`, native input, and optional WebBlocks button. |
| Example appearance | Site search field in a header or page. |

### `sidebar-footer` — Sidebar Footer

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`, `translations.subtitle` footer note. |
| Settings and variants | `settings.variant`: info, success, warning, danger. |
| Children/media | None. |
| HTML | Generic wrapper plus `.wb-sidebar-footer`, toned `.wb-callout`, and optional muted note. |
| Example appearance | Small documentation notice or version note. |

## Pattern, Form, And Engagement Blocks

### `alert` — Alert

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, required `translations.content`. |
| Settings and variants | `settings.variant`: info, success, warning, danger. |
| Children/media | None. |
| HTML | Generic wrapper plus `<div class="wb-alert wb-alert-{tone}">` and optional title. |
| Example appearance | Inline warning, success note, or informational message. |
| Avoid | Marketing promos. |

### `contact_form` — Contact Form

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Locale-owned `title`, `content`, `submit_label`, `success_message`, `consent_label`. |
| Settings and variants | `recipient_email`; `send_email_notification`; `store_submissions` remains product-owned true in the native contract; `consent_required` (boolean, default false). |
| Consent | Set `consent_required` and give the locale a `consent_label` to render a required consent checkbox. The wording is translated because it *is* the notice. An accepted submission stores `consent_accepted_at` plus a copy of the wording, so editing the block later cannot change what a past visitor is recorded as having agreed to. A required consent with no wording for the resolved locale renders no checkbox rather than an unlabelled one. `consent_required` is closed to PATCH: removing a legal notice from a live form is an operator decision. |
| Children/media | None. |
| HTML | Generic wrapper around native `section.wb-card`, CSRF-protected form, renderer-generated anti-spam field, WebBlocks inputs, textarea, optional consent checkbox, validation errors, and submit button. |
| Example appearance | Fully managed contact form stored in Contact Messages with optional notification. |
| Avoid | Raw form HTML, custom honeypot fields, or `mailto:` replacement. |

### `rating` — Rating

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Optional shared single-language `settings.title`; normal visitor copy is product-translated. |
| Settings and variants | `scale`: fixed 5; `allow_change`: boolean; `show_summary`: boolean. |
| Children/media | Uses `content_ratings`; no children. |
| HTML | Root-owning `<section class="wb-card">` with optional H3, partially filled `.wb-rating-stars`, summary, and no-JS `.wb-rating-input` submit buttons. |
| Example appearance | Five-star page rating with average and response count. |
| Note | `allow_change` is enforced by the submission controller rather than by hiding the form. |

### `comments` — Comments

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No block-authored visitor copy; product translations supply labels and messages. |
| Settings and variants | `form_enabled`, `show_approved`, `show_author_name`; `sort_order`: newest or oldest. |
| Children/media | Uses moderated `comment_entries`; no children. |
| HTML | Root-owning `<section class="wb-card wb-public-comments">` with approved comment list, native CSRF form, anti-spam field, validation state, and submit action. |
| Example appearance | Moderated comments below an article or product guide. |
| Avoid | Custom comment storage or raw form markup. |

## Human-Only Advanced Block

### `html` — HTML (Trusted)

| Contract area | Source-backed behavior and target policy |
| --- | --- |
| Purpose | Reviewed human escape hatch for trusted markup that has no structured product contract yet. |
| Admin-editable content | Trusted HTML content. The current translation registry treats it as text-family content. |
| Settings and variants | None. Recognized overlay/body-end fragments may be extracted to package registries. |
| Children/media | No children. |
| HTML | Generic wrapper plus a plain inner `<div>` containing trusted markup; extracted fragments may render outside the visible root. |
| API authoring | Prohibited. No create, update, replacement, topology mutation, destructive mutation, staged mutation, or publish mutation. |
| AI behavior | Report a capability gap and propose a structured block/variant/renderer. Never generate a writable HTML payload. |

## Legacy And Renderer-Only Handles

Do not treat a Blade partial as proof that a handle is available for new API content. The current source contains compatibility renderers and draft rows that are not published core authoring contracts.

Draft catalog rows include:

```text
text
card-grid
tabs
menu
faq-list
showcase-list
contact-info
```

Renderer-only, alias, partial, or compatibility handles include examples such as:

```text
accordion
faq
button
callout
list
map
metric-card
stats
testimonial
gallery-viewer
sidebar-nav-item-link
sidebar-navigation-menu-item
fallback
missing-renderer
```

Rules:

- Never author these merely because a renderer file exists.
- Use them only if the live authenticated block catalog reports the exact handle as published and usable for the current install.
- Prefer canonical structured blocks documented above.
- Internal partials such as gallery viewer and sidebar link renderers are never content-plan block types.

## Visual Composition Recipes

These are managed block trees, not fixed templates. Confirm all handles at runtime.

### Marketing page intro with separate actions

```text
section(background optional)
└── container(width:lg)
    ├── hero(variant:accent, layout:centered)
    └── cluster(alignment:center, gap:sm)
        ├── button_link(primary)
        └── button_link(secondary)
```

Use this only when the action row may sit outside the Hero promo root. If the screenshot requires buttons inside a Hero with a split foreground image, report the Hero contract gap.

### Feature card grid

```text
section(spacing:lg)
└── container(width:lg)
    ├── header(h2)
    └── grid(columns:3, gap:4)
        ├── card
        │   └── card_body
        │       ├── header(h3)
        │       ├── plain_text
        │       └── button_link
        ├── card
        └── card
```

Every title, paragraph, and action remains independently editable. Use site CSS for a consistent site-specific Card skin through stable hooks; do not inject card HTML.

### Alternating image and copy rows

```text
section
└── container
    ├── grid(columns:2, alternate_media_text_sections:true, alternate_start:media_left)
    │   ├── image
    │   └── card or content stack
    └── grid(columns:2, alternate_media_text_sections:true)
        ├── image
        └── card or content stack
```

Use Image for foreground media. Use a background-capable block only when the image is semantically a background.

### Shared responsive navbar

```text
sticky-navbar(sticky)
└── container(width:lg)
    └── cluster(alignment:between, width:full)
        ├── navbar-brand
        └── cluster
            ├── navbar-navigation
            └── header-actions
```

Navigation labels and URLs belong to CMS Navigation records, not to HTML.

### Managed image slider

```text
slider(height:viewport, autoplay:false, show_arrows:true, show_dots:true)
├── slide(background media)
│   └── container
│       ├── header
│       ├── plain_text
│       └── button_link
└── slide(background media)
    └── container
        └── card
            └── card_body
                └── rich-text
```

## Design-To-CMS Workflow

Before applying a visual design, produce a mapping table:

| Design region | Content owner | Block tree | Variant/settings | Stable CSS hooks | Capability status |
| --- | --- | --- | --- | --- | --- |
| Example hero | Page translations and Media Library | Section → Container → Hero | accent, centered, background media | `[data-wb-public-block-type="hero"]`, `.wb-promo` | Supported only if background-media promo matches the design |

For each region:

1. Identify every editable piece of copy, media, action, badge, navigation data, and dynamic record.
2. Map each piece to an admin-editable native field.
3. Confirm parent/child rules and renderer HTML.
4. Confirm the visual composition is possible with the documented DOM.
5. Use site CSS only for presentation that the stable DOM can support.
6. If any semantic field, wrapper, slot, or variant is missing, mark the region unsupported.
7. Propose the smallest reusable CMS or plugin capability: a renderer variant, a new structured block, a pattern composed of existing blocks, or a domain block such as a Commerce product collection.
8. Do not apply a knowingly low-fidelity substitute unless the user explicitly approves that compromise.

Capability-gap report format:

```text
Region: Storefront hero
Required editable content: title, body, two actions, foreground product image, offer badge, trust items
Current closest block: hero
Supported: title, eyebrow, body, background image, promo tone
Missing: foreground media slot, split DOM, trust-item collection, discoverable managed action child
Why CSS is insufficient: required semantic wrappers and editable fields do not exist
Recommended product change: add a reusable split/storefront Hero variant and structured trust-item children
HTML fallback: prohibited
```

## CSS Guidance

Use styling layers in this order:

1. WebBlocks UI primitives already emitted by the renderer.
2. Public Theme tokens and mode-aware public color roles.
3. Native block settings and variants.
4. Narrow site-specific CSS using stable hooks.
5. A reusable renderer or block contract change when the required DOM is missing.

Stable selectors include:

```css
body[data-wb-public-theme] {}
[data-wb-public-block-type="hero"] {}
[data-wb-public-block-type="card"] {}
.wb-promo {}
.wb-card {}
.wb-content-header {}
```

Do not use site CSS to:

- insert essential text with pseudo-elements;
- depend on generated block IDs;
- infer semantics from sibling order;
- hide CMS-authored content merely to replace it with CSS content;
- rebuild a missing layout with fragile absolute positioning;
- hardcode light-only colors that break Light/Dark/Auto mode.

## Known Source Gaps At The Audit Baseline

These are implementation findings, not permissions to invent behavior:

1. Resolved: this inventory now ships as `docs/inventory.md` and is served to tools by `GET /webadmin/api/inventory`.
2. `docs/block-type-contracts.md` says 42 published core types, while the current catalog defines 51.
3. Several existing docs still show pre-package-only renderer paths under `packages/webblocks-cms/...`; current package paths begin at `resources/views/...`.
4. Resolved: Trusted HTML is no longer API-writable. `BlockTypeApiAuthoringPolicy` blocks every API mutation path, including generic normalization, existing-block PATCH, and the Shared Slot reorder, subtree-delete, clear-all, and publish operations.
5. Resolved: Hero and CTA are plain containers for `button_link` children in both the admin and the API. The `primary_cta` / `secondary_cta` fields survive as a two-button shorthand. The unpublished legacy `button` catalog row is no longer an authoring blocker.
6. Resolved: the Column Item editor now exposes the subtitle field that the Columns `stats` variant renders as the stat value.
7. Audio has a normal admin media picker and public media renderer, but the content-plan direct-media allowlist omits Audio.
8. Resolved: icon normalization has one owner. `InternalContentApiOperations` holds the canonical `PUBLIC_ICON_BLOCK_TYPES` list plus the shared slug/tone normalizers, and the full content plan delegates to them, so plans and incremental block endpoints validate icons identically.
9. API block settings are not yet governed by one per-block machine-readable settings schema. Unknown settings can survive normalization even when no renderer or admin field uses them.
10. Resolved: `navigation-auto` now has a documented contract in `BlockTypeContractRegistry` and is discoverable through block-types and content-contract.
11. WebBlocks UI ships a `wb-footer-*` anatomy (`wb-footer-grid`, `wb-footer-brand`, `wb-footer-nav`, `wb-footer-link`, `wb-footer-list`, `wb-footer-item`, `wb-footer-copy`, `wb-footer-meta`, `wb-footer-text`, `wb-footer-logo`) that no CMS renderer emits. A shared-slot footer composes generic `wb-section`/`wb-container`/`wb-grid`/`wb-stack`/`wb-cluster` instead, so the pattern is reachable only from hand-written layouts. Cosmetic since 1.50.0 gave `.wb-slot-footer` its own surface; a footer-composition block remains deliberately deferred rather than pending.
12. Resolved: `GET /content-contract` derives its `media_library` section from the registered route table, so `supported_operations` and `unsupported_operations` cannot drift from what `openapi.json` publishes. Upload, remote fetch, delete, replace and move are published as supported with the capability each route enforces.
13. Resolved: consent has a visitor-facing half. The System Settings banner toggle renders WebBlocks UI's Cookie Consent pattern on public pages and wires it to the existing `POST /privacy-consent/sync` endpoint, and `contact_form` gained `settings.consent_required` plus a translated `consent_label` recorded on each submission.
14. The repository has dashboard and page-management screenshots, but no canonical per-block/per-variant visual fixture gallery. The “Example appearance” descriptions in this inventory are therefore source-derived, not screenshot-backed golden references.

## Recommended Inventory Freshness Checks

When this file is added to the project, automated documentation checks should fail on:

- a published core catalog handle missing from this inventory;
- an inventory handle no longer present or no longer published;
- a documented renderer or admin form path that does not exist;
- a documented child rule differing from `Block::allowedChildTypeSlugs()`;
- a documented root-ownership claim differing from `Block::ownsPublicRoot()`;
- an API-write policy differing from discovery and mutation guards;
- an enum documented here but rejected by the admin request or ignored by the renderer;
- a new admin-visible setting absent from this inventory;
- a new visual variant without a canonical fixture or explicit fixture-gap note.

The long-term ideal is to generate the mechanical catalog data from one product-owned registry and keep the prose, examples, and design guidance reviewed by humans.

## Related Detailed References

- `docs/ai-page-building-guide.md`
- `docs/internal-content-api.md`
- `docs/api-discovery.md`
- `docs/block-type-contracts.md`
- `docs/public-block-render-markup.md`
- `docs/block-ui-renderer-contract.md`
- `docs/public-theme-and-tones.md`
- `docs/public-assets.md`
- `docs/media-image-variants.md`

This inventory should be the first document an AI reads for page-design capability selection. The detailed references remain useful for endpoint workflows, historical compatibility, and full renderer notes.
