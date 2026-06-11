# WebBlocks CMS Page Converter Roadmap

## Purpose

Page Converter is a proposed reusable WebBlocks CMS admin feature that converts pasted or uploaded static HTML into structured CMS pages made of first-class blocks.

The feature should help migrate static sites, hand-written HTML pages, documentation pages, marketing pages, and legacy content into WebBlocks CMS without collapsing the entire page body into one Safe HTML block.

The goal is not to create a site-specific importer for `webblocksui.com`. The converter should be a generic CMS capability that can be used for any site managed by a WebBlocks CMS install.

## Current Implementation Status

The first runtime foundation is in place: `Admin -> Pages -> Page Converter` renders the scoped target/source form and validates pasted or uploaded `.html` / `.htm` input. The analyzer normalizes submitted HTML, extracts the most likely content area, and shows ordered structured-block suggestions with confidence scores and warnings. The review screen serializes those suggestions into a signed conversion plan payload, then `Create draft page` can create one new draft page with supported main-slot blocks. Draft creation supports `header`, `plain_text`, `rich-text`, `code`, `table`, `quote`, explicit `html` fallback, `button_link`, `list` as Rich Text, `callout` as Alert, `section`, `content_header`, `hero`, `cta`, and explicit `card` shells with signed `card_header` / `card_body` / `card_footer` children. Adjacent `<details>` groups now become one signed `accordion` suggestion with explicit `accordion_item` children, and draft creation writes those items to the existing `faq` child-row contract when both `accordion` and `faq` block types are published. Card suggestions without explicit usable region children and accordion suggestions without a usable item contract are skipped rather than flattened into unsafe HTML. Media-backed suggestions such as `image` and `gallery` are still skipped and reported without importing media.

A compact WebBlocks UI-flavored fixture pilot now covers a realistic static docs/marketing fragment with `<main>`, content header/body wrappers, promo, card grid, buttons, code, table, adjacent details, and remote image media. The fixture guards that analysis produces many structured suggestions instead of one Safe HTML fallback, creates a signed plan and draft page, preserves explicit card regions and footer button links where the current block contract can represent them, and keeps media-backed fragments warning-only/skipped without importing files, publishing pages, creating navigation, touching shared slots, fetching remote URLs, or overwriting content.

## Core Principle

The converter must prefer structured CMS blocks over a single Safe HTML block.

Bad output:

```text
Page
└── Main Slot
    └── Safe HTML Block
        └── entire page main HTML
```

Preferred output:

```text
Page
└── Main Slot
    ├── Content Header
    ├── Hero
    │   └── Button
    ├── Section
    │   └── Columns
    │       ├── Column Item
    │       ├── Column Item
    │       └── Column Item
    ├── Rich Text
    ├── Code
    ├── Table
    ├── Card
    │   ├── Card Header
    │   ├── Card Body
    │   └── Card Footer
    └── Accordion
```

Safe HTML is allowed only as a visible, reviewable fallback for fragments that cannot yet be represented by structured CMS blocks.

## Product Positioning

Recommended admin location:

```text
Admin -> Pages -> Page Converter
```

The tool belongs near Pages because its output is a CMS page draft. It is an editorial/content tool, not primarily an operational maintenance tool.

Possible secondary entry point:

```text
Admin -> Pages -> Import Page -> Convert HTML
```

However, the main Page Converter flow should have its own screen because it includes source input, analysis, review, warnings, and final draft creation.

## Target Users

- `super_admin`: can use Page Converter for all sites.
- `site_admin`: can use Page Converter for assigned sites.
- `editor`: possible later; for MVP, keep conservative and decide based on existing page creation permissions.

Generated pages must always start as `draft`.

The converter must never publish automatically.

## MVP Scope

### Included In MVP

- Admin screen under the Pages area.
- Target Site selector limited by user access.
- Target Locale selector.
- Target Page Layout selector.
- Page title field.
- Slug/path field.
- Source HTML textarea.
- Source HTML file upload for `.html` and `.htm`.
- Analyze action that creates an in-memory conversion plan.
- Review screen showing suggested blocks, confidence, warnings, and fallbacks.
- Final `Create Draft Page` action.
- Path conflict validation.
- Draft-only page creation.
- Structured block creation for high-confidence mappings.
- Explicit Safe HTML fallback reporting.
- Focused tests.
- Documentation and changelog updates.

### Excluded From MVP

- Remote URL fetching.
- Website crawling.
- Multi-page ZIP import.
- Batch conversion.
- Screenshot comparison.
- AI API integration.
- Automatic publish.
- Overwriting or replacing an existing page.
- Media file download/import from remote URLs.
- Destructive cleanup of old pages or Safe HTML blocks.

## Source Input Modes

### MVP Input Modes

| Input Mode | Status | Notes |
| --- | --- | --- |
| Paste HTML | MVP | Safest starting point. |
| Upload `.html` / `.htm` file | MVP | Useful for static site exports. |
| Fetch remote URL | Later | Requires SSRF protection and network policy. |
| Upload ZIP of pages | Later | Requires batch review and conflict handling. |
| Crawl site | Later | Requires scope controls, rate limits, and URL allowlists. |

## Admin Flow

### Step 1: Source And Target

The admin chooses:

- Target Site
- Target Locale
- Page Layout
- Page title
- Page slug/path
- Conversion profile
- Source HTML paste or uploaded file

### Step 2: Analyze

The converter analyzes the HTML and produces a conversion plan without writing page content to the database.

Example analyze summary:

```text
Detected page title: Admin Standards
Suggested path: /patterns/admin-standards
Suggested layout: docs
Detected blocks:
- 1 Content Header
- 8 Header blocks
- 12 Rich Text blocks
- 4 Card blocks
- 2 Table blocks
- 3 Code blocks
- 0 Safe HTML fallbacks
Warnings:
- Theme switcher controls ignored.
- Sidebar navigation detected; review whether it belongs in a Shared Slot.
```

### Step 3: Review

The review screen shows each suggested block:

| Order | Source Fragment | Suggested Block | Confidence | Warning |
| ---: | --- | --- | ---: | --- |
| 1 | `<header class="wb-content-header">` | Content Header | 96% |  |
| 2 | `<section class="wb-section">` | Section | 92% |  |
| 3 | `<article class="wb-card">` | Card | 94% |  |
| 4 | `<pre><code>` | Code | 99% |  |
| 5 | Unknown widget HTML | HTML fallback | 41% | Manual review required |

The review screen should clearly separate:

- high-confidence structured blocks
- medium-confidence suggestions
- ignored fragments
- fallback Safe HTML fragments
- unsafe stripped content
- media warnings

### Step 4: Create Draft Page

Only after explicit confirmation, the tool creates a new draft page.

The operation should be transactional and create:

- page record
- page translation
- required page slots
- block tree
- block translations
- block settings and relationships
- revision metadata where existing revision services support it

The tool must not publish the page.

## Conversion Profiles

Profiles allow different mapping strictness without making the converter site-specific.

### Generic Marketing Page

Best for landing pages and product pages.

Prioritizes:

- Hero
- Section
- Columns
- Column Item
- Card
- Button
- CTA
- Image
- Gallery
- Quote

### Generic Docs Page

Best for documentation and long-form content.

Prioritizes:

- Content Header
- Header
- Rich Text
- Code
- Table
- List
- Callout
- Accordion
- TOC

### WebBlocks UI-Flavored HTML

Best for pages that already use WebBlocks UI class names.

Prioritizes class-aware mapping for:

- `wb-section`
- `wb-content-header`
- `wb-promo`
- `wb-card`
- `wb-grid`
- `wb-btn`
- `wb-alert`
- `wb-gallery`
- `wb-rich-text`
- `wb-link-list`

This profile should remain generic and reusable. It must not assume a specific domain such as `ui.webblocksui.com`.

### Conservative

Best when source HTML is messy or unknown.

Prioritizes:

- fewer guesses
- more rich-text grouping
- explicit warnings
- visible fallbacks

## HTML To CMS Block Mapping Rules

### Generic Semantic Rules

| HTML Pattern | CMS Block |
| --- | --- |
| `h1`-`h6` | `header` |
| plain paragraph | `plain_text` or `rich-text` |
| multiple paragraphs with inline formatting | `rich-text` |
| `ul`, `ol`, `li` | `list` or `rich-text` depending on context |
| `pre > code` | `code` |
| `table` | `table` |
| `blockquote` | `quote` |
| `figure > img` | `image` placeholder plus media warning if media import is not available |
| repeated cards or repeated cells | `columns` + `column_item` |
| `details > summary` groups | `accordion` where possible |
| unknown but safe editorial markup | `html` fallback |

### WebBlocks UI Class-Aware Rules

| HTML Pattern | CMS Block |
| --- | --- |
| `.wb-section` | `section` |
| `.wb-content-header` | `content_header` |
| `.wb-promo` | `hero` or `cta` |
| `.wb-card` | `card` |
| `.wb-card-header` | `card_header` |
| `.wb-card-body` | `card_body` |
| `.wb-card-footer` | `card_footer` |
| `.wb-grid`, `.wb-grid-2`, `.wb-grid-3`, `.wb-grid-4` | `columns` |
| `.wb-btn` anchor/button | `button` |
| `.wb-alert`, `.wb-callout` | `callout` |
| `.wb-gallery` | `gallery` |
| `.wb-rich-text` | `rich-text` |
| `.wb-link-list` | `navigation-auto`, `toc`, or structured list depending on context |
| `.wb-stat` repeated cards | `columns` with stats-style items where supported |

## Safe HTML Fallback Policy

Safe HTML must be a last resort, not the default conversion output.

### Allowed Fallback Cases

- CMS has no first-class block for the fragment yet.
- Markup is too complex to map safely in the current version.
- The converter confidence is low.
- The admin explicitly accepts the fallback during review.

### Fallback Requirements

Every fallback must be shown in the review screen with:

- source fragment preview
- reason
- confidence score
- warning text
- approximate location in the page

### Fallback Anti-Goal

The converter must not create one large Safe HTML block for the entire `main` content when meaningful structured block mappings are available.

## Sanitization And Safety

The converter should sanitize structured text conversion aggressively.

Strip or reject:

- `<script>`
- event attributes such as `onclick`
- `javascript:` URLs
- `iframe`
- `object`
- `embed`
- dangerous inline styles where not explicitly supported
- unknown executable attributes

Remote URL fetching is excluded from MVP to avoid SSRF risks. If added later, it must block:

- localhost
- private IP ranges
- link-local addresses
- metadata services
- internal hostnames
- redirects to blocked addresses

## Media Handling

MVP should not attempt full media import.

For images:

- detect image references
- create image placeholder only if existing CMS media matching is implemented
- otherwise report a media warning
- preserve alt/caption suggestions for manual use

Later phases can add:

- upload local image files with an HTML archive
- match existing media by filename/hash
- import images from ZIP bundles
- rewrite image blocks to CMS media IDs

## Suggested Technical Architecture

### Controllers And Requests

```text
PageConverterController
PageConverterAnalyzeRequest
PageConverterCreateRequest
```

Controllers should stay thin and delegate parsing/conversion to services.

### Services

```text
Services/PageConverter/PageHtmlNormalizer
Services/PageConverter/PageHtmlSegmenter
Services/PageConverter/PageConversionEngine
Services/PageConverter/PageConversionProfileRegistry
Services/PageConverter/BlockSuggestionMapper
Services/PageConverter/ConvertedPageDraftCreator
Services/PageConverter/HtmlSafetySanitizer
```

### DTOs / Value Objects

```text
ConvertedPagePlan
ConvertedSlotPlan
ConvertedBlockPlan
ConversionWarning
ConversionSourceFragment
ConversionProfile
ConversionConfidence
```

### Runtime Flow

```text
HTML input
→ normalize
→ extract body/main
→ segment meaningful regions
→ map segments to block suggestions
→ calculate confidence and warnings
→ render review screen
→ create draft page in transaction
```

## Data Creation Rules

When creating the draft page:

- use database transactions
- create a new page only
- never overwrite existing pages in MVP
- create page as `draft`
- create default translation for selected locale
- create required slots for selected layout
- create only page-owned blocks in MVP
- do not create Shared Slots automatically
- do not publish automatically
- record revision metadata when supported by existing services

## Authorization Rules

MVP should follow existing page creation and site access rules.

Recommended access:

- `super_admin`: all sites
- `site_admin`: assigned sites
- `editor`: defer or allow only if normal page creation permits it

All site selectors must be scoped to the authenticated user's accessible sites.

## Path And Conflict Rules

The converter must validate:

- selected site exists and is accessible
- selected locale exists and is enabled for the site
- selected layout exists and is active
- title is present
- path/slug is valid
- path does not conflict with an existing page translation for the same site and locale

MVP must not replace, merge into, or update an existing page.

## Admin UI Standards

Use existing WebBlocks CMS admin patterns:

- card-based forms
- compact review tables
- clear warnings
- modal only when useful
- no browser confirm for destructive actions
- no custom CSS/JS unless necessary
- WebBlocks UI classes only

The review screen should make it easy to answer:

- What will be created?
- Which parts are structured blocks?
- Which parts are fallback HTML?
- Which parts were ignored?
- What requires manual review?

## Testing Plan

Focused feature tests should cover:

- authorized users can open Page Converter
- unauthorized users cannot access inaccessible sites
- pasted HTML can be analyzed
- uploaded `.html` file can be analyzed
- unsupported file types are rejected
- scripts and unsafe attributes are stripped from structured conversion
- `h1`/`h2` maps to header block suggestion
- paragraphs map to rich-text/plain_text suggestions
- `pre > code` maps to code suggestion
- `table` maps to table suggestion
- `.wb-card` maps to card suggestion
- `.wb-grid` repeated items map to columns/column_item suggestions
- unknown fragments are reported as fallback, not silently discarded
- target path conflict blocks draft creation
- draft page is created only after explicit submit
- created page is not published
- no existing page is overwritten

## Documentation Updates

Add or update:

- `docs/page-converter.md`
- `docs/index.md`
- `README.md`
- `CHANGELOG.md`

Documentation should include:

- feature purpose
- source input modes
- conversion profiles
- Safe HTML fallback policy
- security exclusions
- MVP limitations
- future roadmap

## Changelog Wording Draft

```md
## Unreleased

- Add a reusable admin Page Converter foundation for turning pasted or uploaded static HTML into draft CMS pages made from structured blocks, with an analysis/review step, Safe HTML fallback reporting, and draft-only creation.
```

## Implementation Phases

### Phase 0: Documentation Only

Create this roadmap and add a canonical CMS documentation page for Page Converter planning.

No runtime changes.

### Phase 1: Read-Only Analyzer

Add admin screen and analysis flow only.

- Accept pasted HTML.
- Produce conversion plan.
- Show review screen.
- Do not create pages yet.

### Phase 2: Draft Creator

Add explicit create action.

- Create draft page.
- Create main slot blocks.
- Validate path conflicts.
- Preserve warnings in UI.

### Phase 3: HTML File Upload

Add `.html` / `.htm` upload support.

### Phase 4: Better Structured Mappings

Improve mappings for:

- deeper card region children
- columns
- richer accordion item rendering beyond the current plain text `faq` child contract
- tables
- lists
- callouts
- hero/cta managed button children

### Phase 5: Media-Aware Conversion

Add optional support for local image bundles or matching existing media.

### Phase 6: Batch Conversion

Add multi-page workflows:

- ZIP upload
- page inventory
- per-page review
- batch draft creation

### Phase 7: Optional URL Fetching

Only after strict SSRF and network safety rules are implemented.

## Acceptance Criteria For MVP

MVP is successful when:

- Admin can paste or upload one static HTML page.
- CMS shows a reviewable conversion plan.
- Common HTML structures become structured CMS blocks.
- Safe HTML fallback is visible and limited.
- Admin can create a new draft page.
- The created page can be edited in the normal Page Builder.
- No page is published automatically.
- No existing page is overwritten.
- Tests cover the core conversion and safety behavior.

## Notes For WebBlocks UI Static Site Migration

The WebBlocks UI static site migration should become the first real-world usage of this generic Page Converter.

Recommended pilot page:

```text
pattern-admin-standards.html
```

Why this page:

- it contains many WebBlocks UI patterns
- it tests docs layout behavior
- it includes content shell, cards, tables, status guidance, and code-like examples
- it helps validate WebBlocks UI class-aware mapping

The migration should not add WebBlocks UI-specific behavior directly to CMS core. If extra mapping intelligence is needed, add it as a reusable profile such as `WebBlocks UI-flavored HTML`, or later as an optional operator/plugin profile.
