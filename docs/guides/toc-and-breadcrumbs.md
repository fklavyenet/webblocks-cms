---
guide: true
guide_slug: toc-and-breadcrumbs
guide_series: F
guide_order: 31
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/toc-and-breadcrumbs
cms_layout: docs
cms_title: Table Of Contents And Breadcrumbs
card_description: Two small blocks that help readers find their place in a long page.
card_thumbnail: 04-rendered.png
---

# Table Of Contents And Breadcrumbs

**Goal:** Give a long page an "On this page" list, and show visitors where they are.
**Time:** 4 minutes
**You need:** A page with several headings

## Steps

1. **Anchor your headings first.** A table of contents lists Header blocks that have an **Anchor ID** — headings without one are invisible to it.
2. Edit each Header block you want listed and fill in **Anchor ID**. Use lowercase words joined by hyphens: `how-we-run-a-project`.

> **Screenshot** `01-header-anchor.png` — A Header block with its Anchor ID filled in.
> Alt: Header block form showing the text, level, and anchor ID fields.

3. Select **Add Block**, then choose **TOC**. Set the **TOC Title** — `On this page` is the convention.

> **Screenshot** `02-toc-form.png` — The TOC block form.
> Alt: TOC block form showing the optional title field.

4. Place it near the top of the page, above the content it describes.
5. Select **Add Block**, then choose **Breadcrumb**. Set the **Home label**, and decide whether the **Current page item** is included.

> **Screenshot** `03-breadcrumb-form.png` — The Breadcrumb block form.
> Alt: Breadcrumb block form showing the home label and current page item settings.

6. Save, then check the result.

> **Screenshot** `04-rendered.png` — The table of contents and breadcrumb on the public page.
> Alt: Public page showing an on-this-page list of anchored headings above a breadcrumb trail.

## Example

```text
Header    Text: How we run a project   Level: h2   Anchor ID: how-we-run-a-project
Header    Text: What you get           Level: h2   Anchor ID: what-you-get
Header    Text: Studio numbers         Level: h2   Anchor ID: studio-numbers

TOC         Title: On this page
Breadcrumb  Home label: Home
```

## The TOC Is Built, Not Written

You never type the list. The TOC block reads the anchored Header blocks on the same page and builds the list from them, in page order.

That means it never goes stale: rename a heading and the entry follows; add a section and it appears; remove the anchor and it drops out. It also means an empty TOC is not a broken block — it is a page with no anchored headings.

## Notes

- **An anchor is also a link target.** Once a heading has one, you can link straight to that section from anywhere: `/guides/toc-and-breadcrumbs#the-toc-is-built-not-written`.
- Keep anchors stable. Changing one breaks every link anyone has saved to that section — the same rule as a page slug.
- **A TOC on a short page is noise.** If a reader can see every heading without scrolling, the list is repeating what is already visible.
- Breadcrumbs are derived from the page's path, so they reflect where the page actually lives. If the trail looks wrong, the [page address](/guides/change-a-page-address) is what needs fixing.
- Headings nested inside cards and other blocks are not listed. The TOC is a map of the page's sections, not of every heading on it.
