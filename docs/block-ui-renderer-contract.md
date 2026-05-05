# Block UI Renderer Contract

Phase 1 defines the intended public rendering contract between CMS layouts, slots, and block types and the shipped WebBlocks UI primitives already loaded by the public layout. This phase is documentation-only. It does not rewrite renderers yet.

## Verified WebBlocks UI Primitives

Verified against the actual shipped assets used by CMS:

- CSS: `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css`
- JS: `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js`

Confirmed primitives and patterns:

- `wb-content-shell`
- `wb-content-header`
- `wb-content-body`
- `wb-content-footer`
- `wb-promo`
- `wb-callout`
- `wb-link-list`
- `wb-stat`
- `wb-gallery`
- `wb-alert`
- `wb-rich-text`
- `wb-rich-text-readable`
- `wb-rich-text-compact`
- `wb-rich-text-loose`
- `wb-btn`
- `wb-grid`
- `wb-grid-2`
- `wb-grid-3`
- `wb-grid-4`
- `wb-stack`
- `wb-gap-1`
- `wb-gap-2`
- `wb-gap-3`
- `wb-gap-4`
- `wb-gap-6`
- `wb-gap-8`

Missing primitives and UI gaps for now:

- `wb-prose` — NOT FOUND in the shipped WebBlocks UI assets. Treat this as a UI gap and do not rely on it for Phase 2 layout or renderer alignment.
- `wb-promo-muted` — NOT FOUND in the shipped WebBlocks UI assets.
- `wb-promo-accent` — NOT FOUND in the shipped WebBlocks UI assets.
- `wb-cluster-3` — NOT FOUND in the shipped WebBlocks UI assets.

Verified shipped JS hooks relevant to current public rendering:

- `data-wb-toggle="dropdown"`
- `data-wb-toggle="modal"`
- `data-wb-gallery-target`
- `#wb-overlay-root`

## Phase 2B Layout Modes

Public pages now use explicit layout composition modes:

- `stack` is the default mode.
- `sidebar` is used when the page has a populated sidebar slot.
- `content` is reserved for future docs/editorial pages with explicit metadata and must not be guessed from URL or title.
- `wb-content-shell` is not a universal wrapper and should only appear when the page metadata explicitly supports a content-shell presentation.

## Matrix

| CMS Block | WebBlocks UI primitive/pattern | Status | Priority | Next action |
| --- | --- | --- | --- | --- |
| public layout | `wb-public-body`, `wb-public-main`, `wb-container` | acceptable | P0 shell/layout | keep the shell stable and move slot-specific chrome toward documented primitives |
| header slot | `wb-section`, `wb-container`, nav/list primitives | weak | P0 shell/layout | align custom header chrome with documented shell rules |
| main slot | `wb-public-main`, `wb-container`, `wb-content-shell` | acceptable | P0 shell/layout | add `wb-content-shell` usage where content reads like article/docs content |
| sidebar slot | `wb-grid`, `wb-stack`, optional `wb-content-shell` aside | weak | P0 shell/layout | stop treating generic sidebars as faux app sidebars |
| footer slot | `wb-section`, `wb-container`, `wb-grid`, link-list/nav primitives | acceptable | P0 shell/layout | keep footer on shipped layout primitives and avoid extra custom classes |
| `heading` | semantic heading | acceptable | P2 content quality | keep wrapper-free output and normalize anchor behavior later |
| `text` | body copy in `wb-stack` rhythm | acceptable | P2 content quality | keep simple output and avoid bespoke typography wrappers |
| `rich-text` | `wb-rich-text wb-rich-text-readable` | acceptable | P2 content quality | keep safe body copy scoped to the shipped rich text primitive |
| `html` | trusted raw HTML in public block wrapper | acceptable | P3 later/custom | keep restricted to trusted editorial/admin usage |
| `section` | `wb-section`, optional `wb-promo` | acceptable | P1 public marketing/docs | keep default section stable and reserve promo semantics for explicit variants |
| `columns` | `wb-grid`, `wb-grid-2`, `wb-grid-3`, `wb-grid-4`, `wb-link-list` | acceptable | P1 public marketing/docs | keep parent-driven variants explicit and avoid reintroducing forced wrapper cards |
| `column_item` | plain cell, `wb-card`, `wb-stat`, or link-list item | acceptable | P1 public marketing/docs | keep item rendering driven by the parent `columns.variant` mapping |
| `callout` | `wb-alert`, optional `wb-callout` | acceptable | P2 content quality | widen variant mapping if a shipped sidebar/help callout exists |
| `quote` | semantic `blockquote`, optional framed card | acceptable | P2 content quality | make framing conditional rather than always card-like |
| `faq` | simple Q/A card | acceptable | P3 custom/fallback | keep the stable single-card treatment and let grouped disclosure live in accordion blocks |
| `tabs` | interactive tabs pattern | weak | P2 content quality | defer until a real shipped tabs pattern exists |
| `button` | `wb-btn` variants | weak | P1 public marketing/docs | map all supported CMS variants and add button-group direction |
| `image` | semantic `figure`, `img`, `figcaption` | weak | P2 content quality | honor link behavior and only add media framing when needed |
| `gallery` | `wb-gallery` plus overlay root modal | acceptable | P1 public marketing/docs | keep using shipped gallery hooks and central overlay root |
| `download` | `wb-btn` or card-with-button CTA | acceptable | P2 content quality | add explicit card/download variants later |
| `navigation-auto` | nav/list primitives, optional `wb-link-list` | acceptable | P1 public marketing/docs | keep simple menus simple and reserve docs sidebars for real docs shells |
| `menu` | legacy alias of `navigation-auto` | acceptable | P3 later/custom | keep for migrated data only |
| `contact_form` | form primitives, `wb-btn`, `wb-alert` | acceptable | P1 public marketing/docs | keep structured editor fields and avoid raw HTML forms |
| `hero` | `wb-promo` marketing shell | acceptable | P1 public marketing/docs | keep hero first-class, translated, and action-driven through child button blocks |
| `card-grid` | card grid pattern | weak | P1 public marketing/docs | keep the current structured card grid stable and revisit first-class modeling later |
| `showcase-list` | showcase/gallery pattern | weak | P3 later/custom | either formalize as a first-class showcase block or keep custom |
| `contact-info` | link list / contact meta card | weak | P2 content quality | promote to first-class only if editors need it beyond seeded pages |
| `code` | code block pattern | missing | P2 content quality | add first-class renderer/admin support or keep fallback deliberately |
| `list` | list primitive | acceptable | P1 public marketing/docs | keep the dedicated line-based editor and preserve legacy fallback-style settings compatibility |
| `table` | `wb-table` pattern | acceptable | P1 public marketing/docs | keep the dedicated line-based editor and preserve legacy fallback-style settings rows |
| `accordion` | semantic disclosure pattern | acceptable | P1 public marketing/docs | keep the first-class semantic `<details>` renderer with child blocks as items |
| `feature-grid` | feature grid pattern | weak | P1 public marketing/docs | keep fallback rendering for now and prefer `columns` with the `cards` variant for structured use cases |
| `stats` / `metric-card` | `wb-stat` and related metric cards | weak | P1 public marketing/docs | add real stat variants instead of fallback cards |
| `logo-cloud` | logo grid / brand strip | weak | P1 public marketing/docs | add structured media handling if it remains productized |
| `testimonial` | quote/testimonial card | weak | P2 content quality | decide whether quote variants are sufficient or a separate block is needed |
| `timeline` | timeline pattern | weak | P2 content quality | promote only if timeline content is a real recurring use case |
| `pricing` | pricing card/grid pattern | weak | P1 public marketing/docs | make first-class only with structured plans/features |
| `toc` | table-of-contents navigation | missing | P1 public marketing/docs | add first-class docs navigation support |
| `breadcrumb` | breadcrumb navigation | missing | P2 content quality | defer until the public page shell truly needs it and a shipped pattern is confirmed |
| `cookie-notice` | shared privacy banner/modal pattern | missing | P3 later/custom | keep consent UI in the public layout rather than block renderers |

## Public Shell And Slots

### Public layout

- The public layout owns the page-wide shell and should keep `<body class="wb-public-body">`.
- Page `Public Shell` is the only source of truth for the outer public shell mode.
- Major page regions should be built from shipped WebBlocks UI layout primitives first: `wb-public-main`, `wb-container`, `wb-section`, `wb-stack`, `wb-grid`.
- `wb-content-shell`, `wb-content-header`, `wb-content-body`, and `wb-content-footer` belong inside the main content area when the page reads like article, guide, docs, or editorial content. They are not the site-wide header or footer chrome.
- `#wb-overlay-root` is the shared mount point for public overlays such as the gallery viewer and cookie preference modal.
- `wb-sidebar` is reserved for a true docs/app navigation shell. Generic marketing or editorial sidebars should stay ordinary `aside` content composed from `wb-grid`, `wb-stack`, cards, callouts, and link lists.

### Slot wrappers

- Slot wrappers are deterministic runtime behavior, not editorial settings.
- Page `Public Shell` and slot name are the only inputs used to resolve slot wrapper element, classes, and structural attributes.
- `default` maps `header`, `main`, `sidebar`, and `footer` to semantic wrappers and falls back to `div` for unknown slots.
- `docs` maps `header` to the docs navbar wrapper, `sidebar` to the docs sidebar wrapper, and `main` to the docs main wrapper while keeping `wb-dashboard-shell` page-owned.
- Blocks render inside the resolved slot wrapper and do not own page-shell markup.
- Generic public block wrappers must stay non-semantic and must not be used for layout/root-owning blocks.
- The page shell owns the outer shell, slot wrappers own the region wrapper, and root-owning blocks own their own real public root element.
- Root-owning blocks must place `data-wb-public-block-type` on their own renderer root instead of receiving an extra outer `wb-public-block` wrapper.

### Header slot

- Intended mapping: `header` region built from `wb-section` and `wb-container` with shipped nav/list primitives inside.
- Use `wb-stack` or `wb-grid` for internal rhythm, depending on whether the header reads as a stacked banner or a horizontal navigation bar.
- Primary or legal navigation may render through `navigation-auto` or `menu`, but the slot should not require block renderers to emit custom header-only HTML.
- Current implementation is weak because the header slot owns several custom `wb-public-*` chrome classes. Phase 2A keeps the existing `wb-section` and `wb-container` shell intact and defers any safe reduction of custom header chrome to a later pass.

### Main slot

- Intended mapping: `<main class="wb-public-main">` is the primary content region.
- Default shell: `wb-public-main > .wb-container > .wb-stack` for ordinary block stacks.
- Editorial/docs shell: `wb-public-main > .wb-container > .wb-content-shell` with optional `wb-content-header`, `wb-content-body`, and `wb-content-footer` sections.
- Nested block layout should use `wb-stack`, `wb-stack-*`, `wb-gap-*`, `wb-grid`, and `wb-grid-*` before any custom wrapper class.
- The current implementation is acceptable because it already gives the main slot a stable public wrapper and block rhythm. Phase 2B adds explicit layout modes so `wb-content-shell` is reserved rather than forced onto every page.

### Sidebar slot

- Intended mapping: `aside` adjacent to main content via `wb-grid` or another shipped layout primitive.
- Default content should be a `wb-stack` of supporting blocks, optionally grouped in a `wb-card` or `wb-callout` if that matches the block content.
- `wb-sidebar` should only be used when the page is explicitly rendering a docs/app navigation shell, not for generic supporting marketing content.
- Current implementation is weak because it still uses a custom `wb-public-sidebar` shell. Phase 2A removes the forced outer `wb-card` wrapper so inner blocks control whether they render as cards or callouts.

### Footer slot

- Intended mapping: `footer` region built from `wb-section`, `wb-container`, and `wb-grid` or `wb-stack`.
- Navigation lists should use simple nav/list primitives or `wb-link-list` when the shipped UI pattern fits.
- Supporting content blocks may render normally inside the footer; they should not need footer-specific block renderers.
- Current implementation is acceptable because it stays close to shipped layout primitives, with only minor CMS-specific footer chrome around the cookie settings control.

## Core Content Blocks

### `heading`

- CMS block slug: `heading`
- Admin fields: `title`, `variant`, `url`
- Translatable fields: `title`
- Shared fields: `variant`, `url`
- Intended WebBlocks UI output: semantic `<h1>`-`<h6>` based on `variant`; optional `id` from `url`/anchor; no invented wrapper beyond the public block wrapper unless needed by children.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: normalize anchor IDs, keep child content optional, and avoid adding decorative heading wrappers.

### `text`

- CMS block slug: `text`
- Admin fields: `content`
- Translatable fields: `content`
- Shared fields: none
- Intended WebBlocks UI output: ordinary paragraph/body copy; use a shipped `wb-stack` rhythm only when needed for multiple text nodes.
- Current implementation: acceptable
- Import/sync mapping note: use `text`/`plain_text` only for simple plain body copy with no safe inline formatting markup.
- Notes for later renderer/admin improvements: keep this block simple and avoid turning it into a pseudo-rich-text block.

### `rich-text`

- CMS block slug: `rich-text`
- Admin fields: `content`
- Translatable fields: `content`
- Shared fields: none
- Intended WebBlocks UI output: sanitized body copy wrapped in `wb-rich-text wb-rich-text-readable` using the shipped WebBlocks UI rich text primitive.
- Current implementation: acceptable
- Storage model: Rich Text stores a restricted safe HTML fragment, not Markdown markers. Allowed tags are `p`, `strong`, `em`, `code`, `a[href]`, `ul`, `ol`, `li`, and `br` when needed. Classes, styles, event attributes, headings, media, tables, buttons, and unsupported HTML are stripped during sanitization.
- Admin behavior: the admin editor is a dependency-free `contenteditable` surface synchronized to a hidden form field. It is intentionally limited to body-copy formatting and does not replace Header, Button, Media, Table, Layout, HTML, or other dedicated block types.
- Import/sync mapping note: when imported body copy contains safe inline formatting, multiple paragraphs, or simple lists, it should become `rich-text` rather than `text`.
- Notes for later renderer/admin improvements: keep Rich Text limited to safe editorial body copy. `wb-rich-text` remains the public typography primitive. Headings, media, buttons, tables, layout, and raw HTML composition remain separate blocks or features.

### `html`

- CMS block slug: `html`
- Admin fields: `content`
- Translatable fields: `content`
- Shared fields: none
- Intended WebBlocks UI output: raw trusted HTML inside the normal public block wrapper.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep this block for trusted editorial/admin usage only, document the XSS risk clearly, and do not make ordinary editors rely on pasted WebBlocks HTML for normal content.

### `section`

- CMS block slug: `section`
- Admin fields: `title`, `variant`, `content`
- Translatable fields: `title`, `content`
- Shared fields: `variant`
- Intended WebBlocks UI output: default wrapper uses `wb-section`; explicit `promo` variants may map to `wb-promo` when the shipped pattern fits; CTA actions should come from child blocks, not raw HTML.
- Current implementation: acceptable
- Wrapper rule: the `Section` block owns the real `<section class="wb-section ...">` root and carries `data-wb-public-block-type="section"` on that element. Generic public block wrappers must not wrap it.
- Notes for later renderer/admin improvements: keep default sections stable, treat `promo` as an explicit marketing variant, and keep child buttons/button groups structured.
- Promo CTA behavior: child `button` blocks render in `wb-promo-actions`; non-button children continue rendering outside the CTA row.

### Layout wrapper rule

- `header`, `section`, `container`, `grid`, `cluster`, `card`, and `content_header` are root-owning layout/content-shell blocks and should not receive a generic public wrapper from the public block loop.
- `Section` owns the semantic `<section class="wb-section">` root when needed.
- `Container`, `Grid`, and `Cluster` own their own non-semantic layout roots unless a specific renderer intentionally chooses otherwise.
- `Card` owns its `<article class="wb-card">` or promo `<section class="wb-card wb-promo">` root.
- `Header` owns its semantic heading root such as `<h1>` or `<h2>`.
- `Content Header` owns its semantic `<header class="wb-content-header">` root.

### `hero`

- CMS block slug: `hero`
- Admin fields: `subtitle`, `title`, `content`, `variant`
- Translatable fields: `subtitle` as eyebrow, `title` as headline, `content` as supporting copy
- Shared fields: `variant`, child block structure
- Intended WebBlocks UI output: `wb-promo > .wb-promo-copy` with optional `wb-eyebrow`, `wb-promo-title`, `wb-promo-text`, and `wb-promo-actions`
- Current implementation: acceptable
- Notes for later renderer/admin improvements: hero CTA actions should come from child `button` blocks, raw HTML should not be used for normal hero content, and legacy imported hero settings should only act as a fallback when canonical translated fields are empty.
- Hero CTA behavior: child `button` blocks render in `wb-promo-actions`; non-button children are ignored in the CTA row.

### `columns`

- CMS block slug: `columns`
- Admin fields: `title`, `subtitle`, `content`, `variant`, repeatable `column_items`
- Translatable fields: `title`, `subtitle`, `content`, child `column_item` text
- Shared fields: `variant`, child ordering, child links, structure
- Intended WebBlocks UI output: layout container for repeatable children using `wb-grid`, `wb-grid-2`, `wb-grid-3`, `wb-grid-4`, or a generic `wb-grid` when the count is dynamic.
- Current implementation: acceptable
- Variant mapping:
  - `cards` -> grid container with each child rendered as `wb-card > .wb-card-body`
  - `plain` -> grid container with unframed stacked child content
  - `stats` -> grid container with child items rendered as `wb-stat`
  - `links` -> `wb-link-list` with child items rendered as link-list rows
- Notes for later renderer/admin improvements: keep the parent block responsible for presentation choice so existing `column_item` content can stay simple and reusable.

### `column_item`

- CMS block slug: `column_item`
- Admin fields: `title`, `url`, `content`
- Translatable fields: `title`, `content`
- Shared fields: `url`
- Intended WebBlocks UI output: one grid cell that may render as plain content, `wb-card`, `wb-stat`, `wb-link-list-item`, or other shipped cell treatment depending on parent or item variant.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: `column_item` remains a simple content unit and now defers public presentation to its parent `columns` block. The current `stats` mapping is intentionally conservative because there is no dedicated numeric value field yet.

### `callout`

- CMS block slug: `callout`
- Admin fields: `title`, `variant`, `content`
- Translatable fields: `title`, `content`
- Shared fields: `variant`
- Intended WebBlocks UI output: notice/alert behavior maps to `wb-alert-*`; sidebar/help variants may map to `wb-callout` if that primitive exists in WebBlocks UI.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep tone mapping explicit and only introduce alternate callout shells when the shipped UI has them.

### `quote`

- CMS block slug: `quote`
- Admin fields: `content`, `title`, `subtitle`
- Translatable fields: `content`, `title`, `subtitle`
- Shared fields: none
- Intended WebBlocks UI output: semantic `blockquote`; use shipped card/callout framing only when the quote is intentionally presented as a framed testimonial/card.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: allow an unframed semantic quote variant and use `testimonial` only if that becomes a separate first-class pattern.

### `faq`

- CMS block slug: `faq`
- Admin fields: `title`, `content`
- Translatable fields: `title`, `content`
- Shared fields: none
- Intended WebBlocks UI output: simple question/answer content in a stable `wb-card` and `wb-stack` shell.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: FAQ remains simple and backward-compatible. When used as a child of an accordion-family block, its `title` and `content` act as the disclosure summary/body.

### `accordion`

- CMS block slug: `accordion`
- Admin fields: `title`, `content`
- Translatable fields: `title`, `content`
- Shared fields: child block structure and ordering
- Intended WebBlocks UI output: semantic grouped disclosure using `<details>` and `<summary>` with no custom accordion JS or invented classes.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: child blocks supply the disclosure items. Blocks without usable `title` and `content` are skipped rather than rendered as empty wrappers.

### `faq-list`

- CMS block slug: `faq-list`
- Admin fields: same editorial expectations as `accordion`
- Translatable fields: inherited from the parent block and child items
- Shared fields: child block structure and ordering
- Intended WebBlocks UI output: same as `accordion`; this slug is now a transitional alias rather than a separate long-term pattern.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep old content working, but steer future grouped disclosure usage toward `accordion`.

### `tabs`

- CMS block slug: `tabs`
- Admin fields: `title`, `subtitle`, `content`
- Translatable fields: `title`, `subtitle`, `content`
- Shared fields: none
- Intended WebBlocks UI output: a true interactive tabset if tabs remain a first-class block.
- Current implementation: weak
- Notes for later renderer/admin improvements: tabs are explicitly deferred until WebBlocks UI ships a real tabs pattern. The current simple card treatment remains only as a compatibility fallback and is not promoted.

### `button`

- CMS block slug: `button`
- Admin fields: `title`, `url`, `subtitle`, `variant`
- Translatable fields: `title`
- Shared fields: `url`, `subtitle`/target, `variant`, optional attachment asset relation
- Intended WebBlocks UI output: explicit `wb-btn` variant mapping for `primary`, `secondary`, `outline`, `ghost`, and `danger`; unknown values must fall back to `wb-btn wb-btn-primary`.
- Current implementation: acceptable
- Variant mapping:
  - `primary` -> `wb-btn wb-btn-primary`
  - `secondary` -> `wb-btn wb-btn-secondary`
  - `outline` -> `wb-btn wb-btn-outline`
  - `ghost` -> `wb-btn wb-btn-ghost`
  - `danger` -> `wb-btn wb-btn-danger`
  - unknown or empty -> `wb-btn wb-btn-primary`
- Notes for later renderer/admin improvements: keep attachment/download compatibility isolated, render `<button type="button">` only when there is no URL, and formalize a first-class button-group block only when editors need it beyond child button rows.

### CTA Rows

- Hero and promo-style section blocks should model CTAs with child `button` blocks.
- Promo CTA rows render in `wb-promo-actions`.
- Outside promo contexts, ordinary action rows can use shipped cluster utilities such as `wb-cluster wb-cluster-2`.
- A first-class `button-group` block is deferred for now; the current child-button model already supports structured CTA rows without adding new block architecture.

### `image`

- CMS block slug: `image`
- Admin fields: `asset_id`, `subtitle`, `url`, `title`
- Translatable fields: `title` as caption, `subtitle` as alt text
- Shared fields: `asset_id`, `url`
- Intended WebBlocks UI output: semantic `figure`, `img`, and optional `figcaption`; use shipped media/card classes only when the image is intentionally framed.
- Current implementation: weak
- Notes for later renderer/admin improvements: honor the optional link URL, keep alt/caption semantics clear, and avoid decorative wrappers by default.

### `gallery`

- CMS block slug: `gallery`
- Admin fields: `title`, `subtitle`, `gallery_asset_ids`
- Translatable fields: none
- Shared fields: `title`, `subtitle`, ordered gallery assets, legacy fallback settings when present
- Intended WebBlocks UI output: WebBlocks gallery pattern with the viewer mounted under `#wb-overlay-root`; shipped WebBlocks UI gallery hooks should drive interaction first.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep CMS JavaScript limited to CMS-specific data plumbing only if the shipped hooks cannot cover a case.

### `download`

- CMS block slug: `download`
- Admin fields: `title`, `subtitle`, `asset_id`, `variant`
- Translatable fields: none
- Shared fields: `title`, `subtitle`, `asset_id`, `variant`
- Intended WebBlocks UI output: file CTA as `wb-btn`, or a compact `wb-card` plus `wb-btn` when the variant needs more context.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: add explicit card/download variants instead of inferring presentation from helper text alone.

### `video`

- CMS block slug: `video`
- Admin fields: `title`, `content`, `url`, `asset_id`
- Translatable fields: none
- Shared fields: `title`, `content`, `url`, `asset_id`
- Intended WebBlocks UI output: semantic `<video controls>` for direct sources, or a safe provider `<iframe>` only for known YouTube/Vimeo URLs, inside a simple `wb-card` shell.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep playback native, avoid custom player JS, and fall back to a simple external link when the URL is not a safe embed provider.

### `audio`

- CMS block slug: `audio`
- Admin fields: `title`, `content`, `url`, `asset_id`
- Translatable fields: none
- Shared fields: `title`, `content`, `url`, `asset_id`
- Intended WebBlocks UI output: semantic `<audio controls>` inside a simple `wb-card` shell.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep playback native and dependency-free.

### `file`

- CMS block slug: `file`
- Admin fields: `title`, `content`, `url`, `asset_id`
- Translatable fields: none
- Shared fields: `title`, `content`, `url`, `asset_id`
- Intended WebBlocks UI output: compact file card with a `wb-btn wb-btn-secondary` download/open action.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep this distinct from the dedicated `download` CTA block, which remains more action-oriented.

### `map`

- CMS block slug: `map`
- Admin fields: `title`, `content`, `url`
- Translatable fields: none
- Shared fields: `title`, `content`, `url`
- Intended WebBlocks UI output: simple location summary with an external `Open map` button, not a custom map widget.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep the implementation link-first unless a real shipped map/embed pattern becomes available.

### `slider`

- CMS block slug: `slider`
- Admin fields: ordered gallery assets and optional text
- Translatable fields: none
- Shared fields: assets and structure
- Intended WebBlocks UI output: no promoted Phase 4 renderer; use `gallery` or other structured media blocks instead when possible.
- Current implementation: weak
- Notes for later renderer/admin improvements: slider remains deferred because there is no confirmed shipped WebBlocks UI carousel pattern worth standardizing on.

### `code`

- CMS block slug: `code`
- Admin fields: `title`, `content`
- Translatable fields: `title`, `content`
- Shared fields: optional `settings.language`
- Intended WebBlocks UI output: escaped source inside semantic `<pre><code>` with no injected HTML and no syntax-highlighting dependency.
- Current implementation: acceptable
- Import/sync mapping note: raw or multi-line snippets such as `<pre><code>` examples and package include blocks should become `code`, not `rich-text`.
- Notes for later renderer/admin improvements: keep code rendering safe and dependency-free. Optional language metadata may be exposed from settings, but a full code editor or syntax-highlighting stack is intentionally out of scope for Phase 3.

### `toc`

- CMS block slug: `toc`
- Admin fields: `title`
- Translatable fields: `title`
- Shared fields: none
- Intended WebBlocks UI output: `wb-link-list` built from existing anchored `heading` blocks on the same page.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: the Phase 3 implementation stays intentionally minimal. It only renders when headings already expose explicit anchor IDs and does not attempt complex heading parsing or auto-generated anchors.

### `navigation-auto`

- CMS block slug: `navigation-auto`
- Admin fields: `navigation_menu_key`
- Translatable fields: none
- Shared fields: `settings.menu_key`
- Intended WebBlocks UI output: site navigation list using simple nav/list primitives or `wb-link-list` when appropriate; do not fake a docs sidebar unless the page shell is truly a docs shell.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep the block focused on rendering navigation trees and let the slot/page shell decide whether the output is header nav, footer nav, or docs nav.

### `menu`

- CMS block slug: `menu`
- Admin fields: `navigation_menu_key`
- Translatable fields: none
- Shared fields: `settings.menu_key`
- Intended WebBlocks UI output: same as `navigation-auto`; this remains a legacy alias for migrated data.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: keep support for old content, but prefer `navigation-auto` in admin UX and future seeds.

### `contact_form`

- CMS block slug: `contact_form`
- Admin fields: `heading`, `intro_text`, `submit_label`, `success_message`, `recipient_email`, `send_email_notification`, `store_submissions`
- Translatable fields: `title`/heading, `content`/intro, `submit_label`, `success_message`
- Shared fields: `recipient_email`, `send_email_notification`, `store_submissions`
- Intended WebBlocks UI output: form fields use WebBlocks UI form primitives; submit action uses `wb-btn`; success and error messages use `wb-alert`.
- Current implementation: acceptable
- Notes for later renderer/admin improvements: preserve structured form fields, keep operational settings out of editorial copy, and avoid making editors paste raw form markup.

## Public-Only Or Weak Blocks

| CMS Block | Current state | Desired direction | Notes |
| --- | --- | --- | --- |
| `card-grid` | public-render-only | should stay transitional | The renderer now matches the same `wb-grid` and `wb-card` structure as `columns.variant = cards`, but it still depends on `settings.items`. Prefer Columns for new structured content. |
| `showcase-list` | public-render-only | should stay fallback/custom | This is currently showcase-specific seeded content and should not become core unless the pattern repeats across sites. |
| `contact-info` | public-render-only | should become first-class | If editors keep using contact metadata cards, a small structured block is better than settings-driven custom content. |
| `code` | first-class public renderer | acceptable | Safe `<pre><code>` rendering is now in place; richer editor affordances remain optional future work. |
| `list` | first-class public renderer | acceptable | Dedicated line-based list rendering now exists; keep compatibility for legacy settings-driven content. |
| `table` | first-class public renderer | acceptable | Dedicated line-based table rendering now exists; keep compatibility for legacy settings rows. |
| `accordion` | first-class public renderer | acceptable | Grouped disclosure now uses semantic `<details>` and child blocks instead of fallback settings markup. |
| `feature-grid` | first-class alias | should merge into Columns | The public renderer now delegates to `columns.variant = cards`; prefer Columns for new content. |
| `stats` | first-class alias | should merge into Columns | The public renderer now delegates to the existing `columns.variant = stats` path. |
| `metric-card` | first-class alias | should merge into stat primitives | The public renderer now uses the same `wb-stat` direction as the Columns stats variant. |
| `logo-cloud` | fallback-only | should become first-class | Only promote if there is a repeatable need for structured logo/media rows. |
| `testimonial` | first-class alias | should merge into Quote | The public renderer now delegates to the quote testimonial variant. |
| `timeline` | fallback-only | should become first-class | Promote only with structured milestones and a clear shipped UI pattern. |
| `pricing` | fallback-only | should become first-class | A pricing block needs structured plan, feature, and CTA fields to be worth promoting. |
| `toc` | first-class public renderer | acceptable | Minimal TOC rendering now uses existing heading anchors and `wb-link-list`; active-section behavior is still deferred. |
| `breadcrumb` | fallback-only | should become first-class | Only add when the public shell truly requires breadcrumb navigation. |
| `cookie-notice` | fallback-only | should stay fallback/custom | Public consent UI already lives in the layout shell, so this block should not compete with the shared privacy pattern. |

## Renderer Rules

- Blade renderers must prefer shipped `wb-*` classes.
- No new one-off public CSS classes unless documented as a WebBlocks UI gap.
- No inline scripts in block renderers.
- Interactive blocks must use shipped WebBlocks UI data hooks first.
- CMS-specific JS belongs in `public/assets/webblocks-cms/js` only when necessary.
- Overlay, dialog, and modal content must use `#wb-overlay-root`.
- Admin fields must not require editors to paste raw WebBlocks UI HTML for normal blocks.
- Blocks should expose structured fields and variants, not raw HTML, whenever possible.
- `README.md` must be updated after meaningful renderer or admin behavior changes.

## Phase Plan

### Phase 1

- Documentation only.
- Establish renderer contract.
- No renderer rewrite yet.

### Phase 2

- Align public layout and slot wrappers.
- Ensure main content can render with `wb-content-shell` where appropriate.
- Add tests for shell and slot class output.

### Phase 3

- Make hero, card-grid or feature-grid, button-group, code, and docs navigation first-class or deliberately deprecated/fallback.
- Add admin forms and translation registry support where needed.

Phase 3 completed: core public blocks now align with WebBlocks UI primitives.

### Phase 4

- Migrate existing seeded/demo docs content to first-class blocks.
- Remove reliance on raw HTML or `settings.items` where a structured block exists.
