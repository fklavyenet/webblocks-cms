# Block UI Alignment Audit — Phase 4

Phase 3 aligned the most visible public rendering paths in WebBlocks CMS: layout modes, hero, buttons, columns, code, toc, and the highest-impact public content blocks now map more directly to shipped WebBlocks UI primitives.

Phase 4 should continue block-by-block, but only after the remaining block inventory is mapped clearly. This document is that map. It focuses on UI output only: what each CMS block should render as in WebBlocks UI, what should stay fallback/custom, what should merge into variants instead of becoming standalone features, and what should be implemented in small batches.

Audit scope:

- 62 seeded block slugs from `database/seeders/BlockTypeSeeder.php`
- 3 additional existing public-renderer types outside the core seeder set: `card-grid`, `showcase-list`, `contact-info`
- total audited slugs: 65

Baseline:

- use only confirmed shipped WebBlocks UI primitives and patterns
- treat missing classes or missing patterns as WebBlocks UI gaps, not CMS-only hacks
- preserve backward compatibility with existing fallback-rendered content while improving future alignment

## Matrix

| Block slug | Current support | Current UI quality | Intended WebBlocks UI output | Recommendation | Phase 4 priority |
| --- | --- | --- | --- | --- | --- |
| `heading` | first-class | aligned | semantic `h1`-`h6` with optional anchor id | keep | P3 custom/fallback |
| `text` | first-class | aligned | plain body copy with `wb-stack` rhythm only when needed | keep | P3 custom/fallback |
| `rich-text` | first-class | acceptable | readable escaped multi-paragraph content in simple `wb-stack` rhythm | align | P2 later |
| `html` | first-class | acceptable | trusted raw HTML in a plain block wrapper | keep | P3 custom/fallback |
| `section` | first-class | aligned | `wb-section`, optional `wb-promo`, child buttons in `wb-promo-actions` | keep | P3 custom/fallback |
| `hero` | first-class | aligned | `wb-promo` marketing shell | keep | P3 custom/fallback |
| `button` | first-class | aligned | explicit `wb-btn` variant mapping | keep | P3 custom/fallback |
| `columns` | first-class | aligned | `wb-grid` or `wb-link-list` driven by explicit parent variants | keep | P3 custom/fallback |
| `column_item` | first-class | aligned | plain cell, card, stat, or link-list row driven by parent columns variant | keep | P3 custom/fallback |
| `callout` | first-class | acceptable | `wb-alert` and optional `wb-callout` shell where shipped | align | P2 later |
| `quote` | first-class | acceptable | semantic `blockquote`, optional framed card | align | P2 later |
| `faq` | first-class | acceptable | simple `wb-card` plus `wb-stack` for one-off Q/A | keep | P3 custom/fallback |
| `code` | public-renderer-only | acceptable | escaped `<pre><code>` in a simple code shell | keep | P2 later |
| `toc` | public-renderer-only | acceptable | `wb-link-list` built from existing anchored headings | keep | P2 later |
| `image` | first-class | acceptable | semantic `figure`, `img`, optional `figcaption`, optional link | align | P2 later |
| `gallery` | first-class | aligned | `wb-gallery` with shared overlay root | keep | P3 custom/fallback |
| `download` | first-class | acceptable | `wb-btn` or compact card-plus-button CTA | align | P2 later |
| `navigation-auto` | first-class | acceptable | simple nav/list output, optionally `wb-link-list` by context | keep | P3 custom/fallback |
| `menu` | legacy alias | acceptable | same output as `navigation-auto` | deprecate later | P3 custom/fallback |
| `contact_form` | first-class | aligned | structured form primitives with `wb-btn` and `wb-alert` | keep | P3 custom/fallback |
| `card-grid` | public-renderer-only | acceptable | `wb-grid` of `wb-card` items | merge into another block | P1 next |
| `list` | first-class | aligned | semantic `ul` or `ol` in `wb-stack` rhythm | keep | P3 custom/fallback |
| `table` | first-class | aligned | `wb-table` inside `wb-table-wrap` with explicit header/body handling | keep | P3 custom/fallback |
| `accordion` | first-class | acceptable | grouped disclosure items using semantic `<details>` and `<summary>` | keep | P3 custom/fallback |
| `tabs` | first-class | weak | true interactive tabset only if WebBlocks UI ships a real tabs pattern | defer | P2 later |
| `slider` | fallback-only | custom-only | carousel only if WebBlocks UI ships a canonical slider pattern; otherwise keep custom | custom only | P3 custom/fallback |
| `video` | fallback-only | weak | compact media card with semantic player or external embed plus copy | align | P2 later |
| `audio` | fallback-only | weak | compact media card with semantic audio player plus copy | align | P2 later |
| `file` | fallback-only | weak | file CTA as compact card or button | align | P2 later |
| `map` | fallback-only | weak | compact card with map summary and external/open action | align | P2 later |
| `breadcrumb` | fallback-only | weak | `nav[aria-label="Breadcrumb"]` with simple list or cluster links | defer | P2 later |
| `pagination` | fallback-only | weak | previous/next navigation using `wb-btn` and simple layout primitives | align | P1 next |
| `form` | fallback-only | weak | internal form wrapper around field blocks only if generic form builder becomes real product scope | keep fallback | P3 custom/fallback |
| `input` | fallback-only | acceptable | `label` plus `wb-input` | keep fallback | P3 custom/fallback |
| `textarea` | fallback-only | acceptable | `label` plus `wb-textarea` | keep fallback | P3 custom/fallback |
| `select` | fallback-only | acceptable | `label` plus `wb-select` | keep fallback | P3 custom/fallback |
| `checkbox-group` | fallback-only | acceptable | semantic fieldset with grouped checkbox controls | keep fallback | P3 custom/fallback |
| `radio-group` | fallback-only | acceptable | semantic fieldset with grouped radio controls | keep fallback | P3 custom/fallback |
| `submit` | fallback-only | acceptable | `wb-btn wb-btn-primary` submit control | keep fallback | P3 custom/fallback |
| `search` | fallback-only | acceptable | `wb-input` search field or compact search form | keep fallback | P3 custom/fallback |
| `product-card` | fallback-only | custom-only | commerce product summary card | custom only | P3 custom/fallback |
| `product-grid` | fallback-only | custom-only | grid of commerce product cards | custom only | P3 custom/fallback |
| `pricing` | fallback-only | weak | pricing plan card/grid only with real structured plan data | custom only | P3 custom/fallback |
| `cart-summary` | fallback-only | custom-only | commerce summary card/list | custom only | P3 custom/fallback |
| `checkout-summary` | fallback-only | custom-only | checkout summary card/list | custom only | P3 custom/fallback |
| `social-links` | fallback-only | acceptable | outbound links in `wb-cluster` or `wb-link-list` | align | P2 later |
| `share-buttons` | fallback-only | acceptable | small `wb-btn` action cluster | keep fallback | P2 later |
| `testimonial` | fallback-only | weak | quote/testimonial card using quote semantics first | merge into another block | P1 next |
| `comments` | fallback-only | custom-only | threaded or listed discussion items in card/list shells | custom only | P3 custom/fallback |
| `stats` | fallback-only | weak | `wb-stat` grid or stat list | merge into another block | P1 next |
| `metric-card` | fallback-only | weak | `wb-stat` or stat-card variant | merge into another block | P1 next |
| `logo-cloud` | fallback-only | weak | simple logo grid or brand strip | align | P2 later |
| `timeline` | fallback-only | weak | milestone stack or card list | align | P2 later |
| `feature-grid` | fallback-only | weak | feature cards through `columns` cards variant unless a separate pattern proves necessary | merge into another block | P1 next |
| `comparison` | fallback-only | weak | comparison table or paired card pattern | align | P2 later |
| `team` | fallback-only | weak | profile card grid | align | P2 later |
| `faq-list` | first-class alias | acceptable | grouped FAQ list rendered through the accordion family block | deprecate later | P2 later |
| `container` | layout/meta | weak | internal content wrapper using stack rhythm, not a public design pattern | deprecate later | P2 later |
| `split` | layout/meta | weak | two-column content wrapper | keep | P2 later |
| `stack` | layout/meta | acceptable | internal stacked layout wrapper | keep | P2 later |
| `grid` | layout/meta | acceptable | internal grid layout wrapper | keep | P2 later |
| `card-group` | layout/meta | weak | grouped card layout wrapper | merge into another block | P2 later |
| `page-title` | layout/meta | acceptable | resolved page title heading | keep | P3 custom/fallback |
| `page-content` | layout/meta | weak | resolved page summary/content shell only if system metadata blocks remain productized | deprecate later | P2 later |
| `page-meta` | layout/meta | acceptable | system metadata card/list | keep | P3 custom/fallback |
| `related-content` | first-class | aligned | `wb-link-list` of editorial links or related pages | keep | P3 custom/fallback |
| `auth-form` | fallback-only | weak | app/auth shell, not ordinary page content | custom only | P3 custom/fallback |
| `cookie-notice` | layout/meta | missing | privacy UI belongs in shared public layout and modal partials, not editorial content blocks | deprecate later | P3 custom/fallback |
| `showcase-list` | custom/site-specific | custom-only | showcase cards with gallery sections | custom only | P3 custom/fallback |
| `contact-info` | custom/site-specific | acceptable | contact metadata card or link-list | custom only | P3 custom/fallback |

## Recommendation Notes

### A. Editorial basics

- `list` is now first-class with a small line-based editor and compatibility for legacy fallback-style settings data.
- `table` is now first-class with line-based row entry, explicit header-row behavior, and compatibility for legacy fallback-style settings rows.
- `breadcrumb` stays deferred until the public shell actually uses breadcrumb navigation in real pages and a shipped breadcrumb pattern is confirmed.
- `pagination` should stay small and simple if promoted: previous/next only, using existing button primitives.
- `related-content` is now first-class and can render either editorial links or automatic related pages through the same `wb-link-list` pattern.

### B. Disclosure/interactivity

- `accordion` is now the canonical grouped disclosure block and intentionally uses semantic `<details>` / `<summary>` markup without JS.
- `faq` remains backward-compatible as a simple single Q/A block and also serves as a natural child item inside accordion-style blocks.
- `faq-list` is now transitional and renders through the same accordion family path rather than maintaining a separate long-term pattern.
- `tabs` should not receive a cosmetic patch if WebBlocks UI does not ship a real tabs pattern. Current recommendation: defer until the design system gains a canonical tabs implementation.
- No shipped accordion-specific or tabs-specific WebBlocks UI classes/hooks have been confirmed in the Phase 2/3 baseline. That means Phase 4 should prefer semantic minimalism over CMS-only interactivity hacks.

### C. Media/content embeds

- `video`, `audio`, `file`, and `map` are reasonable first-class candidates only as lightweight media shells using existing cards, buttons, and semantic HTML.
- `slider` should not be expanded first. Without a clearly confirmed WebBlocks UI carousel pattern, it should remain custom/fallback-oriented.
- `gallery` already covers the strongest shipped visual media pattern and should stay the baseline for image-rich content.

### D. Marketing blocks

- `stats` and `metric-card` should merge into the existing stat-oriented direction already established by `columns.variant = stats`.
- `feature-grid` should merge into `columns.variant = cards` unless a clearly different structured pattern is required.
- `testimonial` should likely merge into `quote` variants rather than become a standalone long-term block.
- `card-grid` should be reviewed alongside `columns`; the likely direction is consolidation, not two parallel card-grid systems.
- `logo-cloud`, `timeline`, `comparison`, and `team` are reasonable Phase 4C candidates only if real recurring editorial demand exists.
- `pricing` should remain custom/fallback until there is structured pricing data worth productizing.

### E. Forms

- `contact_form` should remain the only clearly first-class public form block for now.
- Generic form-builder slugs (`form`, `input`, `textarea`, `select`, `checkbox-group`, `radio-group`, `submit`, `search`) should not become a major product area in Phase 4 unless there is a strong explicit roadmap for reusable public forms.
- `auth-form` should remain app/auth-specific, not ordinary editorial page content.
- Recommendation: keep generic form-builder blocks fallback-oriented or internal for now, rather than investing in a broad public form-builder UX.

### F. Commerce

- `product-card`, `product-grid`, `cart-summary`, and `checkout-summary` should remain fallback/custom.
- WebBlocks CMS is not ecommerce-first today, so these blocks should not drive early Phase 4 scope.
- `pricing` belongs with marketing/commerce overlap and should also stay deferred unless product requirements become concrete.

### G. Layout/meta blocks

- `container`, `split`, `stack`, `grid`, and `card-group` are layout helpers, not strong end-user content patterns.
- `split`, `stack`, and `grid` may remain internal layout helpers, but they should not expand into a sprawling editor surface without a clear user need.
- `container` and `card-group` are good candidates for later simplification or consolidation.
- `page-title`, `page-content`, and `page-meta` should remain system/layout-meta tools rather than editorial primitives.

### H. Privacy/system blocks

- `cookie-notice` should not become an ordinary page-content block.
- Cookie consent belongs in the shared public layout and privacy partials, where it already lives.
- Recommendation: keep `cookie-notice` deprecated-later or system-only, not a future first-class editorial surface.

## Recommended Phase 4 implementation batches

### Phase 4A — Editorial basics

- `list`
- `table`
- `related-content`
- `breadcrumb` deferred pending verified UI support and real shell need

### Phase 4B — Disclosure blocks

- `accordion`
- `faq-list` -> accordion merge direction
- `faq` stays simple and backward-compatible
- `tabs` decision: defer until a real shipped pattern exists

### Phase 4C — Marketing pattern blocks

- `stats` / `metric-card`
- `testimonial`
- `logo-cloud`
- `timeline`
- `feature-grid` / `card-grid` consolidation
- `comparison` and `team` only if clearly needed

### Phase 4D — Media/embed cleanup

- `video`
- `audio`
- `file`
- decide `map` and `slider` direction without inventing CMS-only UI patterns

### Deferred / custom

- commerce blocks
- generic form-builder blocks
- `auth-form`
- `cookie-notice`
- `showcase-list`
- `contact-info` unless it proves to be a broadly reusable editorial pattern

Phase 4 should not start as a huge all-block rewrite. The recommended path is a small-batch sequence that promotes only the patterns that are common, clearly modeled, and already supported by shipped WebBlocks UI primitives.

## Risks

- Too many first-class blocks can bloat admin UX and make the picker harder to use.
- Several slugs should likely merge into variants instead of becoming separate long-term block families.
- Settings-driven blocks should not remain a permanent solution for user-facing content patterns.
- Missing WebBlocks UI patterns should be fixed in WebBlocks UI first, not patched with CMS-only CSS or ad hoc JS.
- Backward compatibility with existing fallback-rendered content must be preserved while Phase 4 promotes selected blocks.
