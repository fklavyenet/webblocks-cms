---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/public-block-render-markup
cms_title: Public Block Render Markup
cms_layout: docs
cms_source_id: webblocks-cms:docs/public-block-render-markup.md
---

# Public Block Render Markup

This file answers the practical page-building question: "When I add this CMS block, which public HTML and WebBlocks UI classes are rendered?" Use it when composing pages manually, reviewing Page Converter output, or asking an AI assistant to build a CMS page from structured blocks.

Root-owning blocks render their own semantic public root and place `data-wb-public-block-type` on that root. Examples include Section, Container, Grid, Card, Hero, Gallery, and Navbar. Non-root-owning blocks render their inner markup and are normally wrapped by the slot renderer in `.wb-public-block` with `data-wb-public-block-type`. Examples include Plain Text, Rich Text, Button Link, Alert, and most navigation utility children.

This file is prepared from the real public renderer Blade sources. Example HTML is representative, but it must stay compatible with the renderer contract. The primary package renderer source is `packages/webblocks-cms/resources/views/pages/partials/blocks/*.blade.php`; root compatibility files under `resources/views/pages/partials/blocks/*.blade.php` generally delegate back to the package namespace. `Block::publicRenderView()` resolves package block partials first, then root compatibility partials, then the safe fallback/missing-renderer partial.

## Recommended block composition for new pages

Prefer these blocks for new hand-built CMS pages:

- Layout and rhythm: `section`, `container`, `grid`, `cluster`.
- Page and section copy: `content_header`, `header`, `plain_text`, `rich-text`, `button_link`, `button` when it is a managed Hero/CTA action.
- Marketing and structured content: `hero`, `cta`, `columns` with `column_item`, `card` with `card_header`, `card_body`, and `card_footer`, `accordion` with FAQ-style child rows, `gallery`, `image`.
- Navigation and shared chrome: `sticky-navbar`, `navbar-brand`, `navbar-navigation`, `header-actions`, `search-form`, plus sidebar blocks for docs/sidebar layouts.

Do not prefer these for new hand-built pages unless you are preserving legacy content, converter output, or compatibility behavior: `feature-grid`, `feature-item`, `card-grid`, `contact-info`, `faq-list`, `menu`, `metric-card`, `showcase-list`, `stats`, `tabs`, `testimonial`, `text`, `callout`, and fallback-only renderer paths.

Use Safe HTML (`html`) and fallback renderers only as reviewed fallbacks when the content cannot yet be represented by structured CMS blocks. They should not be the default output for migrated or AI-created pages.

For public engagement, use normal heading/copy blocks before the system behavior block:

```text
Section
└── Container
    ├── Content Header
    ├── Rating
    └── Comments
```

`rating` stores lightweight 1-5 star feedback without creating comment rows and renders WebBlocks UI `wb-rating` stars (a read-only average plus a no-JS star input); it supports an optional `settings.title` heading but otherwise composes visible copy with neighboring content blocks. `comments` stores visitor text for moderation and only renders approved comments publicly. Both blocks own their public `section.wb-card` root, and `comments` intentionally does not own a visible section heading.

## Common page trees

Marketing page:

```text
Page
└── main slot
    └── Section
        └── Container
            ├── Content Header or Hero
            ├── Grid or Columns
            └── CTA
```

Card grid:

```text
Section
└── Container
    └── Grid
        └── Card
            ├── Card Header
            ├── Card Body
            └── Card Footer
```

Navbar / shared header:

```text
Navbar
└── Container
    └── Cluster
        ├── Navbar Brand
        └── Cluster
            ├── Navbar Navigation
            └── Header Actions
```

FAQ:

```text
Section
└── Container
    └── Accordion
        ├── FAQ child row
        ├── FAQ child row
        └── FAQ child row
```

The current Accordion renderer consumes published child rows with title and content as disclosure items. Page Converter writes adjacent `<details>` groups into `faq` child rows when both the `accordion` and `faq` contracts are available.

The default main slot wrapper renders non-root-owning blocks inside:

```html
<div class="wb-public-block" data-wb-public-block-type="plain-text">
  ...
</div>
```

Root-owning blocks instead put `data-wb-public-block-type` on their own public root element.

## Section (`section`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/section.blade.php`

### Rendered HTML

```html
<section class="wb-section wb-section-lg wb-stack" data-wb-public-block-type="section">
  <!-- child blocks -->
</section>
```

### Main CSS / WebBlocks UI classes

`wb-section`, spacing class from `sectionSpacingClass()`, `wb-stack`, and optional native WebBlocks UI `wb-background-media` plus an overlay modifier.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.spacing | sm | `sectionSpacingClass()` adds `wb-section-sm`. |
| settings.spacing | lg | `sectionSpacingClass()` adds `wb-section-lg`. |
| settings.spacing | default/empty | Uses only `wb-section wb-stack`. |
| media_id | image Media record | Adds `wb-background-media` and emits `--wb-background-media-image` with the safe Media Library URL. |
| settings.background_position | center/top/bottom/left/right | Emits `--wb-background-media-position`; unknown values fall back to `center`. |
| settings.background_overlay | soft/default | Uses the primitive's default soft overlay. |
| settings.background_overlay | none/medium/strong | Adds `wb-background-media--overlay-{value}`. |
| status | unpublished | Block is not included by normal public block tree queries. |

### Use for / Avoid for

Use for: major page sections and block grouping.

Avoid for: putting visible text directly in Section settings; use child copy blocks.

### Notes

The block owns its root `<section>`. It renders all child blocks through the normal block partial. It has no translatable text contract; visible content should be child blocks.

## Container (`container`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/container.blade.php`

### Rendered HTML

```html
<div class="wb-container wb-container-lg wb-stack" data-wb-public-block-type="container">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-container`, width class from `containerWidthClass()`, flow class from `containerFlowClass()`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.width | sm/md/lg/xl/full | `containerWidthClass()` adds `wb-container-sm`, `wb-container-md`, `wb-container-lg`, `wb-container-xl`, or `wb-container-full`. |
| settings.width | default/empty | Uses base `wb-container`. |
| settings.flow | stack/default | `containerFlowClass()` adds `wb-stack`. |
| settings.flow | none | No flow class is added. |

### Use for / Avoid for

Use for: width control inside sections, navbars, and page shells.

Avoid for: using Container as a visual card or content surface.

### Notes

The block owns its root `<div>`. It renders child blocks directly. Width and flow settings change classes.

## Grid (`grid`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/grid.blade.php`

### Rendered HTML

```html
<div class="wb-grid wb-grid-3 wb-gap-4" data-wb-public-block-type="grid">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-grid`, column class from `gridColumnsClass()`, gap class from `gridGapClass()`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.columns | 2 | `gridColumnsClass()` adds `wb-grid-2`. |
| settings.columns | 4 | `gridColumnsClass()` adds `wb-grid-4`. |
| settings.columns | default/3/other | `gridColumnsClass()` adds `wb-grid-3`. |
| settings.gap | 3/4/6 | `gridGapClass()` adds `wb-gap-3`, `wb-gap-4`, or `wb-gap-6`. |
| settings.gap | default/empty | No gap class is added beyond `wb-grid`. |
| settings.alternate_media_text_sections | true | Direct media/text child pairs are reordered by detected visual content while preserving the `wb-grid` wrapper. Sibling alternating Grids under the same parent share one sequence, including across intervening non-alternating blocks. |
| settings.alternate_start | media_left/text_left | Sets the first alternating Grid direction for the sibling sequence; later alternating sibling Grids follow the sequence position rather than their own start value. |

### Use for / Avoid for

Use for: multi-column card, media, or content layouts.

Avoid for: simple button rows or navbar internals; use Cluster.

### Notes

The block owns its root `<div>`. It renders child blocks directly. Column and gap settings change classes.

## Cluster (`cluster`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/cluster.blade.php`

### Rendered HTML

```html
<div class="wb-cluster wb-cluster-2 wb-cluster-between wb-cluster-center" data-wb-public-block-type="cluster">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-cluster`, plus classes from gap, justify, align, wrap, and width helpers.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.gap | none | `clusterGapClass()` adds `wb-cms-cluster-gap-none`. |
| settings.gap | xs | `clusterGapClass()` adds `wb-gap-1`. |
| settings.gap | sm or 2 | `clusterGapClass()` adds `wb-cluster-2`. |
| settings.gap | md or 4 | `clusterGapClass()` adds `wb-cluster-4`. |
| settings.gap | lg or 6 | `clusterGapClass()` adds `wb-cluster-6`. |
| settings.alignment | center/end/between | `clusterAlignmentClass()` adds `wb-cluster-center`, `wb-cluster-end`, or `wb-cluster-between`. |
| settings.items_alignment | start/end/stretch | `clusterAlignClass()` adds `wb-items-start`, `wb-items-end`, or `wb-cms-items-stretch`; `center` adds no class. |
| settings.wrap | nowrap | `clusterWrapClass()` adds `wb-flex-nowrap`. |
| settings.width | full | `clusterWidthClass()` adds `wb-w-full`. |

### Use for / Avoid for

Use for: horizontal composition, action rows, and navbar internals.

Avoid for: using Grid for a single horizontal row of small controls.

### Notes

The block owns its root `<div>`. It renders child blocks directly. This is the normal horizontal composition primitive for buttons and navbar internals.

## Content Header (`content_header`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/content_header.blade.php`

### Rendered HTML

```html
<header class="wb-content-header wb-text-center" data-wb-public-block-type="content-header">
  <div class="wb-cms-public-kicker">
    <i class="wb-icon wb-icon-sparkles" aria-hidden="true"></i>
    <span class="wb-badge wb-badge-info">Beta</span>
  </div>
  <h1 class="wb-content-title">Page title</h1>
  <p class="wb-content-subtitle">Intro text</p>
  <div class="wb-content-meta">
    <span>Meta item</span>
    <span class="wb-content-meta-divider"></span>
    <span>Another item</span>
  </div>
</header>
```

### Main CSS / WebBlocks UI classes

`wb-content-header`, optional alignment class, `wb-cms-public-kicker`, `wb-icon wb-icon-{slug}`, optional `wb-icon-tone-{tone}`, `wb-badge`, `wb-content-title`, `wb-content-subtitle`, `wb-content-meta`, `wb-content-meta-divider`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.alignment | left/center/right | `contentHeaderAlignmentClass()` adds `wb-text-left`, `wb-text-center`, or `wb-text-right`. |
| settings.icon_slug | active content icon catalog slug | Renders a decorative `<i class="wb-icon wb-icon-{slug}" aria-hidden="true">`; inactive or unknown slugs render nothing. |
| settings.icon_tone | default/soft/brand/accent/highlight/bold/quiet | Adds `wb-icon-tone-{tone}` for non-default visual tones when an active icon renders; unknown tones and missing icons produce no tone class. |
| settings.badge_tone | neutral/info/success/warning/danger | Adds an allowlisted badge tone class when a badge label is present. |
| eyebrow / badge label | translated text | Renders escaped badge text. |
| title | any text | Renders as `<h1 class="wb-content-title">`. |
| subtitle | any text | Renders `<p class="wb-content-subtitle">` when present. |
| meta | list | Renders `.wb-content-meta` spans separated by `.wb-content-meta-divider`. |

### Use for / Avoid for

Use for: page or major section intros with title, intro, and metadata.

Avoid for: nested card content or places where the heading should not be an H1.

### Notes

The block owns its root `<header>`. It always renders the title as `<h1>`. It is not a container.

## Hero (`hero`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/hero.blade.php`

### Rendered HTML

```html
<section class="wb-card wb-promo wb-card-accent" data-wb-public-block-type="hero">
  <div class="wb-card-body wb-promo-copy wb-stack wb-gap-3 wb-text-center">
    <p class="wb-eyebrow">Eyebrow</p>
    <h1 class="wb-promo-title">Hero title</h1>
    <p class="wb-promo-text">Hero copy.</p>
    <div class="wb-promo-actions wb-cluster wb-cluster-2">
      <a href="/start" class="wb-btn wb-btn-primary">Start</a>
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-promo`, optional `wb-card-muted` or `wb-card-accent`, `wb-card-body`, `wb-promo-copy`, `wb-stack`, `wb-gap-3`, optional `wb-text-center`, `wb-eyebrow`, `wb-promo-title`, `wb-promo-text`, `wb-promo-actions`, `wb-cluster`, `wb-btn`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | muted or soft | Adds `wb-card-muted` to the promo card. |
| variant | accent | Adds `wb-card-accent` to the promo card. |
| variant | default/other | Uses `wb-card wb-promo` without muted/accent modifier. |
| settings.layout | centered | Adds `wb-text-center` to `.wb-promo-copy`. |
| settings.title_tag | h1/h2/h3 | Hero allows `h1`, `h2`, or `h3`; CTA renders its title as `h2`. |
| child button blocks | published with label and URL | Renders up to two managed `.wb-btn` actions through `_actions`. |

### Use for / Avoid for

Use for: top-of-page marketing introductions with managed CTA buttons.

Avoid for: general section grouping or arbitrary nested content.

### Notes

The block owns its root promo `<section>`. It accepts only `button` child blocks for managed actions, and renders at most two published children with URL and label. `settings.title_tag` may change the heading element to `h1`, `h2`, or `h3`.

## CTA (`cta`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/cta.blade.php`

### Rendered HTML

```html
<section class="wb-card wb-promo wb-card-muted" data-wb-public-block-type="cta">
  <div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">
    <p class="wb-eyebrow">Ready</p>
    <h2 class="wb-promo-title">Start now</h2>
    <p class="wb-promo-text">CTA body.</p>
    <div class="wb-promo-actions wb-cluster wb-cluster-2">
      <a href="/contact" class="wb-btn wb-btn-primary">Contact</a>
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-promo`, optional `wb-card-muted` or `wb-card-accent`, `wb-card-body`, `wb-promo-copy`, `wb-stack`, `wb-gap-3`, `wb-eyebrow`, `wb-promo-title`, `wb-promo-text`, `wb-promo-actions`, `wb-cluster`, `wb-btn`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | muted or soft | Adds `wb-card-muted` to the promo card. |
| variant | accent | Adds `wb-card-accent` to the promo card. |
| variant | default/other | Uses `wb-card wb-promo` without muted/accent modifier. |
| settings.layout | centered | Adds `wb-text-center` to `.wb-promo-copy`. |
| settings.title_tag | h2 fixed in renderer | Hero allows `h1`, `h2`, or `h3`; CTA renders its title as `h2`. |
| child button blocks | published with label and URL | Renders up to two managed `.wb-btn` actions through `_actions`. |

### Use for / Avoid for

Use for: focused conversion sections with a short action row.

Avoid for: full page intros; use Hero or Content Header.

### Notes

The block owns its root promo `<section>`. It accepts only `button` child blocks for managed actions, and renders at most two published children with URL and label.

## Card (`card`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/card.blade.php`

### Rendered HTML

```html
<article class="wb-card" data-wb-public-block-type="card">
  <div class="wb-card-header">...</div>
  <div class="wb-card-body">...</div>
  <div class="wb-card-footer">...</div>
</article>
```

Legacy no-region fallback:

```html
<article class="wb-card" data-wb-public-block-type="card">
  <div class="wb-card-header">Subtitle</div>
  <div class="wb-card-body wb-stack wb-gap-2">
    <strong>Title</strong>
    <p class="wb-m-0">Description</p>
  </div>
  <div class="wb-card-footer">
    <a href="/target" class="wb-btn wb-btn-secondary">Action</a>
  </div>
</article>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-header`, `wb-card-body`, `wb-card-footer`, optional `wb-stack`, `wb-gap-2`, `wb-m-0`, `wb-btn`, `wb-btn-secondary`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.layout_name | any stored value | Admin/layout metadata only; no public class effect in the current renderer. |
| child blocks | published children | Rendered inside the card or region root. |
| legacy card fields | older Card rows without region children | Card parent can render a minimal legacy header/body/footer fallback. |

### Use for / Avoid for

Use for: framed repeated items with explicit Header, Body, and Footer children.

Avoid for: wrapping whole page sections or nesting cards inside cards.

### Notes

The block owns its root `<article>`. Normal structure is child `card_header`, `card_body`, and `card_footer` region blocks. The fallback path only applies to older saved card rows without region children.

## Card Header (`card_header`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/card_header.blade.php`

### Rendered HTML

```html
<div class="wb-card-header" data-wb-public-block-type="card-header">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card-header`, optional `wb-icon wb-icon-{slug}`, optional `wb-icon-tone-{tone}`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.layout_name | any stored value | Admin/layout metadata only; no public class effect in the current renderer. |
| settings.icon_slug | active content icon catalog slug | Renders a decorative icon before child content; inactive or unknown slugs render nothing. |
| settings.icon_tone | default/soft/brand/accent/highlight/bold/quiet | Adds `wb-icon-tone-{tone}` for non-default visual tones when an active icon renders; unknown tones and missing icons produce no tone class. |
| child blocks | published children | Rendered inside the card or region root. |
| legacy card fields | older Card rows without region children | Card parent can render a minimal legacy header/body/footer fallback. |

### Use for / Avoid for

Use for: the header region inside Card.

Avoid for: placing outside Card.

### Notes

The block owns its card-region root and renders child blocks. It is intended only under `card`.

## Card Body (`card_body`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/card_body.blade.php`

### Rendered HTML

```html
<div class="wb-card-body" data-wb-public-block-type="card-body">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card-body`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.layout_name | any stored value | Admin/layout metadata only; no public class effect in the current renderer. |
| child blocks | published children | Rendered inside the card or region root. |
| legacy card fields | older Card rows without region children | Card parent can render a minimal legacy header/body/footer fallback. |

### Use for / Avoid for

Use for: the main content region inside Card.

Avoid for: placing outside Card.

### Notes

The block owns its card-region root and renders child blocks. It is intended only under `card`.

## Card Footer (`card_footer`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/card_footer.blade.php`

### Rendered HTML

```html
<div class="wb-card-footer" data-wb-public-block-type="card-footer">
  <!-- child blocks -->
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card-footer`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.layout_name | any stored value | Admin/layout metadata only; no public class effect in the current renderer. |
| child blocks | published children | Rendered inside the card or region root. |
| legacy card fields | older Card rows without region children | Card parent can render a minimal legacy header/body/footer fallback. |

### Use for / Avoid for

Use for: actions or supporting footer content inside Card.

Avoid for: placing outside Card.

### Notes

The block owns its card-region root and renders child blocks. It is intended only under `card`.

## Header (`header`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/header.blade.php`

### Rendered HTML

```html
<h2 class="wb-text-center" id="section-anchor" data-wb-public-block-type="header">Heading</h2>
```

### Main CSS / WebBlocks UI classes

Optional alignment class from `headerAlignmentClass()`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | h1-h6 | Controls the heading element. |
| variant | empty/other | Defaults to `<h2>`. |
| settings.alignment | left/center/right | `headerAlignmentClass()` adds `wb-text-left`, `wb-text-center`, or `wb-text-right`. |
| settings.anchor or legacy URL fallback | safe anchor string | Adds an `id` attribute for same-page links and TOC. |

### Use for / Avoid for

Use for: semantic headings inside page content.

Avoid for: page intros that need subtitle/meta; use Content Header.

### Notes

The block owns the actual heading element (`h1` through `h6`). `variant` controls the heading level, defaulting to `h2`. `settings.anchor` or legacy URL fallback can add `id`.

## Plain Text (`plain_text`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/plain_text.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="plain-text">
  <p class="wb-text-center">Plain copy.</p>
</div>
```

### Main CSS / WebBlocks UI classes

Optional alignment class from `plainTextAlignmentClass()`. The generic wrapper uses `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.alignment | left/center/right | `plainTextAlignmentClass()` adds `wb-text-left`, `wb-text-center`, or `wb-text-right` to `<p>`. |
| content | text | Escaped into a single paragraph. |
| status | unpublished | Block is not included by normal public block tree queries. |

### Use for / Avoid for

Use for: short unformatted copy.

Avoid for: formatted paragraphs, lists, links, or headings; use Rich Text/Header.

### Notes

The block does not own its public root according to `Block::ownsPublicRoot()`, so normal slot rendering wraps it. The renderer itself emits only `<p>`.

## Rich Text (`rich-text`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/rich-text.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="rich-text">
  <div class="wb-rich-text wb-rich-text-readable">
    <p>Safe <strong>formatted</strong> copy.</p>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-rich-text`, `wb-rich-text-readable`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| content | safe rich HTML | `SafeRichTextRenderer` outputs sanitized content inside `wb-rich-text wb-rich-text-readable`. |
| content | empty after sanitization | Renderer emits nothing. |
| unsupported HTML/classes | present in content | Stripped by the safe renderer rather than mapped to public classes. |

### Use for / Avoid for

Use for: body copy with safe inline formatting, links, and simple lists.

Avoid for: headings, media, tables, buttons, or raw layout markup.

### Notes

The renderer emits nothing when sanitized content is empty. It does not own the slot-level root. Content is rendered through `SafeRichTextRenderer`.

## Button Link (`button_link`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/button_link.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="button-link">
  <a href="/start" class="wb-btn wb-btn-primary">Start</a>
</div>
```

### Main CSS / WebBlocks UI classes

Class from `buttonLinkVariantClass()`, usually `wb-btn` plus a variant class.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | secondary | `buttonLinkVariantClass()` outputs `wb-btn wb-btn-secondary`. |
| variant | default/other | `buttonLinkVariantClass()` outputs `wb-btn wb-btn-primary`. |
| settings.url | safe URL | Renders the anchor `href`; unsafe/empty URL renders nothing. |
| settings.target | _blank | Adds `target="_blank" rel="noopener noreferrer"`. |

### Use for / Avoid for

Use for: standalone editorial links styled as buttons.

Avoid for: managed Hero/CTA actions where the `button` child contract is expected.

### Notes

The renderer emits nothing without a safe URL. `_blank` target adds `target="_blank"` and `rel="noopener noreferrer"`. It normally receives the generic public block wrapper unless nested in a root-owning parent.

## Image (`image`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/image.blade.php`

### Rendered HTML

```html
<figure class="wb-stack wb-gap-2" data-wb-public-block-type="image">
  <a href="/target">
    <img src="/media/photo.jpg" alt="Alt text" width="1200" height="800">
  </a>
  <figcaption>Caption</figcaption>
</figure>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| media_id | selected media | Renders `<figure>` with `<img>` using media URL and dimensions. |
| url | safe URL | Wraps only the image in an anchor. |
| caption | translated text | Renders `<figcaption>` when present. |
| alt text | translated or media fallback | Renders the image `alt` attribute. |

### Use for / Avoid for

Use for: single semantic images with optional caption/link.

Avoid for: image collections; use Gallery.

### Notes

The block owns its `<figure>` root and emits nothing without media. Optional link wraps only the image when the URL is safe (`http`, `https`, `/`, `#`, `mailto`, or `tel`).

## Gallery (`gallery`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/gallery.blade.php`

### Rendered HTML

```html
<div class="wb-gallery wb-gallery--grid wb-gallery--cols-3 wb-gallery--gap-4 wb-gallery--aspect-16-9"
  data-wb-public-block-type="gallery"
  data-wb-gallery-variant="grid"
  data-wb-gallery-captions="below"
  aria-label="Gallery">
  <div class="wb-gallery-grid">
    <figure class="wb-gallery-item">
      <a href="/media/full.jpg" class="wb-gallery-trigger" data-wb-gallery-target="#wb-gallery-viewer-10">
        <img src="/media/thumb.jpg" alt="Alt" class="wb-gallery-media">
      </a>
      <figcaption class="wb-gallery-caption">Caption</figcaption>
    </figure>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-gallery`, `wb-gallery--{variant}`, `wb-gallery--cols-{n}`, `wb-gallery--gap-{n}`, `wb-gallery--aspect-{ratio}`, `wb-gallery-grid`, `wb-gallery-item`, `wb-gallery-trigger` or `wb-gallery-link`, `wb-gallery-media`, `wb-gallery-caption`, `wb-gallery-caption-title`, `wb-gallery-caption-meta`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.variant | grid/masonry/masonary/collage | `galleryVariant()` adds `wb-gallery--grid`, `wb-gallery--masonry`, or `wb-gallery--collage`; `masonary` normalizes to `masonry`. |
| settings.columns | 2/3/4/5 | `galleryColumns()` adds `wb-gallery--cols-{n}`; default is `3`. |
| settings.gap | none/sm/md/lg | `galleryGap()` adds `wb-gallery--gap-{value}`; default is `md`. |
| settings.aspect_ratio | auto/square/4:3/16:9/portrait | `galleryAspectRatio()` adds `wb-gallery--aspect-{value}` with `:` rendered as `-`. |
| settings.captions_mode | hidden/below/overlay/on-hover | Controls below captions or overlay caption markup; default is `below`. |
| settings.overlay_mode | none/gradient/solid | Controls overlay caption modifier; default is `gradient`. |
| settings.lightbox_enabled | true/default | Uses `.wb-gallery-trigger` and registers `gallery-viewer` in `PublicOverlayRegistry`. |
| settings.lightbox_enabled | false | Uses `.wb-gallery-link` and no viewer modal. |
| settings.viewer_title | text/null | Renders a lightbox-only `.wb-gallery-viewer-title` above the viewer toolbar when present. |

### Use for / Avoid for

Use for: structured image collections with captions and optional lightbox.

Avoid for: section intro copy; place Content Header/Rich Text before Gallery.

### Notes

The block owns its gallery root and registers `gallery-viewer` HTML in `PublicOverlayRegistry` when lightbox is enabled. Variants, columns, gap, aspect ratio, captions, overlay mode, and lightbox settings change attributes/classes. Grid uses equal CSS grid columns, Masonry uses CSS columns with natural image heights, and Collage enlarges the first item in each visual group. Grid and Masonry can look similar when the selected images have similar aspect ratios. Legacy settings-based items remain readable. Technical migration notes such as `Imported from ... during ... migration` are ignored when public Gallery output falls back to media or legacy item captions, overlay meta, and lightbox metadata.

## Columns (`columns`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/columns.blade.php`

### Rendered HTML

```html
<section class="wb-stack wb-gap-4" data-wb-public-block-type="columns">
  <div class="wb-stack wb-gap-1">
    <h2>Columns title</h2>
    <p class="wb-text-muted">Subtitle</p>
  </div>
  <div class="wb-stack wb-gap-2">
    <p class="wb-m-0">Intro copy.</p>
  </div>
  <div class="wb-grid wb-grid-3">
    <!-- column_item or feature-item output -->
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-4`, `wb-gap-1`, `wb-text-muted`, `wb-gap-2`, `wb-m-0`, `wb-grid`, `wb-grid-2`, `wb-grid-3`, `wb-grid-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | cards | Child items render card-style. |
| variant | stats | Child items render stat-style. |
| variant | plain/default | Child items render plain stack-style. |
| child count | 1 | Uses `wb-stack wb-gap-3` for the item layout. |
| child count | 2/3/4+ | Uses `wb-grid wb-grid-2`, `wb-grid-3`, or `wb-grid-4`. |

### Use for / Avoid for

Use for: structured feature, stat, or simple column groups.

Avoid for: arbitrary layout grids where Grid + Card is clearer.

### Notes

The block owns its root `<section>`. Child count controls grid class: one child uses `wb-stack wb-gap-3`, two uses `wb-grid wb-grid-2`, three uses `wb-grid wb-grid-3`, otherwise `wb-grid wb-grid-4`. `variant` is passed to child `column_item`/`feature-item` renderers.

## Column Item (`column_item`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/column_item.blade.php`

### Rendered HTML

Cards variant:

```html
<div class="wb-card">
  <div class="wb-card-body wb-stack wb-gap-2">
    <a href="/item" class="wb-no-decoration">
      <div class="wb-stack wb-gap-2">
        <strong>Title</strong>
        <p class="wb-m-0">Copy.</p>
      </div>
    </a>
  </div>
</div>
```

Plain variant:

```html
<div class="wb-stack wb-gap-2">
  <strong>Title</strong>
  <p class="wb-m-0">Copy.</p>
</div>
```

Stats variant:

```html
<div class="wb-stat">
  <div class="wb-stat-label">Label</div>
  <div class="wb-stat-value">Value</div>
  <div class="wb-stat-delta">Detail</div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-body`, `wb-stack`, `wb-gap-2`, `wb-no-decoration`, `wb-m-0`, `wb-stat`, `wb-stat-label`, `wb-stat-value`, `wb-stat-delta`, optional `wb-icon wb-icon-{slug}`, optional `wb-icon-tone-{tone}`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| parent columns variant | cards | Renders `.wb-card > .wb-card-body`. |
| parent columns variant | stats | Renders `.wb-stat` with label/value/detail. |
| parent columns variant | plain/default | Renders `.wb-stack wb-gap-2`. |
| url | safe URL | Cards/plain variants wrap content in `a.wb-no-decoration`; stats do not render a link in current renderer. |
| settings.icon_slug | active content icon catalog slug | Renders a decorative icon in the item kicker; inactive or unknown slugs render nothing. |
| settings.icon_tone | default/soft/brand/accent/highlight/bold/quiet | Adds `wb-icon-tone-{tone}` for non-default visual tones when an active icon renders; unknown tones and missing icons produce no tone class. |

### Use for / Avoid for

Use for: children of Columns.

Avoid for: standalone page content; use Card or Stat Card when independent.

### Notes

This renderer changes substantially based on the parent `columns` variant. It does not own a slot-level root and is normally rendered by the parent.

## Feature Grid (`feature-grid`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/feature-grid.blade.php`

### Rendered HTML

```html
<section class="wb-stack wb-gap-4" data-wb-public-block-type="feature-grid">
  <div class="wb-grid wb-grid-3">
    <div class="wb-card">...</div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

Delegates to Columns: `wb-stack`, `wb-gap-4`, `wb-grid`, `wb-card`, and related `column_item` classes.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated alias | Delegates to an existing renderer path; exact setting map follows the delegate where visible. |
| preferred new pages | n/a | Prefer the canonical block named in Notes instead of this alias/compatibility block. |

### Use for / Avoid for

Use for: legacy feature-grid content that already exists.

Avoid for: new hand-built pages; use Columns with Column Item or Grid with Card.

### Notes

The renderer replicates the block, filters children to `feature-item` and legacy-compatible `column_item`, forces `variant = cards`, and includes the Columns renderer. It does not own public root according to the helper, so top-level rendering may add `wb-public-block`.

## Feature Item (`feature-item`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/feature-item.blade.php`

### Rendered HTML

```html
<div class="wb-card">
  <div class="wb-card-body wb-stack wb-gap-2">
    <div class="wb-cms-public-kicker">
      <i class="wb-icon wb-icon-sparkles wb-icon-tone-brand" aria-hidden="true"></i>
      <span class="wb-badge wb-badge-info">Badge</span>
    </div>
    <strong>Feature title</strong>
    <p class="wb-m-0">Feature copy.</p>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

Delegates to Column Item cards variant: `wb-card`, `wb-card-body`, `wb-stack`, `wb-gap-2`, `wb-cms-public-kicker`, optional `wb-icon wb-icon-{slug}`, optional `wb-icon-tone-{tone}`, optional `wb-badge`, `wb-m-0`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.icon_slug | active content icon catalog slug | Renders a decorative `<i class="wb-icon wb-icon-{slug}" aria-hidden="true">`; inactive or unknown slugs render nothing. |
| settings.icon_tone | default/soft/brand/accent/highlight/bold/quiet | Adds `wb-icon-tone-{tone}` for non-default visual tones when an active icon renders; unknown tones and missing icons produce no tone class. |
| eyebrow / badge_label | text | Renders escaped badge text in the kicker. |
| settings.badge_tone | neutral/info/success/warning/danger | Adds the matching `wb-badge-{tone}` class; unknown values fall back to neutral. |
| renderer behavior | delegated alias | Delegates to an existing renderer path; exact setting map follows the delegate where visible. |
| preferred new pages | n/a | Prefer the canonical block named in Notes instead of this alias/compatibility block. |

### Use for / Avoid for

Use for: legacy Feature Grid children.

Avoid for: new standalone feature cards; use Card regions or Column Item.

### Notes

This renderer includes `column_item` with `columnsVariant = cards`. It is intended as a child of `feature-grid`.

## Accordion (`accordion`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/accordion.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="accordion">
  <div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <h3>Questions</h3>
      <p>Intro copy.</p>
    </div>
    <div class="wb-stack-2">
      <details>
        <summary>Question?</summary>
        <div>Answer.</div>
      </details>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-3`, `wb-gap-1`, `wb-stack-2`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title/subtitle | present | Renders intro heading/copy above items. |
| child rows | published with title and content | Renders each as `<details><summary>...` disclosure item. |
| child rows | missing title or content | Skipped by current item collection. |
| wrapper | top-level non-root block | Receives generic `.wb-public-block` wrapper from slot renderer. |

### Use for / Avoid for

Use for: FAQ/disclosure groups.

Avoid for: single Q/A cards; use FAQ only for compatibility child rows.

### Notes

The block renders child rows as accordion items when each child has title and content. It does not own the slot-level root. Page Converter writes details items to `faq` rows when the contract is available, and `accordion` consumes child title/content.

## FAQ (`faq`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/faq.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="faq">
  <section class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-2">
      <strong>Question?</strong>
      <p class="wb-m-0">Answer.</p>
    </div>
  </section>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-2`, `wb-m-0`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title/content | present | Renders one muted card with question and answer. |
| children | published children | Renders an additional `wb-stack wb-gap-4` child block area. |
| accordion child usage | inside Accordion | Accordion consumes FAQ row title/content as disclosure item content. |

### Use for / Avoid for

Use for: accordion item child rows and legacy single Q/A cards.

Avoid for: new standalone FAQ sections; use Accordion as the parent.

### Notes

FAQ is a single Q/A card renderer. If children exist, it also renders them in an additional `wb-stack wb-gap-4` block.

## Navbar (`sticky-navbar`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sticky-navbar.blade.php`

### Rendered HTML

```html
<nav class="wb-navbar wb-navbar--static" data-wb-public-block-type="sticky-navbar">
  <!-- navbar child blocks -->
</nav>
```

### Main CSS / WebBlocks UI classes

`wb-navbar`, optional class from `navbarPositionClass()` such as `wb-navbar--static`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.sticky_mode | sticky/default | Uses only base `wb-navbar` for sticky behavior. |
| settings.sticky_mode | static | `navbarPositionClass()` adds `wb-navbar--static`. |
| settings.sticky_mode | fixed | `navbarPositionClass()` adds `wb-fixed`. |
| child blocks | published allowed children | Rendered directly inside `<nav class="wb-navbar...">`. |

### Use for / Avoid for

Use for: shared public header/navigation block trees.

Avoid for: creating a separate custom header shell around navbar markup.

### Notes

The block label is Navbar, but the persisted slug and renderer file are `sticky-navbar`. It owns its outer `<nav>` root and renders child blocks. Allowed child types include `container`, `cluster`, `header`, `plain_text`, `rich-text`, `button_link`, `navbar-brand`, `navbar-navigation`, `header-actions`, and `search-form`.

## Navbar Brand (`navbar-brand`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/navbar-brand.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="navbar-brand">
  <a href="/" class="wb-navbar-brand">
    <img src="/media/logo.svg" alt="Site name">
    <span class="wb-navbar-identity">
      <span>Site name</span>
      <span class="wb-navbar-brand-note">Tagline</span>
    </span>
  </a>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-navbar-brand`, `wb-navbar-identity`, `wb-navbar-brand-note`, plus generic `wb-public-block` unless nested in a root-owning parent.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.url | safe URL | Saved URL wins for the brand link. |
| settings.url | empty | Falls back to site home path, then `/`. |
| settings.target | _blank | Adds `target="_blank" rel="noopener noreferrer"`. |
| settings.aria_label | text | Used as accessible label when no visible text is rendered. |
| media_id | logo media | Renders brand image inside `wb-navbar-brand`. |

### Use for / Avoid for

Use for: site identity inside Navbar.

Avoid for: full header layouts; compose with Navbar/Container/Cluster.

### Notes

The block does not own the outer navbar shell. Saved URL wins, then site home path, then `/`. Logo-only output uses `aria-label` when no text copy is rendered.

## Navbar Navigation (`navbar-navigation`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/navbar-navigation.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="navbar-navigation">
  <div class="wb-cms-navbar-navigation">
    <div class="wb-dropdown wb-dropdown-end wb-cms-navbar-mobile-toggle">
      <button class="wb-navbar-toggle wb-cms-navbar-mobile-toggle-button" data-wb-toggle="dropdown">
        <i class="wb-icon wb-icon-menu" aria-hidden="true"></i>
      </button>
      <div class="wb-dropdown-menu wb-cms-navbar-mobile-menu">
        <ul class="wb-navbar-nav wb-cms-navbar-mobile-nav wb-navbar-nav--active-underline">...</ul>
      </div>
    </div>
    <div class="wb-navbar-links">
      <ul class="wb-navbar-nav wb-navbar-nav--active-underline">
        <li class="wb-navbar-nav-item">
          <a href="/" class="wb-navbar-link is-active" aria-current="page">Home</a>
        </li>
      </ul>
    </div>
  </div>
</div>
```

Dropdown group variant:

```html
<li class="wb-navbar-nav-item wb-dropdown">
  <button class="wb-navbar-link" data-wb-toggle="dropdown" data-wb-target="#navbar-navigation-group-1-2">
    Group <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
  </button>
  <div class="wb-dropdown-menu" id="navbar-navigation-group-1-2">
    <a class="wb-dropdown-item" href="/child">Child</a>
  </div>
</li>
```

### Main CSS / WebBlocks UI classes

`wb-cms-navbar-navigation`, `wb-dropdown`, `wb-dropdown-end`, `wb-cms-navbar-mobile-toggle`, `wb-navbar-toggle`, `wb-cms-navbar-mobile-toggle-button`, `wb-icon`, `wb-icon-menu`, `wb-dropdown-menu`, `wb-cms-navbar-mobile-menu`, `wb-navbar-nav`, `wb-cms-navbar-mobile-nav`, `wb-navbar-links`, `wb-navbar-nav-item`, `wb-navbar-link`, `wb-dropdown-item`, `wb-navbar-nav--active-underline`, `wb-navbar-nav--active-pill`, `wb-navbar-nav--active-dot`, `wb-navbar-nav--active-background`, `wb-navbar-nav--active-none`, `is-active`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.menu_key | known NavigationItem menu key | Selects the CMS navigation tree to render. |
| settings.active_indicator | underline/default | Adds `wb-navbar-nav--active-underline` to desktop and mobile navbar nav lists. |
| settings.active_indicator | pill | Adds `wb-navbar-nav--active-pill`. |
| settings.active_indicator | dot | Adds `wb-navbar-nav--active-dot`. |
| settings.active_indicator | background | Adds `wb-navbar-nav--active-background`. |
| settings.active_indicator | none | Adds `wb-navbar-nav--active-none`; active matching can still be disabled separately. |
| settings.active_matching | path/default | Marks items active when page id, canonical URL, or normalized path matches. |
| settings.active_matching | section | Marks a parent section URL such as `/news` active for descendants such as `/news/article`. |
| settings.active_matching | current-page | Prefers NavigationItem page id matching, falling back to normalized path. |
| settings.active_matching | exact | Requires exact resolved URL match. |
| settings.active_matching | off | Suppresses `is-active` and `aria-current`. |
| title | text | Used as shared ARIA label when present. |
| navigation group item | group with children | Renders WebBlocks UI dropdown trigger and `.wb-dropdown-menu`. |
| active item | current page match | Adds `is-active` and `aria-current="page"`. |

### Use for / Avoid for

Use for: CMS-managed menu links inside Navbar.

Avoid for: manual button rows; use Button Link or Cluster.

### Notes

The actual core slug is `navbar-navigation`. It renders CMS NavigationItem trees from the selected menu key. It does not own the outer navbar shell. It uses WebBlocks UI dropdown hooks for group menus and the mobile menu.

## Header Actions (`header-actions`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/header-actions.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="header-actions">
  <div class="wb-cluster wb-cluster-2 wb-cluster-end" data-wb-header-actions>
    <div class="wb-topbar-actions">
      <a class="wb-topbar-action" data-wb-public-search-open>
        <i class="wb-icon wb-icon-search" aria-hidden="true"></i>
      </a>
      <button class="wb-topbar-action" data-wb-mode-cycle>
        <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-cluster`, `wb-cluster-2`, `wb-cluster-end`, `wb-topbar-actions`, `wb-topbar-action`, `wb-icon`, `wb-icon-search`, `wb-icon-sun-moon`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.show_search | false | Hides public search action. |
| settings.show_mode_toggle | false | Hides mode toggle action. |
| settings.show_accent_toggle | any | Public rendering suppresses preset/accent controls while site-level Public Theme presets are active. |
| default settings | empty/true | Renders search and mode actions. |

### Use for / Avoid for

Use for: standard search and safe color mode utilities in headers.

Avoid for: custom business CTAs; use Button Link/Button.

### Notes

Header Actions does not emit public preset/accent dropdown controls in the site-level Public Theme model. Public pages use the body `data-wb-public-theme` marker for selected theme presets, and CMS public CSS maps that marker to public theme tokens.

Settings can hide the search, mode toggle, or accent/theme menu. This block does not own a slot-level root. It uses WebBlocks UI dropdown hooks and CMS public search hooks.

## Search Form (`search-form`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/search-form.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="search-form">
  <form action="/search" method="GET" role="search" class="wb-cluster wb-cluster-2">
    <div class="wb-stack wb-gap-1 wb-flex-1">
      <label for="search-form-1">Search</label>
      <input id="search-form-1" type="search" name="q" class="wb-input" placeholder="Search this site">
    </div>
    <button type="submit" class="wb-btn wb-btn-primary">Search</button>
  </form>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-cluster`, `wb-cluster-2`, `wb-stack`, `wb-gap-1`, `wb-flex-1`, `wb-input`, `wb-btn`, `wb-btn-primary` or `wb-btn-secondary`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | secondary | Submit button uses `wb-btn wb-btn-secondary`. |
| variant | primary/default | Submit button uses `wb-btn wb-btn-primary`. |
| settings.show_button | false | Omits submit button. |
| search route unavailable | n/a | Renderer emits nothing. |

### Use for / Avoid for

Use for: site search forms.

Avoid for: filter forms or custom external search integrations.

### Notes

The renderer emits nothing if the route resolver cannot produce a search path. `settings.show_button` controls whether the submit button renders.

## Stat Card (`stat-card`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/stat-card.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="stat-card">
  <div class="wb-stat">
    <div class="wb-stat-label">Label</div>
    <div class="wb-stat-value">Value</div>
    <div class="wb-stat-detail">Detail</div>
    <div class="wb-stat-detail"><a href="/more" class="wb-link">Learn more</a></div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stat`, `wb-stat-label`, `wb-stat-value`, `wb-stat-detail`, `wb-link`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| subtitle | text | Renders `.wb-stat-label`. |
| title | text | Renders `.wb-stat-value`. |
| content | text | Renders `.wb-stat-detail`. |
| url | safe URL | Adds `Learn more` link with `.wb-link`. |

### Use for / Avoid for

Use for: standalone metric cards.

Avoid for: multi-metric groups where Columns stats is more compact.

### Notes

This is a non-root-owning content block. It uses subtitle as label, title as value, content as detail, and optional canonical URL for a `Learn more` link.

## Code (`code`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/code.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="code">
  <pre><code data-language="php">php artisan test</code></pre>
</div>
```

### Main CSS / WebBlocks UI classes

No WebBlocks UI class is emitted by the code renderer itself. The generic wrapper uses `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.language | text | Sanitized into `data-language` on `<code>`. |
| settings.lang | text | Legacy fallback for `data-language`. |
| content | empty | Renderer emits nothing. |

### Use for / Avoid for

Use for: code snippets.

Avoid for: formatted prose or command lists; use Rich Text/List as appropriate.

### Notes

The renderer returns early when content is empty. `settings.language` or `settings.lang` becomes a sanitized `data-language` value.

## Download (`download`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/download.blade.php`

### Rendered HTML

```html
<div class="wb-stack wb-gap-2" data-wb-public-block-type="download">
  <a href="/media/file.pdf" class="wb-btn wb-btn-secondary" download>Download file</a>
  <p>Helper copy.</p>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-btn`, `wb-btn-primary`, `wb-btn-secondary`, `wb-btn-ghost`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | primary | Button class is `wb-btn wb-btn-primary`. |
| variant | ghost | Button class is `wb-btn wb-btn-ghost`. |
| variant | secondary/default/other | Button class is `wb-btn wb-btn-secondary`. |
| media_id | download media | Media URL is used; no media means renderer emits nothing. |
| subtitle | text | Renders helper `<p>`. |

### Use for / Avoid for

Use for: media-backed download CTAs.

Avoid for: external file cards; use File when no media asset exists.

### Notes

The block owns its root `<div>`. It emits only when `downloadAsset()` has a URL. `variant` changes the button class.

## File (`file`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/file.blade.php`

### Rendered HTML

```html
<div class="wb-card wb-card-muted" data-wb-public-block-type="file">
  <div class="wb-card-body wb-stack wb-gap-2">
    <strong>File title</strong>
    <p class="wb-m-0">Description.</p>
    <a href="/media/file.pdf" class="wb-btn wb-btn-secondary" download>Download</a>
    <span class="wb-text-sm wb-text-muted">file.pdf | application/pdf</span>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-2`, `wb-m-0`, `wb-btn`, `wb-btn-secondary`, `wb-text-sm`, `wb-text-muted`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| media_id | selected media | Media URL wins over external URL. |
| url | safe external URL | Used only when no media URL is available. |
| title/content | text | Rendered as visible card copy when present. |
| source unavailable | n/a | Renderer emits nothing or no playable control depending on source availability. |

### Use for / Avoid for

Use for: file cards with media or reviewed external URL fallback.

Avoid for: simple media downloads where Download is enough.

### Notes

The block owns its root card. Media URL wins over a safe external `http`, `https`, or `mailto` URL. The button label is `Download` for media and `Open file` for external URL.

## Video (`video`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/video.blade.php`

### Rendered HTML

```html
<div class="wb-card wb-card-muted" data-wb-public-block-type="video">
  <div class="wb-card-body wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <strong>Video title</strong>
      <p class="wb-m-0">Video copy.</p>
    </div>
    <video controls preload="metadata">
      <source src="/media/video.mp4">
    </video>
  </div>
</div>
```

Embed variant:

```html
<iframe src="https://www.youtube.com/embed/abc123" title="Video title" loading="lazy" allowfullscreen></iframe>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-3`, `wb-gap-1`, `wb-m-0`, `wb-btn`, `wb-btn-secondary`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| media_id | selected media | Media URL wins over external URL. |
| url | safe external URL | Used only when no media URL is available. |
| title/content | text | Rendered as visible card copy when present. |
| source unavailable | n/a | Renderer emits nothing or no playable control depending on source availability. |

### Use for / Avoid for

Use for: uploaded video or safe YouTube/Vimeo embeds.

Avoid for: arbitrary iframe HTML; use Trusted HTML only as reviewed fallback.

### Notes

The block owns its root card. Uploaded media renders native `<video>`. Safe YouTube and Vimeo URLs render iframe embeds. Other safe HTTP URLs render an `Open video` button.

## Audio (`audio`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/audio.blade.php`

### Rendered HTML

```html
<div class="wb-card wb-card-muted" data-wb-public-block-type="audio">
  <div class="wb-card-body wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <strong>Audio title</strong>
      <p class="wb-m-0">Audio copy.</p>
    </div>
    <audio controls preload="metadata">
      <source src="/media/audio.mp3">
    </audio>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-3`, `wb-gap-1`, `wb-m-0`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| media_id | selected media | Media URL wins over external URL. |
| url | safe external URL | Used only when no media URL is available. |
| title/content | text | Rendered as visible card copy when present. |
| source unavailable | n/a | Renderer emits nothing or no playable control depending on source availability. |

### Use for / Avoid for

Use for: uploaded or safe external audio media.

Avoid for: podcast/player embeds that need unsupported markup; review Trusted HTML fallback.

### Notes

The block owns its root card. Uploaded media URL wins over a safe external HTTP URL.

## Table (`table`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/table.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="table">
  <div class="wb-stack wb-gap-2">
    <h3>Table title</h3>
    <div class="wb-table-wrap">
      <table class="wb-table">
        <thead>
          <tr><th>Name</th><th>Value</th></tr>
        </thead>
        <tbody>
          <tr><td>One</td><td>Two</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-table-wrap`, `wb-table`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | plain | No header row; all rows render in `<tbody>`. |
| variant | default/other | First row renders in `<thead>`. |
| settings.rows | array | Legacy rows source wins when present. |
| content | pipe-delimited lines | Used as row source when `settings.rows` is empty. |

### Use for / Avoid for

Use for: simple structured tables.

Avoid for: layout grids or complex interactive data tables.

### Notes

Rows come from legacy `settings.rows` when present, otherwise pipe-delimited translated content lines. `variant = plain` disables header-row behavior.

## Quote (`quote`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/quote.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="quote">
  <blockquote class="wb-stack wb-gap-2">
    <p class="wb-m-0">Quote text.</p>
    <footer>Name | Role</footer>
  </blockquote>
</div>
```

Testimonial variant:

```html
<div class="wb-card wb-card-muted">
  <div class="wb-card-body">
    <blockquote class="wb-stack wb-gap-2">...</blockquote>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-m-0`, optional `wb-card`, `wb-card-muted`, `wb-card-body`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | testimonial | Wraps blockquote in `wb-card wb-card-muted > .wb-card-body`. |
| variant | default/other | Renders plain `<blockquote class="wb-stack wb-gap-2">`. |
| subtitle | text | Renders in `<footer>` with title/content context. |

### Use for / Avoid for

Use for: quotations and testimonials.

Avoid for: generic callouts or cards.

### Notes

The renderer itself does not add `data-wb-public-block-type`; top-level slot rendering provides the generic wrapper.

## Link List (`link-list`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/link-list.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="link-list">
  <div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <div class="wb-link-list-meta">Meta</div>
      <h2>Links</h2>
      <p>Description.</p>
    </div>
    <div class="wb-link-list">
      <a href="/item" class="wb-link-list-item">...</a>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-3`, `wb-gap-1`, `wb-link-list`, optional `wb-link-list--stacked`, optional `wb-link-list--cards`, `wb-link-list-meta`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title/subtitle/content | text | Renders optional intro stack above links. |
| settings.row_layout | index (default) / stacked | `stacked` adds `wb-link-list--stacked`, moving each row description under its title beside any leading visual. `index` and unknown values add no class and keep the default description column. |
| settings.list_frame | joined (default) / cards | `cards` adds `wb-link-list--cards`, giving each row its own card frame with spacing. `joined` and unknown values add no class and keep the single shared frame. |
| child link-list-item | published children | Renders children inside `.wb-link-list`. |
| no children | n/a | Renderer has no link rows to display. |

### Use for / Avoid for

Use for: structured lists of links.

Avoid for: full navigation menus; use Navbar/Navigation Auto as appropriate.

### Notes

The block renders published `link-list-item` children only. It does not own the slot-level root.

## Link List Item (`link-list-item`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/link-list-item.blade.php`

### Rendered HTML

```html
<a href="/item" class="wb-link-list-item wb-link-list-item--media">
  <i class="wb-icon wb-icon-book-open wb-icon-tone-brand wb-link-list-icon" aria-hidden="true"></i>
  <div class="wb-link-list-main">
    <span class="wb-link-list-title">Title</span>
    <span class="wb-link-list-meta">Meta</span>
    <span class="wb-badge wb-badge-success">New</span>
  </div>
  <div class="wb-link-list-desc">Description.</div>
</a>
```

A row that leads with a thumbnail instead of an icon:

```html
<a href="/item" class="wb-link-list-item wb-link-list-item--media">
  <img src="/storage/media/guide.jpg" alt="" class="wb-link-list-thumb" loading="lazy" decoding="async">
  <div class="wb-link-list-main">
    <span class="wb-link-list-title">Title</span>
  </div>
</a>
```

### Main CSS / WebBlocks UI classes

`wb-link-list-item`, optional `wb-link-list-item--media`, `wb-link-list-thumb`, `wb-icon wb-icon-{slug}`, optional `wb-icon-tone-{tone}`, `wb-link-list-icon`, `wb-link-list-main`, `wb-link-list-title`, `wb-link-list-meta`, `wb-badge`, `wb-link-list-desc`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| url | safe URL | Required for output; becomes anchor `href`. |
| media_id | image Media record | Renders a `wb-link-list-thumb` image in the row's leading column and adds `wb-link-list-item--media`; non-image records render nothing. Wins over `settings.icon_slug` when both are set. |
| settings.icon_slug | active content icon catalog slug | Renders a decorative icon before the row body and adds `wb-link-list-item--media`; inactive or unknown slugs render nothing. Skipped when a thumbnail renders. |
| settings.icon_tone | default/soft/brand/accent/highlight/bold/quiet | Adds `wb-icon-tone-{tone}` for non-default visual tones when an active icon renders; unknown tones and missing icons produce no tone class. |
| settings.badge_tone | neutral/info/success/warning/danger | Adds an allowlisted badge tone class when a badge label is present. |
| eyebrow / badge label | translated text | Renders escaped badge text. |
| title | text | Required for output; renders `.wb-link-list-title`. |
| subtitle | text | Renders `.wb-link-list-meta`. |
| content | text | Renders `.wb-link-list-desc`. |

### Use for / Avoid for

Use for: children of Link List.

Avoid for: standalone buttons or nav menu items.

### Notes

The renderer emits only when both a safe URL and title exist. It is intended as a child of `link-list`.

## Navigation Auto (`navigation-auto`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/navigation-auto.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="navigation-auto">
  <nav class="wb-stack wb-gap-2" aria-label="main navigation" data-wb-menu-key="main">
    <ul class="wb-cluster wb-cluster-2 wb-cluster-between">
      <li class="wb-stack wb-gap-1">
        <a href="/" class="wb-btn wb-btn-secondary">Home</a>
      </li>
    </ul>
  </nav>
</div>
```

Footer/legal menu variant:

```html
<ul class="wb-stack wb-gap-1">
  <li class="wb-stack wb-gap-1"><a href="/privacy" class="wb-link">Privacy</a></li>
</ul>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-gap-1`, `wb-cluster`, `wb-cluster-2`, `wb-cluster-between`, `wb-btn`, `wb-btn-secondary`, `wb-link`, `wb-text-sm`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.menu_key or settings.location | known menu key | Selects CMS Navigation menu; helper falls back to primary/footer based on slot/subtitle. |
| footer/legal menu | footer or legal key | Renders stacked `.wb-link` list. |
| other menu | primary/default | Renders clustered button-style root links. |
| active item | current page match | Adds current/active classes where renderer resolves them. |

### Use for / Avoid for

Use for: compatibility rendering of CMS menus.

Avoid for: new shared headers where Navbar Navigation is the intended child.

### Notes

The renderer resolves a CMS Navigation menu by key. Footer and legal menus render stacked links; other menus render clustered button-style root links.

## TOC (`toc`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/toc.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="toc">
  <div class="wb-stack wb-gap-2">
    <strong>Contents</strong>
    <div class="wb-link-list">
      <a class="wb-link-list-item" href="#intro">
        <div class="wb-link-list-main">
          <span class="wb-link-list-title">Intro</span>
        </div>
        <div class="wb-link-list-desc">Jump to section</div>
      </a>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-link-list`, `wb-link-list-item`, `wb-link-list-main`, `wb-link-list-title`, `wb-link-list-meta`, `wb-link-list-desc`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title | text | Renders heading label; default visible label is current renderer behavior. |
| same-page Header blocks | published with anchors | Generates `.wb-link-list-item` links. |
| header variant | h3 | Description says `Jump to subsection`; others say `Jump to section`. |
| no eligible headings | n/a | Renderer emits nothing. |

### Use for / Avoid for

Use for: same-page contents lists from anchored Header blocks.

Avoid for: manual link lists; use Link List.

### Notes

The renderer reads anchored published Header blocks from the same page tree. It emits nothing when no eligible headings exist.

## Alert (`alert`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/alert.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="alert">
  <div class="wb-alert wb-alert-info">
    <h3 class="wb-alert-title">Notice</h3>
    <p>Message.</p>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-alert`, variant class from `alertVariantClass()`, `wb-alert-title`, plus generic `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.variant | success/warning/danger | `alertVariantClass()` adds `wb-alert-success`, `wb-alert-warning`, or `wb-alert-danger`. |
| settings.variant | info/default/other | Adds `wb-alert-info`. |
| title | text | Renders `.wb-alert-title`. |
| content | text | Renders alert body. |

### Use for / Avoid for

Use for: inline user-facing notices.

Avoid for: marketing promos; use CTA/Hero.

### Notes

The block does not own the slot-level root. The shared variant controls the alert tone class.

## Contact Form (`contact_form`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/contact_form.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="contact-form">
  <section class="wb-card wb-public-contact-form-card" id="contact-form-1">
    <div class="wb-card-body wb-stack wb-gap-4">
      <div class="wb-stack wb-gap-2">
        <h2>Contact</h2>
        <p>Intro copy.</p>
      </div>
      <form method="POST" action="/contact-messages" class="wb-stack wb-gap-3">
        <input type="hidden" name="_token" value="csrf-token">
        <input type="hidden" name="block_id" value="1">
        <input type="hidden" name="page_id" value="10">
        <input type="hidden" name="source_url" value="/contact">
        <input type="hidden" name="submitted_at" value="1770000000">
        <input type="hidden" name="_form_check_name" value="signed-generated-field-name">
        <div class="wb-form-check" inert aria-hidden="true">
          <label for="contact-form-check-1">Leave this field empty</label>
          <input id="contact-form-check-1" type="text" name="form_check_generatedtoken" tabindex="-1" autocomplete="off">
        </div>
        <div class="wb-grid wb-grid-2">
          <div class="wb-stack wb-gap-1">
            <label class="wb-label">Name</label>
            <input class="wb-input" required>
          </div>
          <div class="wb-stack wb-gap-1">
            <label class="wb-label">Email</label>
            <input class="wb-input" type="email" required>
          </div>
        </div>
        <textarea class="wb-textarea" rows="7" required></textarea>
        <div class="wb-cluster wb-cluster-between wb-cluster-2">
          <span class="wb-text-sm wb-text-muted">Your message is stored first...</span>
          <button class="wb-btn wb-btn-primary">Send message</button>
        </div>
      </form>
    </div>
  </section>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-public-contact-form-card`, `wb-card-body`, `wb-stack`, `wb-gap-4`, `wb-gap-3`, `wb-gap-2`, `wb-gap-1`, `wb-alert`, `wb-alert-danger`, `wb-alert-title`, `wb-grid`, `wb-grid-2`, `wb-label`, `wb-input`, `wb-textarea`, `wb-cluster`, `wb-cluster-between`, `wb-cluster-2`, `wb-text-sm`, `wb-text-muted`, `wb-btn`, `wb-btn-primary`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| site contact recipient | configured | Form posts to `contact-messages.store` with hidden site/page context. |
| validation errors | present | Renders `wb-alert wb-alert-danger` and field-level feedback. |
| settings submit/success labels | legacy translated settings | Moved out by translation resolver/writer; visible output follows current translated block fields. |

The public form is native Blade output, not Trusted HTML content. Browser submissions require CSRF, include the CMS-owned hidden generated anti-spam check field, validate required fields server-side, store legitimate messages first, and then attempt email notification using the documented recipient fallback order. The check field is generated by the renderer and is not part of normal visitor input.

### Use for / Avoid for

Use for: the product-owned contact message workflow.

Avoid for: raw HTML forms or third-party embeds unless reviewed as Trusted HTML.

### Notes

The renderer includes hidden fields, CSRF, `.wb-form-check` anti-spam markup with `inert` and `aria-hidden="true"`, targeted validation errors, and posts to `contact-messages.store`. The old `website` field is no longer the public renderer contract.

## Breadcrumb (`breadcrumb`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/breadcrumb.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="breadcrumb">
  <nav class="wb-breadcrumb" aria-label="Breadcrumb">
    <ol class="wb-breadcrumb-list">
      <li class="wb-breadcrumb-item">
        <a class="wb-breadcrumb-link" href="/">Home</a>
      </li>
      <li class="wb-breadcrumb-item">
        <span class="wb-breadcrumb-current" aria-current="page">Current page</span>
      </li>
    </ol>
  </nav>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-breadcrumb`, `wb-breadcrumb-list`, `wb-breadcrumb-item`, `wb-breadcrumb-link`, `wb-breadcrumb-current`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.home_label | text | Overrides home crumb label. |
| settings.include_current | false | Omits current page crumb. |
| settings.include_current | true/default | Includes current page crumb. |
| home page | include_current true | Renders only current crumb. |

### Use for / Avoid for

Use for: hierarchical page navigation.

Avoid for: primary site navigation.

### Notes

Home label and current-page inclusion are settings-driven. On the home page it renders only the current crumb when `include_current` is true.

## Sidebar Brand (`sidebar-brand`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-brand.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="sidebar-brand">
  <a href="/" class="wb-sidebar-brand">
    <img src="/media/logo.svg" alt="Docs" class="wb-sidebar-brand-logo">
    <span class="wb-sidebar-brand-copy">
      <span>Docs</span>
      <span class="wb-sidebar-brand-note">Guide</span>
    </span>
  </a>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-sidebar-brand`, `wb-sidebar-brand-logo`, `wb-sidebar-brand-copy`, `wb-sidebar-brand-note`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.url | safe URL | Saved URL wins for the brand link. |
| settings.url | empty | Falls back to site home path, then `/`. |
| settings.target | _blank | Adds `target="_blank" rel="noopener noreferrer"`. |
| settings.aria_label | text | Used as accessible label when no visible text is rendered. |
| media_id | logo media | Renders brand image inside `wb-sidebar-brand`. |

### Use for / Avoid for

Use for: site/docs identity inside sidebar layouts.

Avoid for: navbar identity; use Navbar Brand there.

### Notes

The block does not own the sidebar shell. Saved URL wins, then site home path, then `/`. Logo-only output uses `aria-label`.

## Sidebar Navigation (`sidebar-navigation`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-navigation.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="sidebar-navigation">
  <nav class="wb-sidebar-nav" aria-label="Documentation navigation">
    <div class="wb-sidebar-section">
      <a href="/docs" class="wb-sidebar-link is-active" aria-current="page">
        <i class="wb-icon wb-icon-book wb-sidebar-icon" aria-hidden="true"></i>
        <span>Docs</span>
      </a>
    </div>
  </nav>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-sidebar-nav`, `wb-sidebar-section`, `wb-sidebar-link`, `wb-nav-group`, `wb-nav-group-toggle`, `wb-nav-group-items`, `wb-nav-group-item`, `wb-icon`, `wb-sidebar-icon`, `is-active`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.menu_key | known menu key | Renders CMS Navigation tree when present. |
| manual children | sidebar-nav-item/sidebar-nav-group | Renders manual child blocks when menu mode does not supply items. |
| settings.show_icons | boolean | Icon visibility effect needs source confirmation. |
| settings.active_matching | value | Active matching behavior is helper-driven; visible output is `is-active`/`aria-current` when matched. |

### Use for / Avoid for

Use for: docs/sidebar navigation blocks.

Avoid for: ordinary marketing sidebars without nav semantics.

### Notes

The block can render a CMS Navigation menu or manual child `sidebar-nav-item` / `sidebar-nav-group` blocks. It does not own the outer page/sidebar shell.

## Sidebar Nav Item (`sidebar-nav-item`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-nav-item.blade.php`

### Rendered HTML

```html
<a href="/docs/page" class="wb-sidebar-link is-active" aria-current="page">
  <i class="wb-icon wb-icon-file wb-sidebar-icon" aria-hidden="true"></i>
  <span>Page</span>
</a>
```

Nested group item:

```html
<a href="/docs/child" class="wb-nav-group-item">
  <span>Child</span>
</a>
```

### Main CSS / WebBlocks UI classes

`wb-sidebar-link`, `wb-nav-group-item`, `wb-icon`, `wb-sidebar-icon`, `is-active`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.url | safe URL | Required for output. |
| settings.target | _blank | Adds external target/rel attributes. |
| settings.icon | icon slug/class | Renders `wb-icon ... wb-sidebar-icon` when configured. |
| settings.active_mode | exact/current-page/manual/default | Controls active-state helper; visible output is `is-active` and `aria-current`. |
| settings.manual_active | true | Can force active output when active mode is manual. |

### Use for / Avoid for

Use for: manual sidebar links.

Avoid for: navbar links or generic buttons.

### Notes

The renderer delegates to `sidebar-nav-item-link.blade.php`. It emits nothing without both a URL and label.

## Sidebar Nav Group (`sidebar-nav-group`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-nav-group.blade.php`

### Rendered HTML

```html
<div class="wb-nav-group is-open" data-wb-nav-group>
  <button type="button" class="wb-nav-group-toggle is-active" data-wb-nav-group-toggle>
    <span class="wb-nav-group-icon"><i class="wb-icon wb-icon-folder" aria-hidden="true"></i></span>
    <span class="wb-nav-group-label">Group</span>
    <span class="wb-nav-group-arrow" aria-hidden="true"></span>
  </button>
  <div class="wb-nav-group-items" id="wb-nav-group-items-1">
    <a href="/docs/child" class="wb-nav-group-item">...</a>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-nav-group`, `is-open`, `wb-nav-group-toggle`, `is-active`, `wb-nav-group-icon`, `wb-icon`, `wb-nav-group-label`, `wb-nav-group-arrow`, `wb-nav-group-items`, `wb-nav-group-item`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.icon | icon slug/class | Renders group icon markup when configured. |
| settings.initially_open | true/default | Adds `is-open`. |
| settings.initially_open | false | Group starts closed. |
| child sidebar-nav-item | published children | Rendered inside `.wb-nav-group-items`. |

### Use for / Avoid for

Use for: collapsible groups inside Sidebar Navigation.

Avoid for: general accordions; use Accordion.

### Notes

The group renders only when it has a label and published `sidebar-nav-item` children. It uses WebBlocks UI nav-group hooks.

## Sidebar Footer (`sidebar-footer`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-footer.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="sidebar-footer">
  <div class="wb-sidebar-footer">
    <div class="wb-callout wb-callout-info">
      <div class="wb-callout-title">Title</div>
      <p>Copy.</p>
    </div>
    <p class="wb-text-xs wb-text-muted wb-mt-3 wb-mb-0">Footer note.</p>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-sidebar-footer`, `wb-callout`, variant class from `sidebarFooterVariantClass()`, `wb-callout-title`, `wb-text-xs`, `wb-text-muted`, `wb-mt-3`, `wb-mb-0`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.variant | success/warning/danger | `sidebarFooterVariantClass()` adds `wb-callout-success`, `wb-callout-warning`, or `wb-callout-danger`. |
| settings.variant | info/default/other | Adds `wb-callout-info`. |
| title/content/subtitle | all empty | Renderer emits nothing. |
| subtitle | text | Renders footer note with `wb-text-xs wb-text-muted wb-mt-3 wb-mb-0`. |

### Use for / Avoid for

Use for: small sidebar notes or calls to action.

Avoid for: main page CTAs.

### Notes

The block renders only when title/content/subtitle exists. It does not own the sidebar shell.

## HTML (Trusted) (`html`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/html.blade.php`

### Rendered HTML

```html
<div class="wb-public-block" data-wb-public-block-type="html">
  <div>
    <!-- trusted HTML content -->
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

No fixed WebBlocks UI class is added by the renderer itself. The generic wrapper uses `wb-public-block`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| content | trusted HTML | Rendered inside a plain `<div>` within the generic public wrapper. |
| overlay/body-end fragments | recognized by extractor | Can be hoisted through `TrustedHtmlOverlayExtractor`, `PublicOverlayRegistry`, and `PublicBodyEndRegistry`. |
| untrusted content | n/a | Do not use; this renderer assumes trusted static markup. |

### Use for / Avoid for

Use for: reviewed fallback markup that structured blocks cannot yet represent.

Avoid for: default migrated/AI-created page output.

### Notes

Trusted HTML can extract overlay and body-end fragments through `TrustedHtmlOverlayExtractor`, `PublicOverlayRegistry`, and `PublicBodyEndRegistry`. Use this only for trusted static markup.

## Button (`button`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/button.blade.php`

### Rendered HTML

```html
<a href="/start" class="wb-btn wb-btn-primary">Open link</a>
```

No URL variant:

```html
<button type="button" class="wb-btn wb-btn-primary">Open link</button>
```

### Main CSS / WebBlocks UI classes

`wb-btn`, `wb-btn-primary`, `wb-btn-secondary`, `wb-btn-outline`, `wb-btn-ghost`, `wb-btn-danger`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | primary/secondary/outline/ghost/danger | Maps to `wb-btn` plus corresponding variant class. |
| variant | empty/other | Defaults to `wb-btn wb-btn-primary`. |
| attachment media URL | present | Wins over canonical URL for compatibility. |
| url | present without attachment | Renders anchor. |
| url | empty | Renders inert `<button type="button">`. |

### Use for / Avoid for

Use for: managed child actions in Hero/CTA.

Avoid for: standalone page buttons; use Button Link.

### Notes

This is a managed child action block for Hero/CTA. It is not in the current published catalog list from `CoreBlockTypeCatalogSyncer`, but public renderers and conversion support exist. Attachment URL wins over canonical URL for compatibility.

## Callout (`callout`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/callout.blade.php`

### Rendered HTML

```html
<div class="wb-alert wb-alert-info">
  <div>
    <div class="wb-alert-title">Title</div>
    <div>Content</div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-alert`, `wb-alert-info`, `wb-alert-success`, `wb-alert-warning`, `wb-alert-danger`, `wb-alert-title`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | success/warning/danger | Adds matching `wb-alert-{tone}` class. |
| variant | info/default/other | Adds `wb-alert-info`. |
| children | published children | Rendered afterward in `wb-stack wb-gap-4`. |
| preferred new pages | n/a | Use Alert for first-class new-page notices. |

### Use for / Avoid for

Use for: legacy/converter-compatible alert-like content.

Avoid for: new structured pages; use Alert.

### Notes

`callout` is a legacy/conversion-compatible renderer and maps visually to the alert pattern. It is not listed as a current published core catalog row, while `alert` is.

## List (`list`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/list.blade.php`

### Rendered HTML

```html
<div class="wb-stack wb-gap-2">
  <h3>List title</h3>
  <ul class="wb-stack wb-gap-1">
    <li>Item</li>
  </ul>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, `wb-gap-1`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| variant | ordered | Renders `<ol>`. |
| variant | default/other | Renders `<ul>`. |
| settings.items/cards/entries | array | Used as item source when available. |
| content | line-separated text | Used as fallback item source. |
| title | text | Renders heading above list. |

### Use for / Avoid for

Use for: simple imported or manually entered lists.

Avoid for: navigation lists or rich body copy that belongs in Rich Text.

### Notes

This renderer exists for compatibility and conversion. Items come from settings (`items`, `cards`, `entries`) or line-separated content. `variant = ordered` switches `ul` to `ol`.

## Text (`text`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/text.blade.php`

### Rendered HTML

```html
<div class="wb-stack wb-gap-2">
  <strong>Title</strong>
  <p>Content</p>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-2`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title | text | Renders `<strong>` above the paragraph when present. |
| content | text | Renders a paragraph inside `wb-stack wb-gap-2`. |
| children | published children | Renders child blocks afterward inside `wb-stack wb-gap-4`. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a compatibility renderer. The current canonical plain body-copy core block is `plain_text`.

## Internal Actions Partial (`_actions`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/_actions.blade.php`

### Rendered HTML

```html
<div class="wb-cluster wb-cluster-2">
  <a href="/start" class="wb-btn wb-btn-primary">Start</a>
</div>
```

### Main CSS / WebBlocks UI classes

Default wrapper `wb-cluster wb-cluster-2`, or a caller-provided wrapper class.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| buttons input | child blocks with type `button` | Filters to button children only and renders them. |
| wrapperClass input | provided class string | Uses the provided wrapper class. |
| wrapperClass input | empty | Defaults to `wb-cluster wb-cluster-2`. |

### Use for / Avoid for

Use for: renderer-owned Hero/CTA action output.

Avoid for: direct page composition.

### Notes

This is an internal helper partial, not a standalone published block. It filters the provided collection to `button` children and renders each child through the normal block partial.

## Card Grid (`card-grid`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/card-grid.blade.php`

### Rendered HTML

```html
<section class="wb-stack wb-gap-4">
  <div class="wb-stack wb-gap-1">
    <h2>Cards</h2>
    <p class="wb-text-muted">Intro</p>
  </div>
  <div class="wb-grid wb-grid-3">
    <div class="wb-card">
      <div class="wb-card-body wb-stack wb-gap-2">
        <img src="/media/card.jpg" alt="Card image">
        <strong>Card title</strong>
        <p class="wb-m-0">Card content.</p>
        <a href="/target" class="wb-link">Read more</a>
      </div>
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-4`, `wb-gap-1`, `wb-text-muted`, `wb-grid`, `wb-grid-2`, `wb-grid-3`, `wb-grid-4`, `wb-card`, `wb-card-body`, `wb-gap-2`, `wb-m-0`, `wb-link`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.items | array | Drives card output; each item may include `asset_id`, `title`, `content`, `url`, and `url_label`. |
| settings.items count | 0-1/2/3/4+ | Uses `wb-stack wb-gap-3`, `wb-grid wb-grid-2`, `wb-grid wb-grid-3`, or `wb-grid wb-grid-4`. |
| item.asset_id | media id | Renders item image when media URL exists. |
| item.url + item.url_label | text | Renders `a.wb-link`. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a legacy/compatibility renderer backed by `settings.items`. The grid class changes by item count.

## Contact Info (`contact-info`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/contact-info.blade.php`

### Rendered HTML

```html
<section class="wb-card wb-card-muted wb-public-contact-card">
  <div class="wb-card-body wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <h2>Contact</h2>
      <p>Reach us.</p>
    </div>
    <div class="wb-stack wb-gap-1 wb-public-contact-meta">
      <strong>Email</strong>
      <a href="mailto:hello@example.test" class="wb-link">hello@example.test</a>
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-3`, `wb-gap-1`, `wb-link`, plus CMS-specific `wb-public-contact-card` and `wb-public-contact-meta`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.items | array | Renders contact rows inside `wb-public-contact-meta`. |
| item.url | safe URL | Renders linked value with `wb-link`. |
| item.target | _blank | Adds external target/rel attributes. |
| item.label/value | text | Renders label and plain value when no safe URL exists. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a legacy/compatibility renderer backed by `settings.items`. Links are passed through `Block::safePublicUrl()`.

## FAQ List (`faq-list`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/faq-list.blade.php`

### Rendered HTML

```html
<div class="wb-stack wb-gap-3">
  <details class="wb-card">
    <summary class="wb-card-header"><strong>Question?</strong></summary>
    <div class="wb-card-body">Answer.</div>
  </details>
</div>
```

### Main CSS / WebBlocks UI classes

Uses the `accordion` renderer classes.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated | Includes the Accordion renderer directly. |
| child rows | title and content | Follow the Accordion disclosure item contract. |
| preferred new pages | n/a | Use `accordion` as the canonical parent block. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This compatibility partial delegates directly to `accordion.blade.php`.

## Gallery Viewer (`gallery-viewer`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/gallery-viewer.blade.php`

### Rendered HTML

```html
<div class="wb-modal wb-modal-xl" id="wb-gallery-viewer-1" role="dialog" aria-modal="true">
  <div class="wb-modal-dialog">
    <div class="wb-modal-body">
      <div class="wb-gallery-viewer">
        <h2 class="wb-gallery-viewer-title wb-m-0" id="wb-gallery-viewer-1-title">Gallery set</h2>
        <div class="wb-gallery-viewer-toolbar">
          <button class="wb-btn wb-btn-secondary wb-btn-icon wb-gallery-viewer-prev" type="button"></button>
          <div class="wb-gallery-viewer-counter" aria-live="polite">1 / 3</div>
        </div>
        <figure class="wb-gallery-viewer-media">
          <img class="wb-gallery-viewer-image" src="/media/full.jpg" alt="Image">
          <figcaption class="wb-gallery-viewer-caption">Caption</figcaption>
        </figure>
      </div>
    </div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-modal`, `wb-modal-xl`, `wb-modal-dialog`, `wb-modal-body`, `wb-gallery-viewer`, `wb-gallery-viewer-title`, `wb-gallery-viewer-toolbar`, `wb-btn`, `wb-btn-secondary`, `wb-btn-icon`, `wb-icon`, `wb-gallery-viewer-counter`, `wb-gallery-viewer-media`, `wb-gallery-viewer-image`, `wb-gallery-viewer-caption`, `wb-gallery-viewer-meta`, `wb-text-sm`, `wb-text-muted`, `wb-m-0`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| viewerId input | id string | Sets the modal/gallery target id used by gallery triggers. |
| viewerTitle input | text/null | Adds the optional viewer header title and uses it as the modal accessible label. |
| galleryItems input | collection | Renders viewer slides/items from Gallery-prepared item data. |
| direct block settings | n/a | Internal partial; no standalone block settings map. |

### Use for / Avoid for

Use for: Gallery lightbox overlay markup registered by Gallery.

Avoid for: manual use as a page block.

### Notes

This is an overlay helper used by gallery/showcase renderers through `PublicOverlayRegistry`, not a standalone block.

## Map (`map`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/map.blade.php`

### Rendered HTML

```html
<div class="wb-card wb-card-muted">
  <div class="wb-card-body wb-stack wb-gap-2">
    <strong>Location</strong>
    <p class="wb-m-0">Address text</p>
    <a href="https://maps.google.com/?q=Address" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Open map</a>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-2`, `wb-m-0`, `wb-btn`, `wb-btn-secondary`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| url | http/https URL | Accepted as a safe map source and displayed when distinct from content. |
| content | text or query | Used as map query fallback. |
| title | text | Renders `<strong>` in the card. |
| children | published children | Renders child blocks afterward inside `wb-stack wb-gap-4`. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a legacy/compatibility renderer. It accepts only `http` and `https` URL schemes for the raw URL and can render children after the card.

## Menu (`menu`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/menu.blade.php`

### Rendered HTML

```html
<nav class="wb-stack wb-gap-2" aria-label="Menu">
  <ul class="wb-cluster wb-cluster-2 wb-cluster-between">
    <li><a href="/page" class="wb-btn wb-btn-secondary">Page</a></li>
  </ul>
</nav>
```

### Main CSS / WebBlocks UI classes

Uses the `navigation-auto` renderer classes.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated | Includes the Navigation Auto renderer directly. |
| settings.menu_key/location | known menu key | Follows Navigation Auto menu selection behavior. |
| preferred new pages | n/a | Use Navbar Navigation or Navigation Auto intentionally, not `menu`. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This compatibility partial delegates directly to `navigation-auto.blade.php`.

## Metric Card (`metric-card`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/metric-card.blade.php`

### Rendered HTML

```html
<div class="wb-stat">
  <div class="wb-stat-label">Label</div>
  <div class="wb-stat-value">42</div>
  <div class="wb-stat-delta">+8%</div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-stat`, `wb-stat-label`, `wb-stat-value`, `wb-stat-delta`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated alias | Delegates to an existing renderer path; exact setting map follows the delegate where visible. |
| preferred new pages | n/a | Prefer the canonical block named in Notes instead of this alias/compatibility block. |

### Use for / Avoid for

Use for: legacy metric-card alias content.

Avoid for: new content; use Stat Card or Columns stats.

### Notes

This is a legacy/compatibility metric renderer. The current published catalog uses `stat-card`.

## Showcase List (`showcase-list`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/showcase-list.blade.php`

### Rendered HTML

```html
<section class="wb-stack wb-gap-6">
  <div class="wb-stack wb-gap-1">
    <h2>Projects</h2>
    <p>Selected work.</p>
  </div>
  <article class="wb-card wb-card-muted wb-public-showcase-item">
    <div class="wb-card-body wb-stack wb-gap-4">
      <div class="wb-stack wb-gap-1">
        <h3>Project</h3>
        <p>Summary.</p>
      </div>
      <section class="wb-gallery" aria-label="Project screenshots">
        <div class="wb-gallery-grid">
          <figure class="wb-gallery-item">
            <a href="/media/full.jpg" class="wb-gallery-trigger" data-wb-gallery-target="#wb-gallery-viewer-1">
              <img src="/media/full.jpg" alt="Project image" class="wb-gallery-media">
            </a>
            <figcaption class="wb-gallery-caption">Screenshot</figcaption>
          </figure>
        </div>
      </section>
      <a href="/project" class="wb-link">View project</a>
    </div>
  </article>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-stack`, `wb-gap-6`, `wb-gap-1`, `wb-card`, `wb-card-muted`, `wb-card-body`, `wb-gap-4`, `wb-gallery`, `wb-gallery-grid`, `wb-gallery-item`, `wb-gallery-trigger`, `wb-gallery-media`, `wb-gallery-caption`, `wb-link`, plus CMS-specific `wb-public-showcase-item`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.items | array | Renders one muted card showcase item per entry. |
| item.images[].asset_id | media id | Renders gallery figures and registers a shared gallery viewer when images exist. |
| item.url | safe URL | Renders external `wb-link` with target/rel. |
| item.title/subtitle/url_label | text | Renders showcase headings and link label. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a legacy/compatibility renderer backed by `settings.items`. It can push `gallery-viewer` overlay markup into `PublicOverlayRegistry`.

## Sidebar Nav Item Link (`sidebar-nav-item-link`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-nav-item-link.blade.php`

### Rendered HTML

```html
<a href="/docs" class="wb-sidebar-link is-active" aria-current="page">
  <i class="wb-icon wb-icon-book-open wb-sidebar-icon" aria-hidden="true"></i>
  <span>Docs</span>
</a>
```

### Main CSS / WebBlocks UI classes

`wb-sidebar-link`, `wb-nav-group-item`, `is-active`, `wb-icon`, `wb-sidebar-icon`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| nested input | true | Uses `wb-nav-group-item`. |
| nested input | false | Uses `wb-sidebar-link`. |
| sidebar item URL/label | present | Required for anchor output. |
| item icon | configured | Renders `i.wb-icon.wb-icon-{icon}.wb-sidebar-icon`. |
| active helper | true | Adds `is-active` and `aria-current="page"`. |

### Use for / Avoid for

Use for: internal sidebar link output shared by sidebar item renderers.

Avoid for: direct page composition.

### Notes

This is an internal helper for `sidebar-nav-item` and `sidebar-nav-group`. The class switches to `wb-nav-group-item` when rendered nested.

## Sidebar Navigation Menu Item (`sidebar-navigation-menu-item`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/sidebar-navigation-menu-item.blade.php`

### Rendered HTML

```html
<div class="wb-nav-group is-open" data-wb-nav-group>
  <button type="button" class="wb-nav-group-toggle is-active" aria-expanded="true" data-wb-nav-group-toggle>
    <span class="wb-nav-group-icon"><i class="wb-icon wb-icon-folder" aria-hidden="true"></i></span>
    <span class="wb-nav-group-label">Group</span>
    <span class="wb-nav-group-arrow" aria-hidden="true"></span>
  </button>
  <div class="wb-nav-group-items">
    <a href="/child" class="wb-nav-group-item">Child</a>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-nav-group`, `is-open`, `wb-nav-group-toggle`, `is-active`, `wb-nav-group-icon`, `wb-icon`, `wb-nav-group-label`, `wb-nav-group-arrow`, `wb-nav-group-items`, `wb-nav-group-item`, `wb-sidebar-link`, `wb-sidebar-icon`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| NavigationItem link_type | group with visible children | Renders `.wb-nav-group` with toggle and nested items. |
| nested input | true | Child links use `wb-nav-group-item`. |
| showIcons input | true | Allows item sidebar icons to render. |
| active helper | true | Adds `is-open`/`is-active` and `aria-current` where applicable. |

### Use for / Avoid for

Use for: internal CMS menu item rendering for sidebar navigation.

Avoid for: direct page composition.

### Notes

This helper renders CMS `NavigationItem` rows for `sidebar-navigation` when that block is menu-backed rather than manual-child-backed.

## Slider (`slider`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/slider.blade.php`

### Rendered HTML

```html
<section class="wb-slider wb-slider-height-viewport wb-slider-overlay-strong wb-slider-content-center wb-slider-content-lg wb-slider-text-light" data-wb-slider>
  <div class="wb-slider-viewport">
    <div class="wb-slider-track">
      <article class="wb-slide wb-slider-content-center wb-slider-content-lg wb-slider-text-light">
        <img class="wb-slide-media" src="/media/slide.jpg" alt="" style="object-position: center;">
        <div class="wb-slide-content">
          <!-- nested Header, Text, Card, Button, or layout blocks render here -->
        </div>
      </article>
    </div>
  </div>
  <div class="wb-slider-controls">
    <button type="button" class="wb-btn wb-btn-icon wb-slider-arrow wb-slider-prev" data-wb-slider-prev aria-label="Previous slide">
      <i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i>
    </button>
    <div class="wb-slider-dots" data-wb-slider-dots></div>
    <button type="button" class="wb-btn wb-btn-icon wb-slider-arrow wb-slider-next" data-wb-slider-next aria-label="Next slide">
      <i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i>
    </button>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

`wb-slider`, `wb-slider-viewport`, `wb-slider-track`, `wb-slide`, `wb-slide-media`, `wb-slide-content`, `wb-slider-controls`, `wb-slider-arrow`, `wb-slider-prev`, `wb-slider-next`, `wb-btn`, `wb-btn-icon`, `wb-slider-dots`, `wb-slider-dot`, `is-active`, plus generated height, ratio, overlay, content-position, content-width, text-color, and media-fit classes.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| `slider.children` | `slide` blocks | Renders the direct Slide children in the slider track. |
| `settings.height` | `fill`, `viewport`, `large`, `medium`, `small`, `auto`, `custom` | Emits native `wb-slider-height-*` classes and optional `--wb-slider-min-height`. |
| `settings.transition` | `slide`, `fade` | Stored for compatibility; the native WebBlocks UI slider uses track movement. |
| `settings.autoplay`, `interval_ms`, `pause_on_hover`, `loop`, `swipe`, `keyboard` | booleans / milliseconds | Emits data attributes consumed by the pinned WebBlocks UI runtime. |
| `settings.show_arrows`, `settings.show_dots` | booleans | Controls whether arrows and dots render when more than one slide exists. |
| `settings.overlay`, `content_position`, `content_width`, `text_color`, `background_fit` | enum values | Emits native `wb-slider-*` or `wb-slide-media-*` presentation classes. |
| `slide.media_id` | image media | Emits an `img.wb-slide-media` element inside the Slide root. |
| `slide.children` | nested blocks | Renders visible slide content inside `.wb-slide-content`. |

### Use for / Avoid for

Use for: hero sliders, split slider/text sections, carousel panels inside cards, and any page area where the slider should fill its placed container.

Avoid for: unrelated galleries where the Gallery block is a better semantic fit.

### Notes

Slider is a published parent/container contract. It accepts only `slide` children; each Slide is also a parent/container and may own background media plus nested content blocks. Slider controls render only when more than one slide exists and the corresponding settings are enabled.

## Slide (`slide`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/slide.blade.php`

### Notes

Slide renders as `<article class="wb-slide">` and is intended to be rendered inside Slider. It owns optional slide media and delegates visible content to nested blocks.

## Stats (`stats`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/stats.blade.php`

### Rendered HTML

```html
<section class="wb-stack wb-gap-4">
  <div class="wb-grid wb-grid-3">
    <div class="wb-stat">
      <div class="wb-stat-label">Metric</div>
      <div class="wb-stat-value">42</div>
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

Uses the `columns` renderer with `variant = stats`, including `wb-stat`, `wb-stat-label`, `wb-stat-value`, and `wb-stat-delta` for child `column_item` blocks.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated alias | Delegates to an existing renderer path; exact setting map follows the delegate where visible. |
| preferred new pages | n/a | Prefer the canonical block named in Notes instead of this alias/compatibility block. |

### Use for / Avoid for

Use for: legacy stats alias content.

Avoid for: new stats groups; use Columns with `stats` variant or Stat Card.

### Notes

This compatibility partial delegates to `columns.blade.php` after forcing the variant to `stats`.

## Tabs (`tabs`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/tabs.blade.php`

### Rendered HTML

```html
<div class="wb-card">
  <div class="wb-card-header">
    <strong>Tab</strong>
    <span>Subtitle</span>
  </div>
  <div class="wb-card-body">
    <div>Content</div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-card`, `wb-card-header`, `wb-card-body`, optional child wrapper `wb-stack wb-gap-4`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| title | text/default | Renders card header title, defaulting to `Tab`. |
| subtitle | text | Renders secondary header span. |
| content | text/HTML text | Renders inside `.wb-card-body`. |
| children | published children | Renders child blocks afterward inside `wb-stack wb-gap-4`. |

### Use for / Avoid for

Use for: legacy or converter-compatible content that already depends on this renderer.

Avoid for: new hand-built pages; use the canonical structured blocks listed near the top.

### Notes

This is a legacy/compatibility renderer. It renders children after the card if child blocks exist.

## Testimonial (`testimonial`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/testimonial.blade.php`

### Rendered HTML

```html
<blockquote class="wb-card wb-card-muted">
  <div class="wb-card-body wb-stack wb-gap-2">
    <p class="wb-m-0">Quote text.</p>
    <footer class="wb-text-sm wb-text-muted">Name</footer>
  </div>
</blockquote>
```

### Main CSS / WebBlocks UI classes

Uses the `quote` renderer with `variant = testimonial`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| renderer behavior | delegated alias | Delegates to an existing renderer path; exact setting map follows the delegate where visible. |
| preferred new pages | n/a | Prefer the canonical block named in Notes instead of this alias/compatibility block. |

### Use for / Avoid for

Use for: legacy testimonial alias content.

Avoid for: new quotes; use Quote with testimonial variant if needed.

### Notes

This compatibility partial delegates to `quote.blade.php` after forcing the variant to `testimonial`.

## Fallback Renderer (`fallback`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/fallback.blade.php`

### Rendered HTML

```html
<section class="wb-card wb-card-muted">
  <div class="wb-card-body wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
      <h3>Block title</h3>
      <p>Block content.</p>
    </div>
    <div class="wb-grid wb-grid-2">
      <!-- fallback child/content output -->
    </div>
  </div>
</section>
```

### Main CSS / WebBlocks UI classes

Fallback branches use existing primitives including `wb-card`, `wb-card-muted`, `wb-card-body`, `wb-stack`, `wb-gap-1`, `wb-gap-2`, `wb-gap-3`, `wb-grid`, `wb-grid-2`, `wb-grid-3`, `wb-table-wrap`, `wb-table`, `wb-table-striped`, `wb-slider`, `wb-btn`, `wb-link`, `wb-input`, `wb-textarea`, and `wb-select`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| settings.items/cards/entries | arrays | Can drive compatibility list/card-style output depending on fallback branch. |
| settings.rows/options/related_slugs | arrays | Used by fallback branches when present. |
| block type/source context | varies | needs source confirmation for exact branch selection. |

### Use for / Avoid for

Use for: safe degraded rendering for unsupported production blocks.

Avoid for: planned page composition or normal migrations.

### Notes

This is the safety renderer for unknown or transitional slugs. It contains branch-specific output for older slugs such as `comparison`, `timeline`, `product-card`, `pagination`, `input`, `textarea`, `select`, `checkbox-group`, and `radio-group`. Prefer documenting dedicated renderer files where they exist.

## Missing Renderer (`missing-renderer`)

### Renderer source

`packages/webblocks-cms/resources/views/pages/partials/blocks/missing-renderer.blade.php`

### Rendered HTML

```html
<div class="wb-alert wb-alert-warning">
  <div>
    <div class="wb-alert-title">Missing Block Renderer</div>
    <div>Expected renderer for <code>unknown</code> at <code>...</code>.</div>
  </div>
</div>
```

### Main CSS / WebBlocks UI classes

`wb-alert`, `wb-alert-warning`, `wb-alert-title`.

### Settings -> class / markup map

| Setting | Value | Output effect |
| --- | --- | --- |
| type slug | any missing renderer slug | Shows warning alert naming expected renderer file. |
| environment | non-production fallback path | Used when no public renderer exists outside production. |
| runtime settings | n/a | No block settings affect this warning output. |

### Use for / Avoid for

Use for: developer visibility when no renderer exists outside production.

Avoid for: public content design.

### Notes

This diagnostic renderer is used when a concrete block renderer cannot be resolved.
