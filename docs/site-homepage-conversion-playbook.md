---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/site-homepage-conversion-playbook
cms_title: Site Homepage Conversion Playbook
cms_layout: docs
cms_source_id: webblocks-cms:docs/site-homepage-conversion-playbook.md
---

# Site Homepage Conversion Playbook

This playbook captures field lessons from converting a design-driven homepage into WebBlocks CMS content through the Internal Content API. It is written for trusted AI/operator tools and human operators who need to turn a visual target into native CMS content without bypassing the CMS model.

The goal is not pixel-perfect copying at any cost. The goal is a CMS-maintainable page that uses native blocks, media, shared slots, navigation, and site assets deliberately, with clear safety checks before anything becomes public.

## Core Principle

Build the page as CMS content first, then use site CSS only to polish the native structure.

Do not start with CSS tricks, Trusted HTML, browser admin scraping, or guessed block handles. A good CMS conversion should leave behind content that an editor can understand and maintain.

## Start With Live Discovery

Begin with the installed site's live API, not with assumptions from another project:

```text
GET /webadmin/api
```

Follow the discovered links to:

- OpenAPI
- AI guide
- content contract
- examples
- sites
- locales
- page layouts
- block types
- pages
- blocks
- media
- navigation menus
- Shared Slots
- icon catalog
- site asset endpoints

Use only discovered handles, settings, icon slugs, paths, and capabilities. If the API does not expose a field or operation, treat it as unsupported until proven otherwise.

## Map The Design To CMS Concepts

Before creating content, translate the visual design into CMS-owned concepts:

| Design need | Preferred CMS concept |
|---|---|
| Page sections | `section` -> `container` |
| Responsive card rows | `grid` -> `card` -> `card_body` |
| Repeated feature points | `feature-grid` / `feature-item` or cards |
| Steps and badges | `columns` / `column_item` with native eyebrow/badge settings |
| Buttons | `button_link` inside `cluster` or CTA blocks |
| Images | Media Library records on `image` or media-backed blocks |
| Header/footer | Shared Slots |
| Navigation | Navigation menu endpoints |
| Site-wide polish | canonical `site.css` asset |

Use Trusted HTML only when a required public pattern cannot reasonably be expressed with native blocks, and report the missing native capability.

## Work Draft-first

For a new page, use `create_draft_page`.

For an existing published page, use the staged update workflow:

1. Read the source page.
2. Reuse an active staged draft if one exists.
3. Create a staged update only when needed.
4. Replace only page-owned slots on the staged page.
5. Preview the staged page.
6. Promote only after explicit approval and only with `content.publish`.

Do not publish a staged page directly. Follow `page._actions.promote`.

## Validate Renderability, Not Just Syntax

Treat validation as more than schema checking. A plan can be syntactically valid but visually empty.

Watch for:

- wrapper blocks without meaningful children
- text blocks without visible content
- button blocks without labels or URLs
- feature/card grids with the wrong child topology
- Shared Slot blocks still in draft
- link-list items that exist but render without titles or URLs

After apply, always fetch either the preview HTML or the public page HTML and inspect the rendered structure.

## Shared Slots Need Separate Thinking

Header and footer slots are often Shared Slot-backed. They are not ordinary page-owned content.

If a page slot reports:

```json
{
  "source_type": "shared_slot",
  "uses_page_owned_blocks": false
}
```

do not try to replace it through a page slot replace plan. The safe options are:

1. Preserve the existing Shared Slot.
2. Create a new Shared Slot and assign the page slot to it.
3. Update an existing Shared Slot only when the operator explicitly approves the global/shared impact.

Shared Slot blocks are draft by default. Public rendering requires explicit publication:

```text
POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks
```

Page publish, page-owned block publish, and staged page promote do not automatically publish Shared Slot block trees.

After creating Shared Slot blocks, re-read:

```text
GET /webadmin/api/shared-slots/{sharedSlot}
```

Do not rely only on the immediate create response. Some responses summarize only the top-level block.

## Patch Existing Blocks When Nested Create Is Incomplete

When creating complex Shared Slot block trees, verify nested links and buttons in rendered HTML. If link-list items or buttons are present but empty, patch the existing blocks with their native fields.

For `link-list-item`, patch:

```json
{
  "locale": "en",
  "translations": {
    "title": "Features"
  },
  "settings": {
    "url": "/#features"
  },
  "url": "/#features"
}
```

For `button_link`, patch:

```json
{
  "locale": "en",
  "translations": {
    "title": "Get started free"
  },
  "settings": {
    "url": "/login",
    "target": "_self"
  },
  "url": "/login"
}
```

The duplicated top-level `url` keeps older renderer/admin paths aligned with settings-backed URLs.

## Use Site CSS Carefully

Use the canonical site asset endpoint:

```text
GET /webadmin/api/sites/{site}/assets/css
PUT /webadmin/api/sites/{site}/assets/css
```

Always write with the returned `expected_checksum`.

Site CSS applies immediately. This can create half-applied states if the content change is still staged or if a Shared Slot assignment has not happened yet.

Rules for safer CSS:

- Scope homepage rules with `body.wb-page-home`.
- Scope page-specific rules with stable public body classes such as `body.wb-page-contact`.
- Keep shared header/footer rules backward-compatible unless the Shared Slot content has already been changed.
- Prefer WebBlocks UI/CMS theme tokens and inherited `wb-*` component behavior.
- Avoid broad selectors such as `footer .wb-card` unless the structure is intentionally shared.
- Re-read HTML and confirm the stylesheet URL changed to the new checksum, for example `site.css?v=...`.

## Visual QA Should Be Concrete

Do not rate a page from memory. Use concrete visual comparison.

A good loop is:

1. Put the design screenshot and current render side by side.
2. Identify named differences.
3. Fix one section or issue.
4. Re-render.
5. Repeat until the page is within the agreed acceptance threshold.

Common differences that matter:

- empty cards
- headings rendered outside intended cards
- missing icons or numbers
- image cards without real media
- CTA spacing and button hierarchy
- footer/header density
- horizontal overflow
- mobile stacking

## Promote And Publish With Caution

Before promotion:

- Re-read the staged page.
- Confirm `page._actions.promote.available` is true.
- Use the action body exposed by the API.
- Confirm managed slots match the intended scope.
- Preserve Shared Slot-backed slots unless the operator explicitly approved Shared Slot changes.

After promotion:

- Re-read the public source page.
- Fetch public `/` or the target path.
- Confirm the expected content appears.
- Confirm the staged draft state.
- Confirm Shared Slot assignment.
- Smoke nearby pages that may share assets, such as `/contact`.

If an apply call returns `500`, timeout, or connection failure, do not retry blindly. The operation may have partially applied.

Instead:

1. Re-read the source page.
2. Re-read the staged page.
3. Fetch public HTML.
4. Check whether page content changed.
5. Check whether cleanup/status update failed.
6. Record the inconsistency as a CMS defect or operational follow-up.

## Recommended Homepage Conversion Checklist

Discovery:

- `GET /webadmin/api`
- `GET /webadmin/api/openapi.json`
- `GET /webadmin/api/content-contract`
- `GET /webadmin/api/block-types`
- `GET /webadmin/api/icon-catalog?context=content`
- `GET /webadmin/api/pages/{sourcePage}`
- `GET /webadmin/api/shared-slots`
- `GET /webadmin/api/sites/{site}/assets/css`

Build:

- Map design sections to native block trees.
- Use media library records for images.
- Use native icons and badges only from discovered contracts.
- Keep CTAs as `button_link`.
- Keep navigation as navigation menu items.
- Keep footer/header as Shared Slots.

Validate:

- Run content plan validation.
- Review renderability summary.
- Fix empty wrapper/content/button warnings.
- Preview through `/webadmin/pages/{page}/preview`.

Apply:

- Apply only after approval.
- Publish Shared Slot blocks explicitly when needed.
- Assign page slots to approved Shared Slots.
- Write site CSS with checksum protection.

Verify:

- Fetch public HTML.
- Confirm stylesheet checksum.
- Confirm target copy and links.
- Check desktop visual layout.
- Check horizontal overflow.
- Smoke related pages.
- Record any API inconsistencies.

## CMS Product Improvements Suggested By Field Work

Homepage conversion work becomes easier and safer if CMS provides:

- Full OpenAPI request schemas for Shared Slot creation, Shared Slot block creation, page-slot Shared Slot assignment, and site asset writes.
- First-class staged Shared Slot workflows, or clearer documentation for create -> publish blocks -> assign.
- Renderability summaries for Shared Slot block creation and publication.
- More consistent preservation of nested link/button fields during Shared Slot block tree creation.
- Richer partial-apply diagnostics for `promote_staged_page_update` beyond the baseline `plan.apply` JSON error returned when transactional writes fail.
- A repair or cleanup action when a staged promote updates public content but leaves the staged update in draft.
- Preview tooling that can render a page with alternate Shared Slot assignments before public assignment.

## Field Example Summary

In the QuizTem homepage conversion:

- the main homepage was prepared through a staged update
- native CMS blocks replaced CSS-only icon and badge simulations
- a new footer Shared Slot was created and published
- nested footer links required explicit block patching
- site CSS needed homepage scoping to avoid affecting older live footer markup
- public checks showed the final homepage and footer live even though the promote API returned `500`, demonstrating why post-apply state verification is mandatory

That outcome is the central lesson: CMS content conversion is safest when every step is explicit, discoverable, native, and verified against the rendered page, not only against the API call that attempted it.
