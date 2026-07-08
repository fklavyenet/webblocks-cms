# webblocksui.com Homepage Redesign

cms_source_id: webblocks-cms:docs/webblocksui-homepage-redesign.md

## Goal

Redesign the `webblocksui.com` homepage as a WebBlocks ecosystem product page, not a simple link hub.

The page should introduce:

- WebBlocks CMS: content, blocks, media, staged publishing, system updates.
- WebBlocks UI Docs: UI patterns, primitives, and contracts.
- CMS Docs: architecture, publishing, release, and operator workflows.

The design should remain CMS-maintainable through native blocks first. Site CSS may polish the page, but the content structure should stay understandable to editors.

## Reference Mockup

Primary visual reference:

![WebBlocks homepage ecosystem mockup](design-assets/webblocksui-homepage-ecosystem-mockup-2026-07-07.png)

Local file:

`docs/design-assets/webblocksui-homepage-ecosystem-mockup-2026-07-07.png`

## Current Live Implementation Status

On July 7, 2026, the homepage was applied to `https://webblocksui.com/` through the trusted CMS content API:

- Source page: `webblocksui.com`, page `id=2`, path `/`.
- Staged page used during rollout: `id=95`.
- Promoted slot: `main`.
- Site CSS asset updated for site `id=2` with a scoped `body.wb-page-home` block.
- Renderability validation passed with no Trusted HTML blocks and no empty wrapper/text/button warnings.

On July 8, 2026, the first shared chrome pass was applied through the trusted CMS content API:

- Synced the homepage layout slots so page `id=2` has `header`, `main`, `sidebar`, and `footer` slots without changing the page-owned `main` blocks.
- Created site-scoped Shared Slots `site-header` (`id=8`) and `site-footer` (`id=9`) for site `id=2`.
- Published the Shared Slot block trees explicitly: 5 header blocks and 8 footer blocks.
- Assigned the homepage `header` slot to `site-header` and the `footer` slot to `site-footer`.
- Used native CMS blocks only: `sticky-navbar`, `navbar-brand`, `navbar-navigation`, `header-actions`, `button_link`, `section`, `container`, `cluster`, `rich-text`, and `plain_text`.
- Public HTML smoke check found the rendered `header` and `footer` slots plus the expected WebBlocks footer copy.

The header was then revised in a second July 8 pass:

- Created `site-header-contained` (`id=10`) and assigned the homepage `header` slot to it.
- Added the Media Library `WebBlocks CMS logo mark` image (`id=3`) to the `navbar-brand`.
- Changed the brand copy to `WebBlocks CMS` with the CMS product slogan `A modern block-based CMS` as the brand subtitle.
- Nested the navbar content under a native `container` block with `width: lg` so the header content is constrained instead of spanning the full viewport.
- Public HTML smoke check found the CMS logo URL, brand title, slogan, rendered `header` slot, and `wb-container-lg`.

A third July 8 header pass removed the redundant right-side `CMS` button:

- Created `site-header-contained-no-cms-button` (`id=11`) and assigned the homepage `header` slot to it.
- Preserved the contained header structure, CMS logo mark, brand title, slogan, navigation, search, and mode toggle.
- Public HTML smoke check found the expected brand/container output and did not find a redundant `CMS` button anchor.

The live page was functionally installed, but visually still only partially matched the mockup after the shared chrome pass. Design alignment estimate at that point: **38/100**.

The hero dashboard panel was then converted to a bitmap asset in a fourth July 8 pass:

- Cropped the right-side ecosystem/dashboard panel from the homepage reference mockup into `docs/design-assets/webblocksui-hero-dashboard-panel-2026-07-08.png`.
- Uploaded the panel to the CMS Media Library as image `id=4`.
- Created staged update page `id=100`, replaced only the page-owned `main` slot, and promoted the staged update back to source page `id=2`.
- Replaced the first hero grid's right-side native card (`id=9704`) with a native `image` block using Media `id=4`.
- Preserved the existing shared `header` (`id=11`) and `footer` (`id=9`) assignments.
- Added scoped, token-aware `site.css` rules for the first-section image block; CSS mode-awareness remained `pass`.
- Public HTML smoke check found the dashboard image and did not find the old `One system, three working layers` hero card heading.

Current design alignment estimate after the hero image pass: **60/100**.

The left hero was polished in a fifth July 8 pass:

- Updated the native hero block title from `Build, manage, publish.` to `WebBlocks`.
- Added scoped `site.css` rules that remove the first hero's card chrome, enlarge the hero title, render `Build, manage, publish.` as the teal second line, style the eyebrow as a compact pill, and make the first action row closer to the reference.
- Kept the dashboard bitmap, shared header (`id=11`), shared footer (`id=9`), and page-owned main slot structure intact.
- CSS mode-awareness remained `pass`.
- Public HTML smoke check found the `WebBlocks` H1, preserved the hero body copy and dashboard image, and did not find the old `Build, manage, publish.` H1.

Current design alignment estimate after the left hero polish: **68/100**.

The lower homepage sections were tightened in a sixth July 8 pass:

- Added scoped `site.css` rules that turn the `Three entrances, one ecosystem.` section into a more cohesive bordered product band.
- Reduced the second-section heading scale, tightened card gaps, gave card icons more deliberate dashboard-style containers, and made card footer actions read more like inline product links.
- Made the `CMS quality` feature grid denser on desktop by using six compact columns, while preserving three-column/tablet and one-column/mobile fallbacks.
- CSS mode-awareness remained `pass`.
- Public HTML smoke check found the expected second/third section copy, preserved the dashboard image, and did not find the old hero card heading.

Current design alignment estimate after the lower-section polish: **73/100**.

The bottom CTA/footer area was de-emphasized in a seventh July 8 pass:

- Added scoped `site.css` rules that turn footer primary button links into quieter text-style links.
- Changed the footer layout into a calmer brand-copy plus navigation-link grid instead of a row of repeated blue CTA buttons.
- Softened the final CTA action row so the buttons no longer compete visually with the CTA card and footer.
- CSS mode-awareness remained `pass`.
- Public HTML smoke check confirmed the footer links and final CTA links still render.

Current design alignment estimate after the footer/CTA de-emphasis: **76/100**.

The docs/link model was corrected in an eighth July 8 pass:

- Updated the primary navigation `WebBlocks UI` item from the retired `https://ui.webblocksui.com` static docs site to `https://ui.docs.webblocksui.com`.
- Replaced visible `Platform Guides` homepage copy with `CMS Docs`.
- Updated the docs card action from relative `/docs` to `https://cms.webblocksui.com/docs`.
- Updated the dashboard panel bitmap to `docs/design-assets/webblocksui-hero-dashboard-panel-docs-2026-07-08.png` and uploaded it as Media `id=5`.
- Replaced the hero image block media with Media `id=5`, so the visible dashboard card now says `CMS Docs` and points to `cms.webblocksui.com/docs`.
- Updated shared footer buttons so UI Docs points to `https://ui.docs.webblocksui.com` and the former guides button points to `https://cms.webblocksui.com/docs`.
- Public HTML smoke check found zero `https://ui.webblocksui.com` links, zero relative `/docs` hrefs, six `https://ui.docs.webblocksui.com` links, two `https://cms.webblocksui.com/docs` links, and zero visible `Platform Guides` text.
- After the CMS 1.34.12 Internal Content API fix was applied to the site, the hero image block alt text was updated through `/webadmin/api/blocks/{block}` so raw HTML also has zero `Platform Guides` occurrences.

Current design alignment estimate after the docs/link cleanup: **77/100**.

The header brand was corrected in a ninth July 8 pass:

- Updated shared header brand block `id=9783` from CMS-specific branding to the ecosystem-level title `WebBlocks`.
- Removed the CMS logo media from the shared `navbar-brand` block and cleared the subtitle/slogan.
- Updated the brand aria label to `WebBlocks home`.
- Added scoped, mode-aware `site.css` wordmark rules so the header title reads as a refined ecosystem brand instead of plain default blue text.
- CSS mode-awareness remained `pass`.
- Public HTML smoke check confirmed the header renders a single `WebBlocks` brand span with no logo image and no `A modern block-based CMS` slogan.

Current design alignment estimate after the ecosystem brand correction: **80/100**.

## Why The Live Result Is Only Partially Aligned

The mockup relies on a richer first-viewport composition than the current native public block renderers produce by default.

Remaining gaps:

- Hero split layout now carries the mockup's dashboard panel as a bitmap asset, and the left hero is closer to the reference, but the first viewport still depends on CSS overrides around native block markup.
- Lower sections are more compact and product-like, and the footer/CTA no longer creates a stack of competing primary buttons. The "built with blocks" area still reads more like native CMS content than the reference mockup's bespoke landing-page composition.
- `card_header` with `plain_text` is usable but does not create strong product-card headings by itself.
- `button_link` variants were accepted by the API, but public rendering did not preserve the expected outline visual in the first implementation.
- Nested `link-list` content inside a composed card did not render as expected in preview, so the ecosystem map had to be simplified to plain text.
- Site CSS is intentionally conservative and token-aware; it improves spacing and surfaces, but it does not yet create the mockup's advanced dashboard composition.

## CMS/API Notes From Implementation

- The `button` block type was not usable through the discovered content API plan validation, even though `hero` and `cta` historically support button children in renderer code. Use `button_link` for API-created visible actions unless the API contract is updated.
- `link-list` with nested `link-list-item` passed plan normalization, but the preview did not render visible link-list items in the composed card context. This needs investigation before relying on link-list for dashboard-style panels.
- `rich-text` safely stripped heading markup in the card body output, so card titles should use native block structure rather than relying on HTML headings in rich text.
- The current icon catalog available through API discovery was small: `files`, `list`, `newspaper`, `pen-tool`, `type`, `file-text`, and `code`.

## Desired Next Iteration

Bring the live page closer to the mockup by improving the native block output and/or adding narrow CMS capabilities where product-appropriate.

Priority ideas:

1. Add or fix a native product card composition that supports clear title, eyebrow/badge, description, icon, and action without relying on `rich-text` headings.
2. Investigate why `link-list` children did not render in the staged homepage preview and add a regression test if this is a renderer/API persistence gap.
3. Align API support for `hero`/`cta` actions with the documented child-button renderer contract, or document `button_link` as the preferred API-created action pattern.
4. Add a homepage-specific native section style or block setting for a dashboard/product-map panel if the design should be reusable beyond this site.
5. Refine scoped `site.css` after renderer gaps are solved, keeping it token-first and mode-aware.

## Design Direction To Preserve

- Product-system homepage, not marketing fluff.
- Modern but operational: dashboard-grade hierarchy, crisp cards, compact status/details, strong first viewport.
- Three clear destinations: CMS, UI Docs, CMS Docs.
- CMS quality should be visible as product capabilities: native blocks, staged updates, multisite, shared slots, media library, system updates.
- The page should still be realistically buildable in WebBlocks CMS and should avoid Trusted HTML.
