---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/inventory
cms_title: CMS Inventory
cms_layout: docs
cms_source_id: webblocks-cms:resources/contracts/inventory.md
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

## Contract Scope And Freshness

This contract describes the supported authoring surface of the reviewed package version. The current catalog is listed below. Confirm install-specific availability, enabled plugins, permissions and supported fields through API discovery before applying a content plan.

Legacy renderer compatibility does not make an unpublished handle available for new authoring. Use the current catalog and field contracts rather than historical counts or implementation milestones.

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
11. Treat Card as opt-in presentation, not the default way to group related copy. Use a Card only for an independently actionable, repeatable, or bounded entity such as a product, plugin, price plan, download, or form.
12. Before choosing blocks, state one site-level design direction covering character, density, typography, geometry, imagery, corners, and contrast. Make the block tree and site CSS implement that direction instead of choosing each section in isolation.
13. Vary page rhythm deliberately. Combine narrow, regular, wide, and full-width regions; alternate quiet copy, dominant imagery, and structured collections rather than repeating equally weighted sections.

Internal Content API clients must honour both budgets advertised by `openapi.json` under `x-rate-limit`: `requests_per_minute` applies per client IP and normalized bearer token (default 120), while `ip_requests_per_minute` is an independent IP backstop across tokens and API paths (default 300). Canonical and legacy API routes share these budgets. Exceeding either returns `429` with `Retry-After`; rotating a supplied token does not reset the IP budget.

An active Super admin can manage request budgets and public search query bounds under System → Settings → Security. The eight installation-wide controls cover page and search requests per minute/hour, API requests per token-IP and per IP per minute, and search query characters/words. Saved database values override installation configuration on subsequent requests without changing server files or resetting active counters. Request budgets must be integers from 1 to 1,000,000; search query bounds are 1–2,000 characters and 1–100 words. Use installation defaults removes only these overrides and preserves existing positive environment/configuration budgets; default search bounds remain 200 characters and 20 words. Site admins, editors and inactive users cannot save or restore these settings. No Internal Content API security-settings write endpoint is introduced.

CMS browser sign-in supports optional TOTP authentication and ten single-use recovery codes per enrolled account. Profile → Manage two-factor authentication opens setup, recovery-code renewal, authenticator replacement and optional disabling. Setup uses a manually entered key in a time-based (TOTP) authenticator, with six-digit HMAC-SHA-1 codes and 30-second steps; there is no QR setup or additional Composer dependency. It requires the current password and confirmation of the generated code; replacement, recovery-code renewal, disabling and enrolled administrators changing the enforcement policy require the password plus an existing authenticator or recovery code. Recovery codes are shown only when generated. An active Super admin can require enrollment for Super admins and any built-in or custom site roles under System → Settings → Security. Any matching site assignment requires enrollment before panel access; required users cannot disable 2FA. Existing host logins, remembered sessions, session-based page previews and plugin recovery do not bypass verification. Browser writes awaiting verification return a redirect, or HTTP 423 with `cms_two_factor_required` and a verification URL for JSON requests. Bearer-token API authentication and its capability model remain independent; no 2FA secret, recovery-code or policy management operation is exposed through the Internal Content API.

## Revision Capture and Bulk Plans

Page and Shared Slot versions remain complete JSON safety snapshots. Consecutive captures of the same owner reuse the latest content version when a canonical content hash matches; root capture time and object key order do not create a content change. Block/list order and editorial/workflow fields remain significant. Every capture still records its actor, source, event, label, reason and time in a separate capture event linked to the version. Existing historical versions are not merged or deleted by this change. Explicit pre-change safety checkpoints and restore/publication checkpoints remain distinct versions.

A content plan captures one completed page state after all its page-owned blocks and Shared Slot assignments, plus one completed state per newly created Shared Slot. Existing-page assignment plans preserve one pre-change safety checkpoint per affected page. Destructive replacement and promotion keep their pre-change checkpoint. Independent HTTP requests remain separate logical operations. Existing Shared Slot assignment targets are checked against the human-only block policy during validation and again before assignment; rejected plans create no versions or capture events. Failed transactions roll back both content and capture events.

From CMS 1.103.1, page-version API operations without an authenticated delegated
user return 403 rather than a server error; system tokens do not acquire additional
revision permissions.

The page-version detail API includes bounded `inspection.capture_history` with up to 100 recent events and a `has_more` flag; access uses the existing version-read permission. Historical rows retain their original revision metadata when no capture events exist. Snapshot size metadata is measured outside ordered queries. Cleanup scans fixed-size metadata batches through an ordered continuation key, preserves the age/newest-version rules, and rechecks active restore sources and the newest-version floor under owner locks before deleting each batch.

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
| Marketing intro | `hero` | Hero supports left, centered, split, and full-bleed layouts; split renders foreground media while full-bleed creates an unframed photographic band. Report a gap when the design requires a second editable foreground image or arbitrary nested content. |
| Conversion band | `cta` | Current CTA does not accept normal structured children other than managed legacy button children. |
| Repeated feature or stat items | `columns` and `column_item` | Prefer `grid` and composable `card` when arbitrary nested content is needed. |
| Composable card | `card` plus Card regions | Use only for independently bounded/actionable content; variants are default, flat, muted, highlight, and accent. |
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
- `slide`, `image`, `hero`, `section`, `card`, `cta`, `content_header`, and
  `link-list-item` also accept an optional top-level `mobile_media_id` referencing
  an image Media Library record. It is stored in `block_media` with role
  `mobile_image`, shared across locales, and editable through the admin media
  picker and `PATCH /blocks/{block}`. On screens up to 768 px wide it replaces
  the default image; an absent, deleted, or private mobile image falls back to
  the default. Send `null` to clear it; omitting it in PATCH preserves it.
  Foreground images use `<picture><source media="(max-width: 768px)">`;
  background blocks use a responsive CSS background. Use another crop of the
  same visual: alt text, captions, position, fit, overlays, and links stay
  shared. The Image block's Gallery Viewer continues to open the default
  full-resolution image. Gallery items, brand logos, video, and audio do not
  accept this field.
- Put Gallery items in `gallery_items` or `gallery_media_ids`.
- Use only nested `children`; do not send `id`, `parent_id`, `block_id`, `slot_type_id`, or `block_type_id`.
- From 1.105.9, discovery and content-contract responses expose `authoring_settings_schema` revision 1. New or changed core settings must match the per-block field rules; unknown fields and invalid values return field-specific 422 errors. Existing descriptive `settings_schema` payloads remain unchanged. Read the operation-specific `writes` and `closed_reason` metadata before authoring.

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

The current published core catalog contains 55 rows:

| Group | Handles |
| --- | --- |
| Layout and composition | `section`, `container`, `stack`, `split`, `cluster`, `grid`, `card`, `card_header`, `card_body`, `card_footer`, `slider`, `slide` |
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
| Settings | `spacing`: empty, `sm`, `lg`; `flow`: normal, offset-up, overlap-previous; optional background `media_id`; `background_position`: center, top, bottom, left, right; `background_overlay`: soft, medium, strong, none. |
| Children | Any supported published child type; at least one renderable child is required by API plans. |
| HTML | Root-owning `<section class="wb-section [wb-section-sm or wb-section-lg] [wb-public-section--offset-up or wb-public-section--overlap-previous] wb-stack" data-wb-public-block-type="section">…</section>`. Background media adds package-owned class/style hooks. Flow modifiers reset on small screens. |
| Example appearance | A full-width themed band containing a constrained Container, or one deliberately shifted band that breaks uniform vertical rhythm. |
| Avoid | Visible text in settings, empty chrome, using Section as a card, or overlapping several consecutive sections. |

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
| Settings | `gap`: empty/default, 0, 1, 2, 3, 4, 6, 8; `items_alignment`: center/default, start, end, stretch; `width`: auto/default or full; `responsive`: stack or preserve. New admin/API blocks default to stack while existing empty settings preserve the legacy row. |
| Children | Exactly two direct children. Put a Stack inside either side when that side needs multiple blocks. |
| HTML | Root-owning `<div class="wb-split …" data-wb-public-block-type="split">…</div>` with allowlisted `wb-*` classes. Responsive stack adds package-owned `.wb-public-split--stack-mobile` and changes to a full-width column at 48rem and below. |
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
| Settings | `columns`: 2, 3, 4; `ratio`: equal, lead-left, lead-right (asymmetric ratios apply only to two columns); `gap`: empty, 3, 4, 6; `alternate_media_text_sections`: boolean; `alternate_start`: media_left or text_left. |
| Children | Any supported published child type; at least one child required by API plans. |
| HTML | Root-owning `<div class="wb-grid wb-grid-{n} [wb-gap-{n}] [wb-public-grid--lead-*]" data-wb-public-block-type="grid">…</div>`. Lead ratios render as 2:1 or 1:2 above the normal one-column mobile breakpoint. Alternating mode may change direct-child order without changing the root. |
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
| Settings | `icon_slug` from the active content icon catalog; `icon_tone`: default, soft, brand, accent, highlight, bold, quiet; `icon_size`: default, sm, lg, xl. |
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
| Settings | `height`: auto, fill, viewport, large, medium, small, custom; optional `min_height`; `aspect_ratio`: 16/9, 4/3, 1/1; `interval_ms`: 1000–30000; booleans `autoplay`, `pause_on_hover`, `show_arrows`, `show_dots`, `loop`, `swipe`, `keyboard`; `overlay`: none/default, soft, medium, dark, strong; `content_position`: center/default, center-left, center-right, top-left, top-center, top-right, bottom-left, bottom-center, bottom-right; `content_width`: medium/default, narrow, wide, full; `text_color`: auto/default, light, dark; `background_fit`: cover/default or contain. Transition is currently normalized to slide. |
| Children | Only `slide`; at least one Slide required. |
| HTML | Root-owning `<section class="wb-slider …" data-wb-slider data-wb-public-block-type="slider">` with viewport, track, optional arrows, and dots. |
| Example appearance | Full-width hero carousel, card-contained slider, or background-media panels with editable child content. |
| Avoid | Static image galleries. |

### `slide` — Slide

| Contract area | Source-backed behavior |
| --- | --- |
| Purpose | One panel inside Slider. |
| Admin-editable content | No direct visible copy; optional editor-only `layout_name` and shared `aria_label`. |
| Settings | Background image `media_id`; `background_position`; `background_overlay` (`none`, `soft`, `medium`, `strong` — each renders a distinct scrim since WebBlocks UI 2.22.0; before that `medium` collapsed onto `strong`); `content_position`: center/default, center-left, center-right, top-left, top-center, top-right, bottom-left, bottom-center, bottom-right; `content_width`; `text_color`; `background_fit`. |
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
| Editable content | `translations.content` through the safe Rich Text editor and sanitizer; editor history supports undo/redo, with a word counter and optional focus mode. |
| Settings and variants | None. Unsupported formatting, attributes, and classes are stripped; pasted headings and table cells retain their text as paragraphs, and executable/media markup is removed. |
| Children/media | None. |
| HTML | Generic wrapper plus `<div class="wb-rich-text">[sanitized editorial markup]</div>`. |
| Example appearance | Several paragraphs with safe inline emphasis, links, and simple lists. |
| Avoid | Layout markup, `<style>`, scripts, iframes, forms, buttons, tables, or a complete page. |

### `content_header` — Content Header

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` as intro, `translations.eyebrow` as optional badge label, `translations.meta` as metadata items. |
| Settings and variants | `alignment`: left, center, right; `icon_slug`; `icon_tone`; `icon_size`: default, sm, lg, xl; `badge_tone`: neutral, info, success, warning, danger; optional background image and overlay settings. |
| Children/media | No children; direct image `media_id` is background media. |
| HTML | Root-owning `<header class="wb-content-header …">` with optional icon/badge cluster, fixed `<h1 class="wb-content-title">`, subtitle, and metadata row. |
| Example appearance | Page title with optional product badge/icon, concise lead text, and two metadata labels. |
| Avoid | Nested sections where H1 is semantically wrong. |

### `hero` — Hero

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.subtitle` as eyebrow, `translations.content`. Action buttons are separate child `button_link` blocks with their own admin form. |
| Settings and variants | `variant`: default, muted, soft, accent; `layout`: left, centered, split, or full-bleed; `title_tag`: h1, h2, h3; optional background image and overlay settings. |
| Children/media | Actions are child `button_link` blocks, with no fixed count. In left/centered/full-bleed layouts `media_id` is background media; in split it renders as a foreground image beside the copy. |
| HTML | Legacy layouts own `<section class="wb-card wb-promo [wb-card-*]">`; split adds `.wb-promo--split` and `.wb-promo-media`. Full-bleed deliberately drops the card class and uses `.wb-public-hero--full-bleed` with an aligned `.wb-public-hero__copy`. |
| Example appearance | A contained promo, foreground image/copy split, or unframed viewport-wide photographic hero, plus actions. |
| Hard limitation | No second foreground image, product-price/trust-strip region, or arbitrary nested content. |
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
| Settings and variants | `settings.variant`: cards, plain, stats. New Internal Content API plans default an omitted variant to `plain`; `cards` must be deliberate. Existing stored blocks with an empty variant retain the legacy cards renderer fallback. |
| Children/media | Only `column_item`. Child count selects stack, 2-column, 3-column, or 4-column layout. |
| HTML | Root-owning `<section class="wb-stack wb-gap-4">` with optional intro and a responsive item grid. |
| Example appearance | Three benefit cards, four compact features, or a simple metric row. |
| Manageability caveat | The stats renderer can use child `subtitle` as the value, but normal Column Item admin forms do not expose that subtitle. AI-created stats that depend on it are not fully manageable and should be avoided until the form contract is aligned. |

### `column_item` — Column Item

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | `translations.title`, `translations.content`, optional `translations.eyebrow` badge; shared `settings.url`, `icon_slug`, `icon_tone`, `icon_size`, `badge_tone`. |
| Settings and variants | Presentation is controlled by the parent Columns variant: cards, plain, or stats. |
| Children/media | None; intended only under Columns. |
| HTML | Cards: `.wb-card > .wb-card-body`; plain: `.wb-icon-card`; stats: `.wb-stat`. Optional safe link wraps cards/plain output. |
| Example appearance | Icon-and-copy feature card with an optional badge. |
| Avoid | Standalone use or relying on renderer-only subtitle for a stat value. |

Use `plain` for qualities, principles, benefits, process summaries, and other copy that does not represent independent objects. Use `cards` only when every item has a meaningful boundary of its own. The number of items — especially the familiar set of three — is never by itself a reason to choose cards.

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
| Editable content | Locale-owned image `alt_text` and `caption`; shared optional URL. Content plans and page/shared-slot block creation accept these native names, with `title` (caption) and `subtitle` (alt text) aliases; native names take precedence. Admin saves and existing-block PATCH preserve omitted image translation fields; an explicitly blank or null field clears only that locale's stored value. Clearing a caption removes the editorial figcaption; the existing Media/dynamic-source alt-text fallback still applies. Clearing editorial text does not remove bindings or their explicit fallback configuration. |
| Settings and variants | `viewer_enabled` opts an unlinked image into the CMS Gallery Viewer; `viewer_group` joins independently placed Image blocks into one browsable viewer set. Focal point and generated variants belong to the Media record. |
| Children/media | Direct image `media_id`; no children. |
| HTML | Root-owning `<figure class="wb-stack wb-gap-2">` with responsive `<img>` output, optional linked or `wb-gallery-trigger` image, and `<figcaption>`. Enabled groups register one existing gallery-viewer modal under the canonical overlay root. |
| Example appearance | Product or editorial image with an editable caption. |
| Avoid | Background treatment or decorative layout HTML. Use Gallery when the collection itself should render as one grid; use viewer groups when independently composed images should share one viewer without changing layout. |

### `gallery` — Gallery

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Ordered Gallery items with locale-owned `alt_text`, `caption`, `overlay_title`, `overlay_text`; optional shared viewer title. Gallery intro copy is intentionally separate. Item translations resolve in the requested visitor locale with default-locale fallback, independently of missing Gallery heading translations. Staged creation and promotion preserve every item translation, media relationship, and position. |
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
| Children/media | Admin, Content Plan and incremental block creation/update accept direct audio `media_id` (or `asset_id`) with Media kind `other` and a valid `audio/*` MIME type; no children. |
| HTML | Root-owning muted Card with copy and native `<audio controls>`. |
| Example appearance | Audio lesson or sample player. |
| API validation | Shared media kind/MIME validation rejects incompatible or missing records before writes. Clearing the assignment remains supported; safe URL fallback is unchanged. |

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
| Settings and variants | `icon_slug`, `icon_tone`, `icon_size`, `badge_tone`. |
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
| Query contract | HTML and JSON search accept an optional text `q`, defaulting to at most 200 characters and 20 normalized words. An active Super admin can change both bounds under System → Settings → Security (1–2,000 characters; 1–100 words). Invalid type, length or word count returns localized validation JSON with `422`, including for HTML requests, using the effective bounds without redirecting to the invalid GET URL. Missing or empty queries retain the empty-search state. |
| Request budgets | Search endpoints share IP-based minute/hour limits across HTML, JSON and locales (defaults 30/minute and 300/hour). Public CMS pages and legacy page redirects share separate defaults of 120/minute and 1800/hour, including CMS pages served before a redirect plugin catch-all. Host-owned routes, static/plugin assets, robots and sitemaps do not consume these budgets. Saved Super admin Security settings take precedence over `cms.public_rate_limits`; removing overrides restores installation configuration. Invalid saved values and nonpositive configuration fall back without disabling protection. Exceeding a budget returns `429` with `Retry-After`. |
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
| Editable content | Locale-owned `title`, `content`, `submit_label`, `success_message`, `consent_label`. Consent wording is writable through content plans, page/shared-slot block creation, and flat or family-nested block translation PATCH. Omitted PATCH wording is preserved; an explicitly blank `consent_label` clears only that locale when consent is optional. Required consent refuses missing, null, empty or whitespace-only wording on editor saves, API creation and translation PATCH before persistence. Creating a new locale row requires its own notice; omitting the field preserves an existing notice. Staged creation and promotion preserve wording in all locales. |
| Settings and variants | `recipient_email`; `send_email_notification`; `store_submissions` remains product-owned true in the native contract; `consent_required` (boolean, default false); optional shared `privacy_notice_url` (internal path starting with `/` or valid HTTP(S) URL, max 2048 characters). |
| Privacy notice link | Set `settings.privacy_notice_url` independently of consent. A separate link uses the visitor locale's built-in label; internal page paths resolve through that same site's page translations, preserving query/fragment and the existing stored-URL fallback when unresolved or untranslated. External HTTP(S) URLs stay unchanged. Empty/null hides the link. Editor and API creation/PATCH validate the URL; omission on update preserves it, blank/null clears it. Nondefault-locale editor saves preserve this shared value. Unsupported schemes, protocol-relative URLs, credentials, controls and backslashes are refused; invalid historical URLs render no link. The URL uses a declared typed SQL field in live settings and complete revision snapshots and survives staging/promotion. It neither enables consent nor records acceptance or the linked page's content/version on a message. |
| Publication validation | Publishing a page, a block, page-owned blocks or a staged update checks required notices in every enabled site locale; a site with no locale assignments still checks the default locale. An absent locale row uses the existing default-row fallback; an existing blank row is invalid. Page publication checks visible published blocks and active Shared Slots; optional page-owned-block publishing also checks draft/in-review candidates. Disabled slots and unpublished ancestors do not affect page-only publication. Publishing a parent cannot expose an invalid descendant. Promotion checks selected replacement slots and the untouched live scope; failures leave source/stage/status/revisions unchanged. Active Shared Slot restoration/activation and live slot source changes use the same guard. Complete old revisions can be inspected as draft candidates, but invalid live restoration is refused transactionally. Existing broken forms can be repaired one locale at a time; enabling required consent on a published block checks every enabled locale. |
| Consent | Set `consent_required` and give the locale a `consent_label` to render a required consent checkbox. The wording is translated because it *is* the notice. An accepted submission stores `consent_accepted_at` plus a copy of the wording, so editing the block later cannot change what a past visitor is recorded as having agreed to. Required consent with missing, empty or whitespace-only wording for the resolved locale shows a localized unavailable warning and disables the submit button; the server rejects all submissions, even a forged acceptance value, before storage or notification. Message Technical details show the saved consent time and original wording, marking absent values as not recorded without inferring historical consent. `consent_required` is closed to PATCH: removing a legal notice from a live form is an operator decision. |
| Site notification policy | Contact forms and participating plugins share `Site.notification_settings`, read through `GET /webadmin/api/sites/{site}/notifications` (`content.read`) and changed through PATCH (`site-settings.write`, site-scoped). Fields: `notification_mode` (`full`, `alert_only`), `notification_frequency` (`immediate`, `batched`, `daily`), `batch_minutes` (1–60), `daily_summary` (boolean), `summary_hour` (0–23 in the site timezone). Omitted PATCH fields are preserved; invalid, null and unknown values are rejected. These are site settings, never block PATCH fields. |
| Notification privacy | `alert_only` has trusted site copy, counts and normal authenticated CMS inbox links only. No visitor name/address, subject/body, IP, browser information, source URL/referrer, visitor Reply-To, custom headers or attachments. Separate minimal mailables never receive visitor models. `full` retains detailed notifications, including detailed batches. Daily summaries always contain counts and protected links, including when the selected content mode is full. Plugins cannot override the site's privacy mode. |
| Notification timing | New sites default to `alert_only`, `batched`, ten minutes, a daily summary enabled at 09:00. Upgrade migrations preserve existing full/immediate behavior with summaries disabled. Batched contact mail sends the first alert immediately, then combines subsequent messages per site/channel/recipient. Live Chat batches per conversation so each conversation gets its first offline alert immediately. `daily` sends one combined summary per site/recipient/day; with `daily_summary` enabled, existing pending work is reminded daily. Read contact messages remain awaiting reply until replied/archived; spam/quarantined/archived and opted-out messages are excluded. |
| Delivery and plugin extension | `webblocks:notifications:dispatch` is registered every minute with the Laravel scheduler; hosts must run it and use a shared cache supporting atomic locks when running multiple workers. Enrollment stores site/channel/source identifiers and status only, with unique source keys; policy is re-read before delivery. Failures are generic and never automatically retried. Interrupted attempts become failed after 15 minutes. Terminal events and idle dispatch state are pruned after 30 days. GET exposes site-scoped message outcomes and latest daily-summary outcomes; the Site settings panel and existing inboxes show delivery state. Enabled plugins register a `SiteNotificationChannel` adapter through `SiteNotificationChannels` with its plugin handle, owning site-scoped sources, recipients, eligibility, detail mail and pending counts; disabled plugins are not dispatched. |
| Panel notifications | From CMS 1.96.0, the shared admin header links to the Dashboard notification panel using the WebBlocks UI `wb-btn wb-btn-ghost wb-btn-icon` action and `wb-btn-badge` counter. Visual counts are capped at `99+`; the accessible label retains exact counts. Site-operation managers see aggregate unread (`new`) and awaiting-reply (`new` + `read`) contact-message counts for accessible sites, plus a scheduling warning when any accessible site requires scheduled email and recorded health is unverified, delayed, failed or unavailable. From CMS 1.97.0 the header badge counts unread contact messages only; read messages awaiting reply and scheduler/schema warnings remain separate Dashboard state and do not contribute to that badge. The navigation bell is rendered only when there are unread contact messages; zero unread messages hide the entire action, while the Dashboard notification panel remains available. The site-row Actions cell uses the standard WebBlocks UI view icon with translated tooltip and accessible label explaining that it opens messages awaiting reply. Stored work is visible independently of mail opt-in, recipient configuration, delivery outcome or scheduler evidence. No visitor names, addresses, subjects, contents or IPs enter the summary. Reads never send mail, consume events, mark messages read or establish scheduler health. Counts refresh on each panel page load; this is current shared inbox state, not a per-user notification history or automatic polling. Site links use GET `/webadmin/contact-messages?site={id}`; the scoped site selector is validated and preserved in safe inbox return URLs. Replied, archived, spam and quarantined messages are excluded; missing inbox schema shows a migration warning rather than an empty inbox. Live Chat retains its separate permission- and site-scoped plugin panel indicator, which also works without scheduled email. |
| Scheduler health and prerequisites | CMS 1.95.1 records an actual every-minute scheduled callback separately from notification-command starts/completions/failures in the CMS database. Site-policy-derived `required` is true for batched/daily delivery or daily summaries. The System Health scheduling section shows the full health notice, pending-delivery impact, last scheduled heartbeat and completed notification run in UTC, server-owned setup instructions, and a guide link resolved from the configured documentation root. Its aggregate scheduling Review link targets this section and preserves the selected site filter. The Dashboard retains the detailed notice with accessible-site links for site-operation managers without System Health access, including legacy immediate-only sites. Operators with System Health access instead see a compact Dashboard summary of critical, warning, and unknown checks using the same report and cache; zero issue counts show that no issues were found in the available checks. Warning severity depends on whether any accessible site requires scheduled notification delivery; inaccessible sites do not influence it. Immediate-only policies without daily summaries retain an informational status card explaining that scheduling is optional for those notifications; Site settings and the scoped notification GET API show `scheduler_health` with overall/scheduler/worker status and UTC timestamps. Missing evidence stays `unverified`; evidence older than 300 seconds is `delayed`; processing failures and unavailable schema are explicit; a current unfinished run can be `running`. Manual dispatch and health reads never create a scheduler heartbeat. The read-only `webblocks:scheduler:status --json` command exits successfully only for verified healthy status. Scheduler execution does not guarantee SMTP/inbox delivery or health of every node. Installer and guest setup notice explain working mail, canonical APP_URL, a server-owned every-minute scheduler, and shared atomic-lock cache for multiple workers; updates and web requests never install cron; CMS 1.105.25 adds the explicitly confirmed CLI setup described below. Enabled notification plugins may consume the same `SchedulerHealth` service in settings and health checks. |
| Children/media | None. |
| HTML | Generic wrapper around native `section.wb-card`, CSRF-protected form, renderer-generated anti-spam field, WebBlocks inputs, textarea, optional consent checkbox, validation errors, and submit button. |
| Example appearance | Fully managed contact form stored in Contact Messages with optional notification. |
| Avoid | Raw form HTML, custom honeypot fields, or `mailto:` replacement. |

### `rating` — Rating

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | Optional locale-aware heading and supporting text (`title`, `subtitle`) use text translations. Legacy `settings.title` remains a render fallback; normal labels are product-translated. |
| Settings and variants | `scale`: fixed 5; `allow_change`: boolean; `show_summary`: boolean; `data_scope` (CMS 1.97.0): `block` (default, preserves separate feedback areas) or `page` (same stored site/page, survives block replacement). Both editor and block PATCH support scope. |
| Children/media | Uses `content_ratings`; no children. |
| HTML | Root-owning `<section class="wb-card">` with optional H3, partially filled `.wb-rating-stars`, summary, and no-JS `.wb-rating-input` submit buttons. |
| Example appearance | Five-star page rating with average and response count. |
| Note | Only active 5-point votes enter the public summary. Page scope keeps the visitor-session hash stable across block replacement, serializes writes under a page lock, and recognizes surviving block-scoped hashes for the current session. Orphaned legacy hashes cannot be matched or deduplicated automatically; existing rows remain unchanged until a recognized vote is updated. The form marks the current session vote with `aria-pressed` and disables repeat input when `allow_change` is false; the server also enforces it. GET does not create a visitor ID. |

### `comments` — Comments

| Contract area | Source-backed behavior |
| --- | --- |
| Editable content | No block-authored visitor copy; product translations supply labels and messages. |
| Settings and variants | `form_enabled`, `show_approved`, `show_author_name`; `sort_order`: newest or oldest; `data_scope` (CMS 1.97.0): `block` (default) or `page`. Page scope includes approved records with the same persisted site/page, including records whose original block was deleted; neighboring pages and sites remain excluded. |
| Children/media | Uses moderated `comment_entries`; no children. |
| HTML | Root-owning `<section class="wb-card wb-public-comments">` with separately labeled Comments/list and Leave a comment/form regions (CMS 1.97.1), localized product headings and an approved-count badge. Author/date share a wrapping metadata row; escaped multiline text uses compact spacing. The form has a theme-aware muted surface, a three-row textarea, and a compact name field that expands on phones. Retains 25-per-page pagination (`comments_page_{block_id}` with fragment and query preservation), native CSRF protection, anti-spam fields, targeted validation state, and submit action. List and form visibility remain independent. |
| Example appearance | Moderated comments below an article or product guide. |
| Avoid | Custom comment storage or raw form markup. |

### Engagement administration

Engagement uses authorized site-scoped aggregates and a sortable, paginated page summary. The all-time pending-review queue and its oldest submission are distinct from new submission counts for the last 7 and 30 days. Ratings mean and distribution include only active 5-point votes; saved-record totals can include inactive or other-scale rows and do not represent unique people. Comments and Ratings share labeled site/page/date/search filters; Comments adds localized status filters and counts, while Ratings adds a value filter and separate submitted/updated dates. Ratings remain read-only with a public-page view icon. Full escaped comment content and spam reasons are available through a policy-guarded detail page. Standard WebBlocks UI action icons expose applicable moderation transitions; selecting comments supports up to 100 per atomic status mutation with policy checks for every record before any update. Mutations retain listing filters and pagination, with the shared out-of-range redirect handling a reduced result set. Reading panels does not moderate submissions or send mail, and needs no scheduler. Staged previews use their own persisted page identity; previously collected page-scoped results reappear after promotion onto the original page. Moderation permissions do not imply publication of a page.

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

Start from the least framed recipe that satisfies the content. Do not repeat the same recipe in adjacent page bands, and do not select the feature-card recipe merely because the source contains three short items.

### Editorial introduction with foreground media

```text
section(spacing:lg)
└── container(width:xl)
    └── hero(layout:split, foreground media)
        ├── button_link(primary)
        └── button_link(secondary)
```

Use a large, meaningful image and restrained surface styling. Choose `layout:full-bleed` when the image should become a viewport-wide, unframed opening band; retain `split` when the image is semantic foreground content.

### Unframed principles or benefits

```text
section(spacing:lg)
└── container(width:xl)
    └── columns(variant:plain)
        ├── column_item
        ├── column_item
        └── column_item
```

This is the normal starting point for qualities such as experience, communication, care, speed, or reliability. Promote it to Cards only when the items are independently actionable or bounded.

### Marketing page intro with separate actions

```text
section(background optional)
└── container(width:lg)
    ├── hero(variant:accent, layout:centered)
    └── cluster(alignment:center, gap:sm)
        ├── button_link(primary)
        └── button_link(secondary)
```

Use this only when the action row should sit outside the Hero promo root. Hero itself accepts Button Link children in every layout, including split; keep actions inside Hero when that is the intended composition.

### Bounded entity card grid

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

Every title, paragraph, and action remains independently editable. Reserve this recipe for bounded entities such as products, plugins, plans, downloads, or services with their own actions. Use site CSS for a consistent site-specific Card skin through stable hooks; do not inject card HTML.

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

## Supported Limitations

The footer uses normal layout and navigation blocks; a dedicated footer-composition block is not required or implied by WebBlocks UI class names. Use documented native blocks for shared footers.

Prose descriptions of appearance are not approved screenshot references. Confirm responsive output and visual behavior in the target installation before promising fidelity to a design.

Discovery describes the installed authoring contract. It does not certify an external provider, future plugin capability or visual output that has not been reviewed.

## Related Detailed References

- `webblocks-cms-docs/docs/ai-page-building-guide.md`
- `webblocks-cms-docs/docs/internal-content-api.md`
- `webblocks-cms-docs/docs/api-discovery.md`
- `webblocks-cms-docs/docs/block-type-contracts.md`
- `webblocks-cms-docs/docs/public-block-render-markup.md`
- `webblocks-cms-docs/docs/block-ui-renderer-contract.md`
- `webblocks-cms-docs/docs/public-theme-and-tones.md`
- `webblocks-cms-docs/docs/public-assets.md`
- `webblocks-cms-docs/docs/media-image-variants.md`

This inventory should be the first document an AI reads for page-design capability selection. The detailed references remain useful for endpoint workflows, historical compatibility, and full renderer notes.


## Plugin startup and recovery contract

Catalog and ZIP installation, updates, and panel/API activation validate plugin source,
provider startup, commands, and routes in a fresh PHP process through `cms:plugin-probe`.
Validation is required even when no migration is pending. A failed or timed-out probe
leaves the current package active; subprocess diagnostics are not exposed in responses.
The default startup timeout is 30 seconds (`webblocks-plugins.install.boot_timeout_seconds`).

Enabled CMS-managed Laravel service providers are registered and booted once per
application after the host boot completes, including when routes are cached.
Provider bindings, notification channels and view composers therefore participate
in normal HTTP and console requests. Disabled, incompatible and recovery-suppressed
plugins do not start. Catchable provider startup failures quarantine the plugin
without exposing its diagnostics.

Successful updates retain the previous package and record whether migrations ran.
Database setup failures leave the plugin disabled and preserve its tables and packages.
Runtime source/route failures quarantine the plugin; an explicit disable overrides
configuration-based enablement. Lifecycle JSON records use atomic replacement.

`/webadmin/plugin-recovery` and its login form load without installed plugin source,
routes, or commands. Existing CMS login controls, active admin-access and Super admin authorization, and CSRF
protection apply. Recovery can disable a plugin or restore the retained package when
no migration ran, after another startup probe. A restore also republishes its assets.
This is recovery for CMS-managed packages; it does not isolate arbitrary host providers
or sandbox executable PHP. Uncatchable process termination still requires the separate
recovery request rather than an in-process error handler.


## System Health read authority

`GET /webadmin/api/system/health` requires the opt-in `system-health.read`
capability and an installation-wide system token (`allowed_site_ids: null`)
owned by an active operator with `access-system`. Personal and site-scoped
credentials cannot read installation health. An optional validated `site_id`
filters site checks while installation-wide checks remain visible.

The endpoint returns safe message keys/parameters, ordered issues, category
statuses (`healthy`, `warning`, `critical`, `unknown`, `not_applicable`), site
summaries, system information, and recent operation outcomes. The live `scheduler`
object includes controlled runtime states, UTC execution timestamps, the stale
threshold, and `required` derived from the filtered sites.
Site, backup, storage, plugin, update-readiness, and history observations are
cached for five minutes; scheduler evidence is read on each request. Unknown
and optional checks remain distinct from successful checks. Reading or
refreshing health does not mutate content, reconcile backup records, send mail,
run cleanup or updates, fetch release metadata, or create scheduler evidence.
Plugin reporters retain their existing health-reporter contract; raw reporter
messages and exception details are excluded from this overview.

Existing operational routes remain available. The core sidebar group is labeled
Maintenance, with System Health as a child destination. Plugin Help destinations
join System and plugin Maintenance destinations merge by the stable maintenance
group key; the core Help item links directly to documentation. System Information
has no separate screen or sidebar destination; all nine non-sensitive runtime
fields remain in System Health's System details table. The legacy named route
and URL redirect to System Health under the same system-access middleware.
Health status is
evidence for review, not authority to publish content, restore a backup, or update a host.
Search checks compare eligible published page/locale scopes with index rows;
they do not prove text freshness. Backup availability does not prove restore
integrity. Update readiness remains separate from normal site operation.

## Relational runtime fields and compatibility

CMS 1.98.5 stores page layout/shared-slot references, staged-update and restore
markers, source-sync fields and managed-slot lists in relational tables. Block
application references, token capability/site/network lists, site notification
policy and scheduler health also use SQL as their authority. The existing API
field names, nested structures, list order and null/empty distinctions are
unchanged. Existing JSON fields remain compatibility copies during this
transition; they are refreshed from authoritative values on supported model
writes. In 1.98.5, unmapped block settings and full revision snapshots retain their
existing storage contract for later migration. Historical malformed values and
unresolved references are preserved, rather than silently normalized or dropped.

From CMS 1.98.6, remaining Block settings use relational records. Core appearance,
media, navigation and content fields have finite typed column schemas; content
bindings, collection configuration and application instance values have their own
records. Typed nodes preserve document structure, key/list order and historical
or plugin extensions, including malformed values, without storing a JSON document
in a new column. Collections batch-load these records rather than issuing a
settings query for every block.

Embedded Application CSS/JavaScript declarations, supported context flags,
setting field definitions/defaults/bounds and enum options also use relational
records. Existing API names and structures, ordered duplicate values, legacy
script strings and omitted/null/empty distinctions remain compatible. Existing
JSON columns are synchronized rollback copies. Update migration rollback retains
SQL data; drift written by an older package must be reconciled before returning
to SQL authority. Full revision snapshot and remaining legacy content/runtime
storage are outside this phase.


CMS 1.98.7 prepares the remaining persistence in SQL: page/slot/layout settings,
block and translated metadata, full page/shared-slot snapshots, import/export
summaries and manifests, import continuation maps, backup-restore data, update
request receipts, icon lists and moderation reasons. Snapshots remain independent
complete restoration documents, represented by historical typed records and
ordered structure nodes. Historical identifiers are independent of live content
lifetimes. Existing wire field names and nested serialized settings remain intact.
Plugin lifecycle state and promotion plans now write SQL; plain emergency disable
markers remain available if the database cannot be reached. Legacy files are
imported once, preserving newer SQL changes on repeated migrations.

This is the preparation bridge for column retirement. The previous updater's
in-flight API request still needs its physical result column to finish, so 1.98.7
retains physical copies. Runtime adapters also support their absence on the next
update. Update History displays duration, and the update modal shows elapsed time
and the active operation. These observations do not initiate updates or grant any
new API capability.


CMS 1.98.8 completes the cutover after the 1.98.7 preparation bridge. Remaining
persistent JSON columns, encoded metadata/continuation fields, imported mutable
plugin/plan files and the scheduler copy are removed. New and existing records
use SQL, including complete independent page and shared-slot snapshots. Virtual
model attributes preserve the existing API and transfer contracts. Known runtime
references and core settings continue using their dedicated relational tables;
ordered nodes preserve legacy shapes and extension values. Snapshot byte counts
measure the logical restoration payload, excluding SQL row/index overhead.

In-app updates to 1.98.8 require CMS 1.98.7 or later. Earlier installations receive
the preparation bridge first. Full pre-update backups remain necessary for
rollback to a package that requires the removed columns/files; reverse migrations
retain SQL data instead of recreating empty compatibility copies. Immutable
package/backup/export formats and API serialization remain protocol contracts.


## Reliable System Update Tracking (1.98.9)

Panel updates are admitted as typed SQL operation records and executed by a detached
CLI worker. The admission pins the release version/checksum and rejects overlapping
or repeated execution. The Internal Content API retains its synchronous receipt
and authorization contract. No content-authoring or snapshot format changes occur.

The modal tracks the operation identity, elapsed time and most recent phase,
reconnects after transient HTTP/transport failures, and resumes on returning to the
screen. Only a terminal server result ends tracking; success also requires the
installed target version. Missing/stale worker evidence is **Status unconfirmed**,
not proof of success or failure. Historic unfinished runs remain stored unchanged
and are shown as unconfirmed when no current run heartbeat identifies them.

Only the existing authenticated, system-authorized status route is exempted from
rendered maintenance mode. Public pages and other admin paths remain in maintenance.
Workers require a POSIX host with a CLI PHP executable and process execution;
launch failures are recorded and shown. Large backfills have a configurable bounded
one-hour subprocess limit (`WEBBLOCKS_UPDATES_COMMAND_TIMEOUT`). The first upgrade
from an older package still starts with that package's updater; installing this fix
does not retroactively replace a running older HTTP request.

## Plugin dependencies and member access — CMS 1.99.0

New plugin manifests may declare `dependencies.required` and
`dependencies.optional` as plugin-handle to Composer-semver constraint maps.
Legacy `requires` entries remain advisory. Required dependencies are enforced
transitively at runtime and before activation. Cycles and incompatible provider
versions are rejected. Missing or disabled optional integrations do not deactivate
the consumer. Summaries expose `dependencies`, `dependency_problems`,
`optional_dependency_problems`, `dependents` and active `service_contracts`.
The plugin detail screen shows both declaration kinds and unavailable integrations.

Normal disable/uninstall operations reject providers used by configured required
consumers. Updates and recovery activation check incoming version constraints as
well as the candidate's requirements. Recovery disable and automatic quarantine
can force a safe stop; dependent consumers then become inactive. Lifecycle state
mutations share a dependency lock. Candidate checks use manifest metadata without
executing replacement code, including before running activation migrations.

A plugin definition can expose a core-owned PHP interface through
`services([Contract::class => Implementation::class])`. Consumers resolve it at
call time through `PluginServiceRegistry::resolve(handle, contract, constraint)`.
Only a compatible active provider can supply the implementation. This works with
ZIP-installed plugins through their static definition; it does not require a
Composer provider boot side effect. Service discovery does not grant API token
capabilities or bypass plugin/site authorization.

`MemberDirectory` is the first shared visitor contract: current site-scoped
`MemberIdentity`, active package membership and site package options. Visitor
accounts and membership grants belong to the separate Memberships plugin; CMS
administrator accounts remain distinct. The first plugin stage supports verified
accounts, free self-enrollment and manual membership periods; no recurring billing
or payment-provider integration is included.

CMS page access requirements use the relational `wbcms_page_access_rules` table.
No rule means public. A rule with null package_id requires a verified site member;
a non-null package_id also requires an active grant for that site/package. Missing,
disabled or quarantined membership providers deny protected content. Protected
HTML responses are private/no-store and noindex/noarchive. Responses that include
logged-in member form data are also private/no-store. Public search indexing and
queries, sitemap, page-list blocks, fallback related-page lists and automatic
navigation exclude protected pages regardless of visitor membership.

Access rules are security policy outside content snapshots: restoring an older
content revision leaves current protection intact. Page duplicates, revision
candidates, staged updates and site clones carry protection forward. Site export
includes an `access_rule` projection and exports protected pages as drafts for
older consumers. Import/promotion preserves protection and does not treat foreign
package IDs as local authorization: a package requirement becomes unresolved
(package_id 0) until the operator explicitly selects a local package. Import does
not loosen an existing target rule. Shared Slot source pages cannot be assigned
member access rules.

Page protection does not revoke URLs of files already stored publicly. The
Memberships private download endpoint checks a published protected page, current
member access, a published block's media association, private media visibility and
private filesystem visibility. Confidential files must use a non-public storage
disk. Public media and confidential downloads remain separate authoring choices.

Appointments can declare Memberships as an optional integration. Its service
fields `members_only` and nullable `required_package_id` default to guest access;
restricted services deny bookings and slot discovery without the matching member
rights. Verified member bookings retain nullable `member_id` plus the existing
customer snapshot. Operator manual bookings retain their existing authority.

## Shared payment contracts and update recovery — CMS 1.100.0

`PaymentProviderDirectory` is a provider-independent service contract for safe
provider metadata, authenticated API requests and signed webhook verification.
`PaymentWebhookListener` lets another active plugin consume payment notifications.
The contracts contain no credentials or subscription storage. Commerce supplies
its existing PayPal/SumUp configuration; Memberships owns plans, consent, payment
attempts, subscription periods and renewals. Both integrations remain optional for
free/manual accounts. Paid package rights use verified, non-voided payment periods
through the existing `MemberDirectory`, including Appointments and protected pages.
Approval, a browser return, and reimbursed card setup are not paid access evidence.

The updater retains the previous package tree until the complete operation,
including migrations, succeeds. Automatic rollback restores package files along
with the pre-update database/uploads, removes tables introduced by that attempt,
and preserves the current operation, receipt and phase durations across the
restore. Rollback refreshes restored PHP timestamps; terminal status polling reads
the canonical version source and invalidates stale package opcode entries in the
web process when its loaded version differs. History reports the real outcome and
duration, including a failed migration followed by restoration.

Composer subprocesses obtain a private local Composer home if a cleared web
process supplies neither HOME nor COMPOSER_HOME. Pending historical migrations on
fresh installations recognize already-prefixed engagement tables and do not depend
on the retired license-promotion helper. SQLite backups serialize multiline scalar
text as one SQL expression, preserving update logs with quotes and semicolons.
These changes do not change block authoring, full revision snapshots, API token
capabilities or the maintenance exemption for the authorized status route.


## Dynamic Content authoring workflow

`/webadmin/dynamic-content/use` connects an existing standard-compliant provider
source to an authorized site/page, compatible loop container and direct-child
template. It does not register arbitrary API endpoints or credentials. Provider
installation and configuration remain integration responsibilities. Page-owned
slots support a starter Grid/Card template without overwriting existing blocks.
Shared Slot source pages remain managed through their existing block editor.

Collection and binding validation is shared with API authoring. Invalid source,
record, field type, template ownership or loop context must fail before a write;
only an explicit empty selection removes a binding. A current-record binding is
available only in the selected template branch. A loop cannot be changed or
removed while leaving current-record fields orphaned. Existing provider settings
and binding fallbacks survive unrelated updates.

The workspace edits draft pages only. Published pages can prepare/reuse a staged
copy with existing CMS staging metadata, media, translations and remapped loop
references. The source page is unchanged. Draft saves are transactional, capture
a complete revision and reject an outdated content fingerprint. Publication and
staged promotion remain separate approved operations. This workflow does not
change automatic-detail routing or automatically publish a page.

Supported blocks expose a dedicated Dynamic Content tab. Binding controls
separate editorial fallback, current loop record, and fixed source record, then
source/record/type-compatible field. The tree and central mapping groups link to
the relevant authoring context. Inventory filtering includes publication state.

An explicit authenticated POST preview applies proposed mappings in memory and
renders the actual block tree. It shares collection filter/sort/limit/pagination
semantics with public rendering, uses the selected enabled locale, checks source
contracts and distinguishes empty, denied, invalid and unavailable results.
Preview responses are private/no-store/noindex; they do not persist content.
Private preview pagination posts the same unsaved design to the next window;
ordinary public pagination retains its existing GET links.
Image source, alt text, optional caption and link retain independent mappings.
The inventory stays passive; the editor's record-choice lists may consult entity
providers, and the explicit preview resolves current records. Providers retain
responsibility for bounded transport timeouts and six-language record content.

## Dynamic Content source standard and inventory — CMS 1.101.1

The native admin entry is `/webadmin/dynamic-content`. Sources, loops, field
connections and issues are read-only projections of existing relational block
settings, including Shared Slot source pages. Site access is enforced before
projection. Opening the inventory or block outline does not fetch provider
records. Links open the existing page-slot or Shared Slot block editor; the
inventory has no independent write state. Counts describe stored block
configuration, not live provider record totals or generated cards.

Source standard 1.0 is declared with
`ContentSourceDefinition::standard($locales, $description, $origin, $recordKey)`.
The provider must support every `AdminLocaleResolver::SUPPORTED_LOCALES` code:
`en`, `de`, `tr`, `es`, `it`, `fr`. Translations belong to the provider. CMS does
not create or maintain record translations. The immutable descriptor is exposed
as `standard` in Internal Content API source discovery, with `capabilities`
reporting collection query execution in CMS or provider code, together with
`standard_status` and `required_locales`. Installed plugin manifests may declare
these values under each `content_sources[].standard` with `version: "1.0"`.

Every standard-1.0 record must return `_locale` exactly matching the requested
render/preview locale, a nonempty stable string record key, and each declared
field (explicit null is allowed). Text, rich-text and URL values are strings;
CMS media references are positive integer IDs. URLs must be internal paths,
fragments, or HTTP(S) URLs, never protocol-relative or executable schemes.
A collection must not return duplicate record keys. Schema/language errors use
existing resolver-error handling; no silent language substitution is performed
by this contract. Discovery labels denote a provider declaration, not a live
availability or translation-quality certification. Undeclared values remain
excluded from API previews.

Unversioned providers remain readable as legacy and are shown as requiring a
standard update. New connections require standard 1.0; an existing legacy
selection may be saved unchanged, but cannot be assigned to a new block. Disabled and missing sources retain stored usage references.
The compatibility path does not certify legacy language coverage or rewrite
records, media, translations or bindings. No automatic record-detail routes or
new page-publication lifecycle is introduced.

Block trees expose loop owners, repeated template IDs, current-item and fixed
record field connections, and collapsed ancestors containing dynamic content.
The requested block translation locale is authoritative for source resolution,
including when editorial fallback or a slot owner uses the default language.

Authoring choices and collection previews use the requested block language, or
the CMS editor language when the block has no public render context yet. This
keeps locale-aware providers selectable in the panel and Internal Content API.


## Automatic Pages (CMS 1.103.0)

**Dynamic Content → Automatic Pages** configures a site-owned detail-design page,
optional catalogue page, collection source, canonical non-reserved base path and
optimistic revision. Rules persist relationally in `wbcms_automatic_page_rules`;
states are draft, active and paused. Saving draft configuration and private
previews never publish routes. Active/paused configuration is immutable.

### Provider publication and routing contracts

Standard-1.0 collections optionally declare
`publishesPages($entitySource, $slugField, $titleField, $descriptionField, $imageField)`.
The entity is enabled, standard-1.0, in the same plugin with the same stable key;
slug/title are text, description text/rich_text and image URL/media. Metadata is
exposed as `publication`; `capabilities.automatic_pages` means a declaration,
not certified live availability.

`ContentPublicationSource` provides `publicationPage(?cursor, limit, context)`
and `publicationRecord(key, context)`. Enumeration returns up to 100 unique
nonempty `{key,label}` identities per page; panel requests 20. Keys are at most
190 bytes, labels 300 characters, cursors 512 bytes; continuation needs entries.
The complete transition and sitemap reject duplicate keys/slugs and cursor
cycles and do not use block limits/filters. No stale success or hidden fallback
copy may be returned. Records distinguish available, missing, unavailable and
incomplete. Every public render validates actual source schema and requested
`_locale`, identity and nonempty title in all six CMS languages. Slugs are
lowercase hyphenated, at most 190 bytes, identical across languages.

Public routing additionally requires `ContentPublicationRouteSource`, extending
the publication interface with `publicationKey(slug, context): ?string`. It
resolves the current stable key for an exact public slug without enumerating the
catalogue. Null means missing/withdrawn; upstream errors throw. Public contexts
have preview=false and actor=null, and both source policies must allow every
required locale in the same-site design context. Provider translations, images,
alt text and captions remain authoritative; the CMS does not fabricate them.

### Designs, preview and transition review

A design is a visible, same-site native page with every enabled site language
and entity field bindings. Shared Slot source pages are excluded. Ownership and
visibility are revalidated on every read. The form lists blocks, target fields
and source fields. Private authenticated previews use the existing native page
presenter and shell, preserve per-record existing designs when present and only
supply page-owned entity bindings with transient verified record data. Current
item loops, other sources and Shared Slot content retain their behavior. Locale
switching keeps the selected record. Canonical and alternate links use transient
translation identities; no stored path or translation is rewritten. Private
previews are noindex,nofollow and private,no-store.

**Review transition** enumerates current public records, their six-language
readiness, preserved native page IDs, exact paths and individual locale previews.
It refuses unavailable routing providers, protected/incomplete designs, duplicate
or unmatched native paths, missing native translations and overlapping rules.
The review digest includes source values, rule revision, design and complete
native page snapshots. Activation rechecks it and locks the site/rule/pages;
stale source or content refuses the operation with 409 rather than overwriting.
Editors may inspect site-owned rules; only Super admin or a site administrator
with site access may activate or restore the previous publication state. The
activation and pause endpoints use dedicated authorized FormRequests. There is
no Internal Content API rule-write endpoint.

Activation captures complete independent native page revisions and records
relational adoptions in `wbcms_automatic_page_adoptions`. Existing detail pages
become draft but all blocks, assets, addresses and six-language translations
remain intact. Adopted records use their individual retained design; new records
use the selected shared design. The catalogue remains native. No native detail
page is created for a new source record.

### Public resolution and discovery

Only active rules own the matching site/base-path detail range. Host Laravel
routes retain normal precedence and existing CMS middleware/quotas apply. The
site resolves from the incoming host, and an unknown host cannot bypass that
resolver. From CMS 1.103.1, the Redirect Manager catch-all bridge recognizes active
automatic detail ranges as CMS-owned and applies the shared public-page limiter
before resolving source data; withdrawn records cannot fall through to a redirect
or a retained native page. Public rendering includes published blocks and published Shared Slot
content only and rechecks access/source policies. Missing/withdrawn records
return 404; source failure/incomplete records return 503. The retained native
page never becomes a fallback for an owned path. Public automatic responses are
private,no-store. Protected designs cannot appear in sitemap discovery.

Sitemaps merge current validated automatic record URLs and all enabled-language
alternates outside the native page cache. They deduplicate native addresses,
remove withdrawn records immediately and return 503 on source failure rather
than serving stale source entries. Native HTML and JSON search also merge current
automatic records with native page results, using only declared publication
title, description and public URL in the requested enabled language. Both source
policies and all six actual translations are validated in a public context with
no admin actor. Protected designs and inactive rules are excluded. Active owned
prefixes suppress native search copies even if republished. Matching uses all
normalized query terms with Unicode case folding and Turkish dotted/dotless
I case pairs for Turkish source metadata; relevance prioritizes title, URL, description and then
multi-field matches, with native recency/title order winning equal-rank ties and
stable title/URL order for source results. One result count and paginator cover
both result types without loading preceding native result pages. Source results
are read per request with no persistent source search index or stale fallback:
withdrawal and republication apply on the next search; unavailable or incomplete
source content returns 503. The transition review explains this discovery effect.

**Restore previous publication** first verifies all retained content hashes,
restores original native publication statuses and pauses the rule in a locked
transaction. It never overwrites intervening content edits; drift returns 409.
Complete revisions and adoptions remain available. Adoption-linked revisions are
excluded from maintenance pruning. Retained designs cannot be deleted or moved
to another site while their adoption is retained; these operations return an
explicit validation message instead of a foreign-key error. A paused rule may be reviewed
again and activated only after a new valid digest; its configuration remains
immutable. This is an explicit publication operation, never automatic recovery.


### General page preview and translation-record labels (1.104.1)

The browser admin page preview accepts an optional `locale` code. A specified
locale must be enabled globally and for the page's site and have a page
translation; invalid, disabled or missing choices are validation errors rather
than silent default-language previews. Without a locale it retains the default
translation. The selected locale is passed explicitly to the public presenter
for page identity, page-owned and Shared Slot blocks, image text and existing
dynamic bindings. Preview includes draft page-owned blocks, retains existing
access checks and noindex output, and never saves or publishes content. Page
translation rows, their edit forms and slot block editors link to this localized
preview. Dynamic Content's configured preview and automatic-page routes are
unchanged.

The page translation table reports that a page translation record exists. This
is a title/routing record, not evidence of complete or current block/provider
translations. API translation-status fields and fallback behavior are unchanged.


## Shared Publisher Client progress (1.104.3)

The embedded Client 1.2.0 supplies the shared release-detail normalizer, phase reporter contract and external versioned modal asset. CMS binds the reporter to its existing SQL operation and step records; the Client file journal is not used for CMS operations. The modal now lists all reached steps with their durations and explicit recorded failure state; a nullable step-state migration is additive and repeatable. Existing admission, backup restoration with receipt preservation, role/API capability checks and installed-target verification remain authoritative. Structured notes suppress their raw duplicate and repeated summary/group items; detailed procedures remain in the Updates handbook. No public authoring fields, block schemas, rendering contracts or content API permissions change.

From CMS 1.105.15, the embedded Client 1.2.1 progress adapter opens the modal once and keeps the displayed elapsed time continuous across asynchronous server admission, later status samples and read-only rechecks. The compact modal uses the native WebBlocks UI indeterminate progress bar, with the current phase above and elapsed time below; no completion percentage is inferred from steps. Recorded steps and durations remain in the SQL receipts and progress payload. Unknown, unavailable or mismatched installed-version status hides unconfirmed activity; terminal/interrupted monitoring stops the timer. Refreshing a tracked operation adopts its server elapsed time without issuing another update POST. The corrected asset uses a new immutable versioned URL. No persistence, authoring, authorization, update verification or recovery contract changes.

## Unfinished editorial work recovery (1.105.5)

Existing draft and staged pages support recovery of unfinished page settings and page-owned block forms, including locale-specific Rich Text and selected media references. Checkpoints do not save editorial entities, create revisions, change workflow, modify Shared Slots or publish. New unsaved pages, direct published-page editing, Shared Slot forms, file uploads and credential fields are excluded. Normal validation and explicit Save remain mandatory.

Recovery headers, ordered scalar form values and typed document nodes are SQL records, independently keyed by owner, site, page, optional block, content locale and editing session. Structured settings text is decomposed into typed nodes rather than a packed JSON checkpoint. Limits are 32 active sessions per user, 512 controls, 256 KiB of decoded field names/values and 4,096 structured nodes per checkpoint. Updates expire after seven days; daily scheduled pruning and `webblocks:editor-recovery:prune` remove expired records and children. Cleared markers prevent delayed requests from recreating discarded or successfully saved checkpoints during their remaining lifecycle.

The panel reports pending, confirmed or unavailable recovery, then offers Restore, Discard or Continue with saved content. Continuing retains the earlier checkpoint and pauses new checkpoint writes until that checkpoint is restored or discarded. A browser tab lock and a tab-local scalar session pointer separate editing sessions; browser storage contains no form content. Initial network failures are retried, while only the last confirmed values are guaranteed after an interruption. Restore rechecks current account activity, site, operation grants, locale and workflow. Shared ACL locks and post-lock authorization protect checkpoint writes, restores and recovered saves from revoked authority. A fingerprint of saved page identity and its revision snapshot detects changed content. Conflict restoration requires explicit review; normal Save checks the current fingerprint again.

A one-use, one-minute restore intent applies form values only to the authorized editor GET request, without placing them in shared cross-tab session flash. Requests use the existing session/CSRF boundary and private no-store responses. Successful Save clears only the matching form/session in its editorial transaction; failed Save retains recovery. Restored media IDs display current authorized names/previews, including empty selections. Absent dynamic bindings remain literal; incomplete existing bindings are still shown and rejected by the existing validation rules.

## Personal Pages and Media views (1.105.6)

The browser admin supports private named views for Pages and Media only. Each
owner may retain up to 50 views per listing, with unique names of at most 80
characters. Relational view headers, allowlisted scalar filter rows and ordered
column rows persist independently of sessions; no editorial record, revision,
workflow or publication state is changed by view management. User deletion
cascades to the owned views. There is no shared or automatic default view.

Pages retain site scope, search, workflow status, sort and direction. Media
retain folder, search, kind, usage, sort, direction and list/grid mode; its
existing authorized media union remains the scope, without a new site filter.
Applying a view starts at the first page and rechecks active account, ownership,
listing access and any saved site reference. Deleted site/folder references and
unsupported or removed filter/column definitions block results and require
explicit correction or a return to Standard view; they are never silently
dropped to broaden the query. Renaming preserves the saved configuration;
updating explicitly replaces it from the currently applied configuration.

Page optional columns are ID, public/preview link, total blocks, status and last
edited. Media optional columns are preview, folder/kind, usage and updated time.
Each may be hidden or selected once in a chosen order. Selection, row identity
and permitted actions remain fixed and available. Grid cards retain their
standard layout. Native query results, counts, pagination and bulk authorization
remain authoritative. Applied changes show whether they differ from the selected
view, while unapplied form edits require Apply before a view is saved. Clear
Filters and Standard view clear the view selection and custom columns; Pages
retain their existing site-context rules. Pagination, list/grid/folder links,
details and safe return URLs preserve the current view and column context.

Personal view management uses the native WebBlocks UI accordion, compact name
and action rows, and responsive column controls. Pages use the same localized
labels in the column chooser, table headings and corresponding sort choices.
Page, ID, total blocks, status and last edited support server-side heading
sorting; ID and total-block sorting can also be retained in a personal view.
Created time and slug remain additional sort choices even without standalone
table columns. Selection, public/preview links and actions are not sortable.

## Admin list lookups and bounded page choices

Admin schema, system-membership and permission-site inputs are shared only
within the current request/job and database connection. Account activity and
the active permission catalog are still checked by each authorization call.
Database writes, DDL and transaction rollback invalidate these inputs; no
permission decision is retained across requests. Returned site-ID collections
and database domain models are independent copies. Loaded/unsaved domain
relations retain their existing override and active/legacy fallback behavior.

The Blocks list page filter offers 50 recent/matching accessible pages per
window, plus the currently selected accessible page when outside that window. GET
`page_search` accepts at most 200 characters and searches translated page name,
path, slug or an exact page ID (optionally prefixed with `#`). Apply refreshes
the choices; only selecting `page_id` restricts the block results. Site scope,
selection, search, sort and pagination remain explicit URL state. Choice
pagination uses `page_options_page` independently of the block table's `page`;
out-of-range windows return to the last available window. This is a
read-only native panel form, not an Internal Content API authoring field.

The Sites index loads export page choices only when the selected site's export
modal is opened, preserving archived defaults and exclusions for source pages
and revision candidates. List deletion availability uses bounded page/block
message references; the selected site's confirmation report and transactional
delete guards remain authoritative. Media list usage is resolved in batches
for visible media, preserving source order, branding precedence and existing
site-read visibility. No new persistence or public content field is introduced.

## Private editing preview (1.105.7)

Existing editable draft and staged pages offer an optional adjacent preview on
page settings and existing page-owned block forms, including the slot block
modal. The explicit Refresh preview action overlays only that active supported
form on a detached graph of the saved draft. Native FormRequest normalization,
translation payloads, authorized media selection, PublicPagePresenter and the
public Blade renderer remain authoritative. No editorial row, recovery record,
revision or workflow is saved by rendering; staged identity and the published
source remain unchanged. Published pages must enter the existing staged workflow
first. Recovery checkpoints continue independently under their existing contract.

Session/CSRF protected POST `/webadmin/pages/{page}/editor-preview` checks active
account, current site read and draft-edit grants, page-owned block identity,
editing-locale translation authority and enabled render locale. It rejects Shared
Slot sources, revision candidates, published pages and foreign blocks. Encoded
native controls are transient, limited to 512 controls and 256 KiB; sensitive
keys remain excluded. Missing saved page translations are explicit errors rather
than silent language fallback. Block translations retain native fallback rules;
a form’s editing locale never becomes the selected render locale implicitly.
Responses containing HTML are private,no-store and noindex,nofollow.

The panel identifies page/workflow/block/editing locale, offers site content
languages and 1024 px desktop/390 px phone widths, reports pending and failed
renders and retains the last successful timestamp. It never fetches on typing or
opening; refresh and configured collection pagination are explicit. Aborted or
older requests cannot replace newer results, and validation/network/session
failures retain form values and the editor position. Narrow screens retain the
saved separate-tab preview; it does not contain unsaved form values.

Configured Dynamic Content uses native bindings, query/filter/sort/limit,
pagination and empty/error behavior. Within one editing render, provider results
and failures are memoized in request memory, with at most 64 unique resolutions;
no private result enters a shared cache. Incomplete, unavailable or denied sources
mark the render incomplete without exposing exception details. No provider or
route publication contract changes.

HTML is displayed in an opaque iframe sandbox with a nonce-only script policy.
Authored scripts, embedded frames, navigation and form submission are disabled;
a dedicated external bridge permits only validated native collection-page keys.
The preview is a visual render, not an interactive public application. Existing
gallery/inline child-builder/plugin/Embedded Application forms, new blocks,
file uploads, and changes to block type, slot, parent, order or status require
Save and the saved preview; limitations are explained instead of silently
presenting those edits as current. All new panel copy covers en/de/tr/es/it/fr.

## SQL roles and operation permissions

The panel supports protected built-in Site Admin and Editor compatibility roles and custom SQL roles. A user has one role per assigned site; Super Admin is a separate protected system membership. Custom roles are managed only by active Super Admins through `/webadmin/access/roles`; `/webadmin/access/inspect` displays effective grants for a selected user/site. Role changes use version checks, assigned roles cannot be deleted, and grant/assignment changes are recorded as relational scalar audit rows. The last active Super Admin is guarded under a shared SQL transaction lock in CMS user mutations.

Effective permission inspection groups the active operation catalog by area. GET search matches localized permission names, identifiers and group labels without case or accent sensitivity; the status filter selects all, granted or denied operations. Clearing search/status retains the selected user and site. Filtered-result counts are separate from the selected context's total granted/denied counts, and missing context and empty search results have distinct guidance. Each inspection resolves the active plugin permission declarations once and bulk-loads the selected site role and grants into a local context, including account activity, system membership and active site-delegable plugin declarations with protected-role defaults. It retains no context across inspections or requests; inspection never changes grants or audit history. These remain operation grants, not a resource-, workflow- or token-specific authorization verdict.

Access history supports combined GET filters for user, site, role and action. User matches the actor or affected account; role matches a direct role reference or the previous/new role of a site-assignment change. Choices come from retained audit references, including unavailable deleted entities, rather than current membership. Filters and pagination remain independent from the role list; clearing one list preserves the other list's context. Filtered empty results have separate guidance. History remains read-only and restricted to active Super Admins. Account activation/deactivation and system-role transitions use distinct `user.activity.changed` and `user.system_role.changed` events with relational scalar before/after states; unchanged states emit no transition event. Values and event labels are translated in all six panel languages. The existing `user.access.saved` summary remains available, now with its previous system-role value. Creation uses an absent previous state. Existing last-administrator safeguards and operation authorization remain in force.

Role saves retain existing grants for disabled plugins, using declared site-delegable permission metadata and the disabled plugin’s own permission prefix when its PHP provider is not loaded. Dormant grants are not executable, newly grantable or copied into new roles; re-enabling the same declaration restores its retained access, and active grants can be explicitly removed. Effective plugin permission inspection uses the plugin resolver's site-context decision, including protected-role compatibility defaults, rather than SQL grants alone. Inactive users and other sites retain their actual denied results.

The operation catalog covers panel access, page read/create/draft and live editing, review, publish, unpublish, archive and deletion, translations, SEO, versions, media, navigation, Shared Slots, messages, moderation, reports, site settings, content sources, automatic pages and personal token management. Grants are positive and site-scoped; ungranted operations are denied. Publishing does not imply unpublishing or deletion, and deleting a published page additionally requires `cms.pages.delete-published`. Source access policies, resource relationships and valid workflow states remain required. Role inheritance, direct user grants and page/locale exceptions are not supported.

CMS user deletion rechecks current administrator authority, or account activity for self-deletion, after acquiring the shared ACL lock. Dashboard shortcuts, page creation site choices and page deletion controls reflect current operation grants; read-only page settings are disabled. User role forms retain actual site assignments even when the assigned role has no panel grant, and retain submitted site selections after validation errors. Role deletion uses the shared WebBlocks modal runtime, including keyboard dismissal.

Role forms preserve explicitly cleared permission selections after validation errors; invalid input cannot silently restore the role's previous grants in the rendered form.

Session routes and personal API requests use the shared CMS resource policy. Personal token capabilities and allowed sites remain upper bounds on current user grants; the existing capability names and wire shapes remain unchanged. Existing built-in users retain historical transport-specific grants through protected SQL compatibility permissions; these are not editable or copied into custom roles. Existing Editor page deletion, published-to-draft transitions and protected-role media access are retained. Custom media assignments additionally require operation authority over every known affected site, and usage details are filtered to readable sites. System tokens retain their existing independent checks, and CMS Super Admin does not bypass host application Gates.

Coarse personal API capabilities can be admitted by any supported native operation they transport. Each request still checks its exact operation and site; for example, a create-only role can use `content.apply` to create a draft without gaining draft replacement, and an archive-only role can use the `content.publish` transport capability without gaining page publication. Composite apply requests select their operation from the plan mode and recheck the normalized plan before writes.

Plugins may explicitly declare a permission site-delegable with `PluginPermission::siteDelegable()` or manifest `scope: "site", delegable: true`. Only active declared site-delegable permissions appear in custom role forms; existing declared role defaults are resolved against protected SQL site-role assignments. Permission middleware scopes the request to granted sites and checks supplied site/resource contexts. Plugin implementations must use the site scope for their own queries and operations. Disabled and unknown declarations grant no access. Public membership/page access remains separate from panel roles.


## Block authoring settings schemas (1.105.9)

`BlockSettingsSchema` owns authoring rules independently of `SettingDefinitions` SQL storage types. The additive `authoring_settings_schema` object has `revision`, `mode`, `unknown_fields`, and `fields`. Each field exposes its type, rule, optional enum/range/text constraints, null/empty clearing semantics, omitted-field update preservation, and separate admin/create/patch write decisions. Creation still applies existing composition defaults (Columns plain, Split stack); schema discovery does not write defaults. Legacy catalog rows remain unpublished and HTML remains human-only.

Invalid supplied enums, booleans, integer ranges, text lengths, object shapes and unsupported fields fail before persistence. Values are not silently clamped or truncated. Supported numeric form strings and booleans normalize through common rules. Link List default choices index/joined/square clear the corresponding override. Declared compatibility aliases normalize Slider fade to slide and Gallery masonary to masonry. Existing domain helpers continue validating active icons, media/MIME ownership, registered applications and application-specific settings, content-source access and collection/template scope. Schema metadata alone grants no capability and performs no provider query. Contact-form submit_label, success_message and consent_label compatibility inputs in settings remain creation aliases consumed by the translation writer; PATCH uses translations instead. Media transport aliases remain creation inputs consumed by the media normalizer; block PATCH uses top-level relation fields instead. `mobile_media_id` is exclusively a top-level relation field and is rejected inside settings, so it cannot become an undeclared stored core setting.

Omitted settings do not trigger cleanup. Historical unknown settings, malformed old scalar values, nulls, empty values, ordered duplicate lists and nested plugin/provider extensions retain their independent relational/revision representation. An unchanged historical field may accompany an update only after exact comparison with server-owned current values. New blocks and full slot replacements cannot claim that exception. Trusted cloning, staging, imports and revision restoration retain their existing lossless paths and authorization checks.

Plugins may opt in with `PluginBlockTypeDefinition::settingsSchema()` or a manifest block's `settings_schema`, using scalar rules `['bool']`, `['int', min, max]`, `['text', maxLength]`, or `['enum', values]`. No method is added to the existing extension interface. A plugin without a schema remains `legacy_plugin` with plugin-owned settings. Invalid declarations report `invalid_plugin_schema` and refuse that block's schema writes without crashing other blocks. Plugin schemas cannot replace a core slug's authoring rules. Translated plugin fields remain separate locale content; optional content bindings/collections retain the existing domain validation.

`webblocks:blocks:audit-settings` produces a read-only JSON compatibility report (default at most 100 reported blocks, `--limit` up to 1000). It reports block identities, field paths and codes without values, does not repair content or resolve external providers, and exits 1 when incompatible settings exist. This is a scalar-schema report, not a live application/provider health check. Existing schema-less plugins are counted separately. Newly changed declared settings are limited to 128 fields, 256 KiB, 32 nested levels and 4096 nodes; historical unchanged data and schema-less plugin behavior remain compatible.

The additive update declares `sort_order`, `show_approved`, `show_author_name`, and `store_submissions` in typed SQL storage and rebinds applicable old documents without casting their values. Unsupported historical shapes remain typed structure nodes. Re-running the migration is safe; rollback retains these columns to avoid data loss. No content is published or rewritten to insert defaults.


## Hosting storage and scheduled maintenance

Panel Dashboard, Backups and Cleanup GETs read the last completed maintenance
report without parsing backup SQL or starting background work on a synchronous
queue. A cold or unavailable cache is explicitly unverified; a report older than
five minutes or invalidated by CMS mutations is labeled stale with its original
measurement time. The transient cache retains the last report for at most seven
days. Settings remain editable before a report is available. No report cache
can authorize deletion or restore.

`system:reports:refresh` is scheduled every five minutes and shares a refresh
lease with the authorized System Health refresh action. It validates at most
eight previously unverified or changed recovery-floor archives per invocation,
then publishes the shared retention/storage report and Dashboard health summary.
Installations without a running scheduler can use System Health → Refresh checks;
ordinary panel requests never fall back to a synchronous full scan. Cache write
failure does not recompute a completed health snapshot.

Typed SQL backup validation provenance records the backup ID, filesystem
signature, compressed archive SHA-256, validator version, status and timestamp.
Backup creation/upload records validation outside list requests; existing
archives are backfilled in bounded batches. Unknown validation withholds cleanup
eligibility in the panel report. This provenance is an optimization for reports,
not a replacement for full current archive inspection under the cleanup lease
or before restore. A cached file signature is not proof against malicious file
replacement. The existing explicit API/CLI cleanup previews still perform fresh
validation; their plan adds a `validation_pending` count (zero for full checks).
Missing provenance schema never triggers heavy list-request fallback. Fresh and
package-update migrations create it idempotently, and restore maintenance repairs
it after restoring an older database. Public uploads and variants are partitioned
in one filesystem traversal in the physical usage report.

`Maintenance → Cleanup` and `GET /webadmin/api/system/cleanup` expose a read-only
`storage_report` with `measured_at` and `categories`. Each category has physical
`files`, `bytes` and `status` (`available`, `missing`, `partial`, `unavailable`).
Categories cover the backups and site-transfers disks, public uploads excluding
variants, generated media variants, known temporary workspace roots, asset
revision roots, site public files and standard application logs. No filenames,
absolute paths, log contents or credentials are returned. Measurements are not
hosting quota, allocated database size or a deduplicated cross-disk grand total;
custom log locations are excluded. Unavailable category previews carry a failure
rather than claiming a confirmed zero. Existing logical revision byte totals
remain separate from physical-file measurements.

The optional `automatic` object on maintenance settings writes patches boolean
selection by `asset-revisions`, `media-variants`, `temporary-workspaces`,
`page-revisions` and `shared-slot-revisions`. Omitted flags keep their values;
unknown categories and non-boolean values fail validation. All five new flags
default to disabled. Existing `maintenance.read`, `maintenance.settings.write`,
`maintenance.delete` and System panel authorization remain separate.

Page and Shared Slot revision retention now defaults to 90 days, with the latest
50 revisions per owner still protected independently of age. Explicit saved
settings, including a 365-day policy, are not rewritten; the new fallback also
applies wherever no setting was stored. The Cleanup form groups each category's
age and protected-count fields together and explains the combined condition.
Retained History uses standard `wb-action-group` icon actions with localized
accessible labels: revision policy links target the corresponding focusable form
group, while the transfer action opens its existing management screen. Management
status is separate from Actions. Empty cleanup categories show a neutral empty
state instead of a disabled destructive button; eligible categories retain their
confirmation modal. Per-category daily/weekly schedule labels use shared
translation placeholders without changing scheduler execution times.

Laravel Scheduler registers assets at 04:00, obsolete media variants at 04:10 and
temporary workspaces at 04:20 daily; page and Shared Slot revisions run on Sunday
at 04:30 and 04:45, in the application's scheduling timezone. Each command uses
`system:cleanup <category> --scheduled`, checks its category flag, and has
scheduler overlap/single-server protection plus a shared maintenance execution
lock. Existing age/minimum-count protections and active revision-restore source
protection remain authoritative. Existing asset-write cleanup and backup,
visitor and editor-recovery schedules are unchanged. A host scheduler and
shared atomic-lock cache are required; reads show actual scheduler evidence
without creating a heartbeat.

Latest execution summaries are bounded to one SQL row per maintenance category,
with manual/scheduled source, timestamps, candidate/deleted counts and bytes,
failure count and last successful completion. Missing summary schema blocks
execution; fresh and package-update migrations install it idempotently. A
running summary older than the execution lock lifecycle is presented as
interrupted without mutating state. Dry-run and overview reads do not write
summaries. Partial variant deletion credits only bytes from successfully removed
directories. Current fingerprints and original media remain protected. Temporary
cleanup selects timestamp/UUID update runs and timestamp/random backup/restore
scratch runs under the known `app/temp/system-backups` and
`app/temp/system-backup-restores` roots. The age threshold covers every nested
entry. Installation identity, unknown run names, updater recovery-backup folders,
symlinked trees/ancestors and roots overlapping backup or public-upload storage
are retained. Stale running/pending update history alone does not block cleanup;
fresh admission/heartbeat evidence, queued operator jobs, held update locks and
process-held backup/restore leases remain protective. Cleanup holds a live update
lease and the process-held backup lease while rechecking tree identity and age
before deletion, without changing update history or manufacturing progress.
An unverified heartbeat cache write stops cleanup; integer and numeric-string
cache representations are both supported.
Flat abandoned catalog downloads use nonblocking exclusive file locks, while
installation holds a shared lease. Other files, transfer archives and application
logs are not added to automatic deletion. Physical measurements may therefore
include retained files with no cleanup candidate.

New system backup ZIP archives omit the reserved generated-cache namespace
`media/transforms/` from uploads. The immutable version-one manifest records
`excluded_upload_paths: ["media/transforms/"]`; database, original uploads,
unrelated public files and site public files retain their existing restoration
contract. Variants regenerate on renderer demand with codec failure falling
back to the original. Existing version-one and legacy manifests remain
restorable, including archived variants when present. Unique archive names
prevent same-second safety backups from overwriting restore sources. Archive
resolution supports a configured symlinked disk root while rejecting relative
traversal, internal symlink escapes and absolute paths outside the disk.


### Backup lifecycle and Cleanup workspace

CMS 1.105.12 adds `is_protected` (boolean), `retention_mode`
(`policy`, `never`, `expires`) and nullable `expires_at` SQL fields on backup
records through idempotent fresh/update migrations. No new JSON authority is
introduced. Missing retention or summary schema blocks automatic cleanup.
Protection blocks single, forced and bulk deletion until explicitly removed.
Bulk failures expose localized protection/operation feedback without diagnostics.
`never` blocks automatic expiration but permits an explicit operator deletion.
Running backups and recovery archives for recent failed/restored updates remain
protected. The operation lease serializes creation, upload, restore, retention
changes and deletion on the backups filesystem, survives SQL restoration, and
releases on process exit. Restores may create their safety archive under the
same process lease; shared filesystems must support advisory file locks.

Defaults are pre-update 14 days/latest five valid successful archives; restore
safety 14 days/latest two; content apply seven days/latest three; manual 30
days/latest three with manual-policy cleanup disabled. Uploaded archives in
`policy` mode remain indefinite. Explicit `expires` opts an individual archive
into cleanup even for manual/uploaded types, without overriding protection or
successful-count floors. Age and count safeguards apply together. Only readable,
structurally validated, distinct physical archives count toward each type's
floor; missing/corrupt snapshots do not displace valid recovery points. Saved
settings are preserved. Multiple records sharing a physical archive do not
multiply eligible bytes; a retained reference protects that file, and explicit
record deletion does not unlink a file still referenced elsewhere.

The backup budget is unset by default, accepts 0.01–10240 GiB, warns at 80% and
reports an overrun at 100%. It is a soft reporting budget, never authority to
shorten age/count protection. Measurements include unregistered files on the
backups disk but cleanup never automatically selects those files. Reports expose
total, explicitly protected, eligible and estimated post-cleanup bytes, measurement
status, current/remaining overrun, per-record decisions and the last bounded SQL
cleanup outcome. CMS 1.105.13 adds `reason_summary`, which groups the existing
per-record decisions by `reason`, with `record_count`, distinct attributed
`file_count` and physical `bytes`. Its total record count is independent of the
physical file count. Shared archives contribute bytes once, to the first
applicable group in displayed order: schema missing, explicit protection,
running, failed-update recovery, minimum count, indefinite, manual disabled,
within retention, invalid policy, unavailable, shared archive, eligible.
`other_files` reports measured files not matched to an archive, including lock
or support files; these remain outside automatic cleanup. Complete scans reconcile
group bytes plus other bytes with total disk usage. Incomplete or inconsistent
scans expose a partial/unavailable summary and null total/other-file metrics,
while still showing measured known archives. Running archives can be measured
but remain ineligible. Cleanup and Backups render the same summary, and the
existing backup-cleanup API exposes it under `preview.overview.reason_summary`.
No new write operation, persistence, policy default or archive format is added.
Partial/unavailable scans are explicit. Preview reads do not
create archive directories, acquire an operation lease, delete files or write
execution history. Runs compute their plan under the lease and credit only bytes
actually removed. If unlink succeeds but deleting its SQL record fails, freed
bytes are recorded with a failure and no deleted-record count. Oldest eligible
records are processed first.

`PUT /webadmin/api/system/backup-cleanup` keeps its original required fields and
patches optional `manual_enabled`, `manual_days`, `keep_latest_manual`,
`keep_latest_restore_safety`, `keep_latest_content_apply` and nullable `budget_gb`.
Omission preserves saved values; null budget clears the budget. Existing read,
settings-write and delete capabilities remain separate. New GET/PUT
`/webadmin/api/system/backups/{backup}/retention` use `backups.read` and
`backups.settings.write`; PUT requires `is_protected`, `retention_mode` and, for
`expires`, a valid `expires_at` in the application timezone. Admin changes use
SystemBackupPolicy and FormRequest validation. The policy accepts the configured
authenticatable user contract and still requires system access. Manual creation stores selected
retention before the SQL dump, so its own snapshot includes that state. Older
SQL snapshots receive the additive retention/summary schema during restore
maintenance. No backup format change or remote-copy automation is introduced.

Cleanup uses native `wb-tabs` for storage/history, retention/automation and
cleanup actions. Policy links open the settings panel and focus their group;
validation and save/run redirects preserve the relevant panel. Informational
cards have no destructive actions. All CMS admin templates use named WebBlocks UI
groups/cards instead of HTML `fieldset`/`legend`, including shared listing
controls and translated-page SEO groups; existing SEO edit restrictions remain.
The contributor contract and a template-wide regression guard enforce the rule.

### Retention tables and scheduler setup (1.105.25)

Cleanup’s Retention Policy uses three native WebBlocks UI tables: selected
categories and schedules, backup age/minimum-count protections, and revision or
temporary-workspace policies. Mobile rows stack with visible column labels;
each input retains an explicit accessible label and its existing name, limits,
old input and stored value. Manual-backup inclusion and the GiB budget retain
their previous semantics. Revision policy links preserve their focusable row
anchors. No saved policy, opt-in flag, default or execution time changes.

GET `/webadmin/system/scheduler` requires the existing System Health policy
(active account with system access). It reads actual scheduler evidence and
shows installation-specific, shell-quoted CLI/hosting commands; cron percent
characters are escaped for the hosting command. A configured CLI PHP path can
be supplied with `WEBBLOCKS_CMS_SCHEDULER_PHP_BINARY`. Web requests neither
inspect private crontabs nor execute OS setup. Cleanup and System Health link
to setup; updates show a nonblocking link when scheduler evidence needs review.

`webblocks:scheduler:setup` defaults to read-only inspection. `--install`
requires interactive confirmation, or explicit `--yes` after external scheduler
review (`--json` never prompts). Installation is limited to a supported Unix
application account owning artisan, with readable application and writable
runtime directories; root and other accounts are refused. A recent heartbeat,
readable external definition, existing exact entry, ambiguous scheduler entry,
unsupported shell or unavailable crontab prevents appending a duplicate. Unknown
cron access failures are not treated as an empty crontab. Unreadable hosting or
system definitions still require operator review; no universal discovery is
claimed.

Setup holds a cache lock, rechecks context and current crontab, preserves its
existing bytes and appends one marked every-minute entry. Concurrent changes
observed before write abort installation. Read-back failure is explicit and
never triggers a blind rollback over another writer. Raw cron contents and
errors are never rendered or logged. The OS crontab remains host-owned; no new
CMS persistence is introduced. Installing an entry is not execution evidence:
only the actual scheduled heartbeat verifies scheduler activity. Updates never
silently install OS tasks or enable destructive cleanup categories.

### Update progress copy and activity visibility (1.105.26)

The CMS progress payload uses one label resolver for the current phase and
recorded steps: product translation, shared Client translation, then a readable
CMS fallback. All current engine phases have product labels in six panel
languages. Missing or future phase translations never expose a translation key
in the phase heading. Operation identity, elapsed time, terminal-state and
installed-version verification remain unchanged.

The product-owned dialog uses a neutral versioned title. Its activity group
contains the current phase, native indeterminate progressbar and waiting advice;
the existing Client visibility contract hides the whole group on unconfirmed,
interrupted or terminal results. Elapsed time and the recorded result remain
outside that group. Successful verified updates retain the existing automatic
return; failed/restored results stay available with the update-history link.
The embedded Client runtime is unchanged.

### Large staged content-plan writes (1.105.28)

The existing HTTP content-plan modes and publication capabilities remain unchanged.
Staging and promotion reuse copied block models and known translation relationships;
translation locale validation and model hooks still run. Unchanged root copies are
not saved twice. Parent and collection-template references are remapped after every
copy has an identity. Promotion loads and locks each selected source slot's block
tree once, then deletes descendants before their parents within that page/slot.

Ordinary model writes retain the request's relational schema-discovery cache;
package schema migration and database restore paths still invalidate it explicitly.
New relational documents skip deletion of nonexistent children. Existing documents
retain their identity and replace prior typed rows/nodes under the normal owner
transaction. Complete independent revision snapshots, SQL authority, legacy value
shapes, API wire formats and public rendering are preserved. No new persistence,
background publication job, SSH requirement or hosting configuration is introduced.
The existing API search-refresh middleware continues to coalesce index work.
