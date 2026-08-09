---
guide: true
guide_slug: duplicate-move-archive
guide_series: H
guide_order: 39
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/duplicate-move-archive
cms_layout: docs
cms_title: Duplicate, Move, And Archive Pages
card_description: Copy a page as a starting point, move it between sites, or take it down without deleting it.
card_thumbnail: 01-duplicate.png
---

# Duplicate, Move, And Archive Pages

**Goal:** Reuse a page you already built, relocate one, or retire one safely.
**Time:** 4 minutes
**You need:** A page, and super admin access for moving between sites

## Duplicate A Page

1. Open the page and select **Duplicate page**.
2. Pick the **Target site** — the same site, or another one you can reach.
3. Set **New page title** and **New page slug**. The panel on the left shows what carries over and what does not.
4. Select **Duplicate page**.

> **Screenshot** `01-duplicate.png` — The Duplicate Page screen with its summary and warnings.
> Alt: Duplicate page screen showing target site, new title and slug, and what carries over.

**What comes with the copy:** the blocks, the translations, and the layout.
**What does not:** the revision history, and any navigation links pointing at the original.
**What changes:** the copy always starts as a **draft**, whatever state the original was in.

## Move A Page To Another Site

1. Open the page and select **Move to another site**.
2. Read the summary — current public path, workflow, translations — then choose the destination.

> **Screenshot** `02-move-site.png` — The Move Page screen.
> Alt: Move page to another site screen showing the current path and workflow.

Moving is not copying: there is one page afterwards, at a new address, on a different site.

## Archive A Page

1. Open a published page's **Overview** tab.
2. Select **Archive**. The page stops being reachable, and everything about it is kept.

> **Screenshot** `03-archive.png` — Overview of a published page with Move Back to Draft and Archive.
> Alt: Page overview of a published page showing the move back to draft and archive actions.

## Notes

- **Duplicate is the honest way to make a template.** Build one page properly, duplicate it, replace the words.
- Every copied language needs a **unique path on the target site**. A collision is refused, not silently renamed — the same slug rule that applies everywhere.
- Navigation does not follow a duplicate or a move. Check your menus afterwards — see [Build A Navigation Menu](/guides/navigation-menus).
- **Archive when a page has served its purpose**, delete only when it should never have existed. Archived pages can come back; deleted ones need a revision or a backup.
- Moving between sites is a super admin action, because it crosses a boundary that editors are deliberately kept inside.
