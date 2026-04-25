# WebBlocks UI Docs -> WebBlocks CMS Migration Plan

## Scope

- Task type: inventory and mapping only.
- Target CMS site: `ui.docs.webblocksui.com.ddev.site`.
- Static source root: `/Users/osm/Sites/projects/project-web_blocks/project-webblocks-ui/webblocks-ui/docs`.
- Static source project is outside this CMS repository.
- No CMS content was created, updated, deleted, or migrated for this plan.

## Inventory Summary

- Static docs root found: `/Users/osm/Sites/projects/project-web_blocks/project-webblocks-ui/webblocks-ui/docs`
- Docs pages inventoried: `18`
- Non-doc HTML pages intentionally excluded from migration inventory: `/index.html`, `/playground/index.html`

## Static Docs Page Inventory

| Source file path | Current/static URL | Proposed CMS path | Proposed page title | Likely content complexity | Likely migration approach | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `project-webblocks-ui/webblocks-ui/docs/index.html` | `/` | `/` | WebBlocks UI Home | moderate | text/rich text blocks | Docs home uses promo, stats, link lists, alerts, and content footer nav. Good candidate for mostly native blocks plus some layout helpers. |
| `project-webblocks-ui/webblocks-ui/docs/getting-started.html` | `/getting-started.html` | `/getting-started` | Getting Started | simple | text/rich text blocks | Best baseline page. Mostly headings, prose, code samples, callouts, link lists, tabs, and next/previous footer. |
| `project-webblocks-ui/webblocks-ui/docs/architecture.html` | `/architecture.html` | `/architecture` | Architecture | moderate | text/rich text blocks | Reference-style article page; should map well to docs article layout with TOC and code/table support. |
| `project-webblocks-ui/webblocks-ui/docs/foundation.html` | `/foundation.html` | `/foundation` | Foundation | moderate | text/rich text blocks | Heavy reference content with examples; likely needs code/table/callout support but not custom runtime. |
| `project-webblocks-ui/webblocks-ui/docs/layout.html` | `/layout.html` | `/layout` | Layout | moderate | text/rich text blocks | Mix of prose, tables, examples, and footer pagination. Native blocks should cover most content. |
| `project-webblocks-ui/webblocks-ui/docs/primitives.html` | `/primitives.html` | `/primitives` | Primitives | complex | HTML block | Large reference page with many live examples, modal, drawer, dropdown, gallery viewer, and password toggle examples. Temporary HTML fallback is acceptable. |
| `project-webblocks-ui/webblocks-ui/docs/icons.html` | `/icons.html` | `/icons` | Icons | complex | HTML block | Likely includes large icon reference grid/list. Better migrated via HTML fallback first, then refined into dedicated reference blocks later. |
| `project-webblocks-ui/webblocks-ui/docs/patterns.html` | `/patterns.html` | `/patterns` | Patterns | moderate | text/rich text blocks | Overview page with cards, callouts, links, gallery/cookie-consent references, and local TOC. Mostly native-capable. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-dashboard-shell.html` | `/pattern-dashboard-shell.html` | `/patterns/dashboard-shell` | Dashboard Shell | moderate | HTML block | Pattern demo page is mostly one embedded screen example. Safer to preserve with one HTML block initially. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-settings-shell.html` | `/pattern-settings-shell.html` | `/patterns/settings-shell` | Settings Shell | complex | requires shared modal/overlay | Includes embedded settings shell plus destructive confirm modal. Needs shared overlay root and likely HTML fallback first. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-auth-shell.html` | `/pattern-auth-shell.html` | `/patterns/auth-shell` | Auth Shell | moderate | HTML block | Compact but example-heavy auth shell page. Mostly one canonical pattern demo with password toggle. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-content-shell.html` | `/pattern-content-shell.html` | `/patterns/content-shell` | Content Shell | moderate | text/rich text blocks | Strong fit for native docs layout. Content is mostly prose, callouts, links, table, and embedded content-shell example. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-breadcrumb.html` | `/pattern-breadcrumb.html` | `/patterns/breadcrumb` | Breadcrumb | moderate | text/rich text blocks | Reference/article style with examples and guidance. Native blocks plus breadcrumb-aware docs layout should be enough. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-gallery.html` | `/pattern-gallery.html` | `/patterns/gallery` | Gallery | complex | requires shared modal/overlay | Gallery examples depend on shared modal viewer contract and many example tiles. Existing CMS overlay root helps; still best as HTML fallback first. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-cookie-consent.html` | `/pattern-cookie-consent.html` | `/patterns/cookie-consent` | Cookie Consent | complex | requires shared modal/overlay | Uses live consent banner, reopen hook, shared modal, API docs, and legal/integration notes. CMS already has cookie consent infrastructure, but this docs page still needs careful mapping. |
| `project-webblocks-ui/webblocks-ui/docs/pattern-marketing.html` | `/pattern-marketing.html` | `/patterns/marketing` | Marketing | moderate | HTML block | Public landing-page example embedded inside docs surface. Likely one or two HTML blocks initially. |
| `project-webblocks-ui/webblocks-ui/docs/utilities.html` | `/utilities.html` | `/utilities` | Utilities | moderate | text/rich text blocks | Utility reference page should fit docs article pattern with code and examples. |
| `project-webblocks-ui/webblocks-ui/docs/javascript.html` | `/javascript.html` | `/javascript` | JavaScript | complex | requires shared modal/overlay | Reference page includes tables, code, live tabs/accordion/dropdown demos, and overlay-root guidance. Native blocks cover much of it, but interactive examples may need HTML fallback first. |

## Shared Structures

### Header and Top Nav

- All docs pages use a shared docs chrome built on `wb-dashboard-shell`.
- Left sidebar is the primary docs navigation.
- Mobile header includes sidebar toggle via `data-wb-toggle="sidebar"` targeting `#docsSidebar`.
- Top bar usually includes:
  - breadcrumb
  - mode cycle trigger via `data-wb-mode-cycle`
  - theme controls dropdown via `data-wb-toggle="dropdown"`
  - occasional back-to-patterns or playground shortcut buttons

### Docs Sidebar / Docs Navigation

- Shared left sidebar links include:
  - Home
  - Getting Started
  - Architecture
  - Foundation
  - Layout
  - Primitives
  - Icons
  - Patterns group
  - Utilities
  - JavaScript
  - Playground
- Patterns are grouped with `wb-nav-group` and contain:
  - Overview
  - Dashboard Shell
  - Settings Shell
  - Auth Shell
  - Content Shell
  - Breadcrumb
  - Gallery
  - Cookie Consent
  - Marketing
- Most article-style pages also include an in-page rail using `wb-section-nav` with an "On this page" label.

### Footer and Legal Links

- No shared legal footer is present in static docs pages.
- Shared sidebar footer only shows `WebBlocks UI v2.4.0`.
- Many pages include `wb-content-footer` previous/next navigation at page bottom.
- `pattern-cookie-consent.html` references footer reopen behavior for cookie settings but does not provide a shared legal footer component for the docs site.

### Shared CSS/JS Dependencies

- Every docs page loads `docs/webblocks-assets.js`.
- `docs/webblocks-assets.js` injects:
  - `../packages/webblocks/dist/webblocks-ui.css`
  - `../packages/webblocks/dist/webblocks-icons.css`
  - `../packages/webblocks/dist/webblocks-ui.js`
  - branding favicons and manifest from `../assets/branding/`
- It also injects one docs-specific inline style:
  - `.wb-dashboard-shell > .wb-sidebar{--wb-sidebar-w:320px;}`
- Runtime dependencies visible in docs examples include:
  - sidebar toggles
  - dropdowns
  - tabs
  - accordion
  - modal
  - gallery viewer
  - cookie consent
  - password toggle
  - theme controls

### WebBlocks UI Assets Used by the Docs

- Branding image used across docs: `../assets/branding/brand-logo-64.png`
- Favicon and manifest assets from `../assets/branding/`
- WebBlocks UI dist assets from `../packages/webblocks/dist/`
- Some pages also use remote example imagery, especially gallery examples via Unsplash URLs.

### Overlay / Modal Examples That May Require `#wb-overlay-root`

- `pattern-cookie-consent.html`
  - shared preference-center modal
  - live reopen button
  - live consent banner
- `pattern-gallery.html`
  - shared gallery viewer modal
- `pattern-settings-shell.html`
  - destructive confirmation modal
- `primitives.html`
  - modal examples
  - drawer examples
  - gallery viewer modal
- `javascript.html`
  - modal trigger examples in code and live demos
- `getting-started.html`
  - modal and drawer hooks shown in code samples

## Current CMS Fit

### Existing CMS Building Blocks That Already Help

- Layout types seeded in CMS: `default`, `landing`, `sidebar-left`, `sidebar-right`, `full-width`, `dashboard`, `system`.
- Slot types seeded in CMS: `header`, `main`, `sidebar`, `footer`.
- Existing CMS block catalog includes useful docs-oriented pieces:
  - `heading`
  - `text`
  - `rich-text`
  - `callout`
  - `code`
  - `table`
  - `tabs`
  - `faq`
  - `gallery`
  - `breadcrumb`
  - `toc`
  - `html`
  - layout helpers such as `section`, `container`, `columns`, `split`, `stack`, `grid`
- Important runtime support already present in CMS public layout:
  - `#wb-overlay-root` exists in `resources/views/layouts/public.blade.php`
  - shared cookie consent modal infrastructure already exists
  - shared gallery viewer modal already exists

### Important Constraint

- A block type existing in the seeded catalog does not guarantee a purpose-built docs authoring experience.
- Some block types currently rely on generic fallback rendering rather than a specialized docs block UI.
- That means the first migration pass should prefer a practical mix of:
  - native CMS blocks where cleanly supported
  - HTML fallback blocks where pattern demos would otherwise fragment badly

## Mapping Static Docs to CMS Concepts

### Family: Docs Home

- Pages:
  - `/`
- Recommended CMS layout:
  - `full-width`
- Slots used:
  - `header`, `main`, `footer`
- Block types needed:
  - `breadcrumb`
  - `heading`
  - `rich-text`
  - `callout`
  - `stats` or HTML fallback
  - `button`
  - `link-list` equivalent via HTML fallback or rich text until dedicated block exists
  - `toc` not necessary
- Are current CMS blocks enough:
  - partially
- HTML fallback acceptable temporarily:
  - yes, for promo/stat/link-list sections

### Family: Article / Reference Pages

- Pages:
  - `getting-started`
  - `architecture`
  - `foundation`
  - `layout`
  - `primitives`
  - `icons`
  - `utilities`
  - `javascript`
- Recommended CMS layout:
  - `sidebar-right` or `sidebar-left` for docs rail + article body
- Slots used:
  - `header`, `main`, `sidebar`, `footer`
- Block types needed:
  - `breadcrumb`
  - `heading`
  - `rich-text`
  - `callout`
  - `code`
  - `table`
  - `tabs`
  - `toc`
  - `html` for advanced live examples
- Are current CMS blocks enough:
  - enough for simple and moderate article pages
  - not enough for polished interactive docs examples without fallback
- HTML fallback acceptable temporarily:
  - yes, especially for `primitives`, `icons`, and interactive sections of `javascript`

### Family: Pattern Overview

- Pages:
  - `patterns`
- Recommended CMS layout:
  - `sidebar-right`
- Slots used:
  - `header`, `main`, `sidebar`, `footer`
- Block types needed:
  - `breadcrumb`
  - `heading`
  - `rich-text`
  - `callout`
  - example grid/card listing block or HTML fallback
  - `toc`
- Are current CMS blocks enough:
  - mostly
- HTML fallback acceptable temporarily:
  - yes, for pattern card grid if no dedicated example-grid block exists

### Family: Pattern Demo Pages

- Pages:
  - `pattern-dashboard-shell`
  - `pattern-settings-shell`
  - `pattern-auth-shell`
  - `pattern-content-shell`
  - `pattern-breadcrumb`
  - `pattern-gallery`
  - `pattern-cookie-consent`
  - `pattern-marketing`
- Recommended CMS layout:
  - `sidebar-right` for article-like pattern docs
  - `full-width` can also work for pages with heavy embedded demos
- Slots used:
  - `header`, `main`, `sidebar`, `footer`
- Block types needed:
  - `breadcrumb`
  - `heading`
  - `rich-text`
  - `callout`
  - `code`
  - `table`
  - `gallery`
  - `toc`
  - pattern example / component preview / shared modal demo blocks later
  - `html` fallback now for embedded demo sections
- Are current CMS blocks enough:
  - enough for explanatory article wrapper
  - not enough for strong reusable pattern-demo authoring
- HTML fallback acceptable temporarily:
  - yes, and recommended for first-pass migration of most pattern demo pages

## Proposed CMS Information Architecture

- `/`
- `/getting-started`
- `/architecture`
- `/foundation`
- `/layout`
- `/primitives`
- `/icons`
- `/patterns`
- `/patterns/dashboard-shell`
- `/patterns/settings-shell`
- `/patterns/auth-shell`
- `/patterns/content-shell`
- `/patterns/breadcrumb`
- `/patterns/gallery`
- `/patterns/cookie-consent`
- `/patterns/marketing`
- `/utilities`
- `/javascript`

This path model removes `.html`, groups all pattern detail pages under `/patterns/*`, and matches how a CMS-managed docs tree should be represented in navigation.

## CMS Block Gaps

| Gap | Why it is needed | Priority |
| --- | --- | --- |
| Documentation article | A purpose-built article block or article composition would reduce fragmentation across heading, prose, meta, and article body sections. | can wait |
| Code example | `code` exists, but docs need fenced language support, copy button behavior, optional filename, and tighter docs styling. | required now |
| Component preview | Many docs sections pair explanation with live rendered output. HTML fallback works short-term, but reusable preview framing is missing. | required now |
| Pattern example | Pattern pages need a repeatable demo wrapper for shell examples, not just raw HTML blobs. | required now |
| Callout | A `callout` block exists, but editorial control over variants, title, and body should be confirmed during migration. | can wait |
| Table of contents | `toc` exists, but article anchor generation and section synchronization for docs pages should be verified. | required now |
| API/options table | `table` exists, but docs need clean key/value or API schema tables with code formatting and consistent header handling. | can wait |
| Example grid | Pattern overview and icon/gallery reference pages benefit from a dedicated example card/grid block. | can wait |
| Shared modal demo | Existing overlay infrastructure exists, but there is no dedicated authorable docs block for modal-driven demos tied to shared overlay root. | required now |

### Gap Guidance

- Required now:
  - code example
  - component preview
  - pattern example
  - table of contents verification
  - shared modal demo
- Can wait:
  - documentation article
  - callout improvements
  - API/options table refinement
  - example grid
- Avoid for now:
  - highly custom one-off blocks per individual docs page
  - bespoke overlay systems outside the shared `#wb-overlay-root`
  - migration-time redesign of the docs IA or visual system

## Recommended Pilot Migration

### First Page

- `getting-started.html`

### Why This One

- It is the cleanest baseline for a first CMS rebuild.
- It represents the core article pattern used by much of the docs site.
- It exercises the most important docs needs without immediately forcing the hardest runtime cases:
  - headings and prose
  - code blocks
  - callouts
  - link lists
  - in-page section nav / TOC
  - tabs
  - previous/next footer navigation
- It is simpler than the gallery or cookie consent pages, so it is better for validating:
  - page model
  - layout choice
  - sidebar/TOC composition
  - how much native CMS block usage is practical before HTML fallback is needed

## Verification Notes

### Read-Only Checks Performed

- Located and inventoried all static docs HTML files under the external source path.
- Reviewed representative source pages including:
  - `index.html`
  - `getting-started.html`
  - `patterns.html`
  - `pattern-dashboard-shell.html`
  - `pattern-settings-shell.html`
  - `pattern-auth-shell.html`
  - `pattern-content-shell.html`
  - `pattern-gallery.html`
  - `pattern-cookie-consent.html`
  - `pattern-marketing.html`
  - `primitives.html`
  - `javascript.html`
- Reviewed shared static dependency loader:
  - `docs/webblocks-assets.js`
- Reviewed CMS page rendering and layout support:
  - `resources/views/layouts/public.blade.php`
  - `resources/views/pages/show.blade.php`
  - existing public block partials
- Reviewed CMS block, slot, and layout catalogs:
  - `database/seeders/BlockTypeSeeder.php`
  - `database/seeders/LayoutTypeSeeder.php`
  - `database/seeders/SlotTypeSeeder.php`

### CMS Host Context

- Expected target host for migration work remains `ui.docs.webblocksui.com.ddev.site`.
- CMS public layout already includes shared overlay infrastructure suitable for modal and gallery demos.

## Recommended Next Execution Order

1. Create the docs IA pages and navigation in CMS without importing full content yet.
2. Build the pilot page: `getting-started`.
3. Decide the native-block vs HTML-fallback threshold from the pilot.
4. Migrate article/reference pages next.
5. Migrate complex pattern demo pages after preview/modal conventions are settled.
