---
guide: true
guide_slug: create-a-page
guide_series: B
guide_order: 5
cms_site: docs-site
cms_locale: en
cms_path: /guides/create-a-page
cms_layout: docs
cms_title: Create A Page
card_description: Add a new page to a site and save it as a draft.
card_thumbnail: 00-card.png
---

# Create A Page

**Goal:** Create a new page and have it ready for content.
**Time:** 2 minutes
**You need:** Editor access to a site

## Steps

1. Select **Pages** in the sidebar.
2. Select **New Page** at the top right.

> **Screenshot** `01-pages-list.png` — Pages list with the New Page button visible.
> Alt: Pages list in the WebBlocks CMS admin panel.

3. Fill in the form:
   - **Site** — the site this page belongs to. It cannot be changed from this form later.
   - **Title** — what the page is called. For this example: `Studio Notes`.
   - **Slug** — the last part of the address. Leave it empty to have it generated from the title, or type `notes`.
   - **Page Layout** — the frame the page renders in, which decides whether it has a header, sidebar, and footer. Leave the default unless you were told otherwise.

> **Screenshot** `02-new-page-form.png` — The Add Page form filled in with the example values.
> Alt: New page form with title, slug, and page layout fields.

4. Select **Create**.

You land on the page's edit screen, and a message confirms the page was saved as a draft.

> **Screenshot** `03-page-edit.png` — The page edit screen right after creation, showing the draft status.
> Alt: Page edit screen showing a newly created draft page.

## What You Just Got

The page is a draft, so it is not visible to visitors yet. The layout you chose already created the page's slots — the named areas the layout renders, such as the main content area. You do not have to set those up; you fill them.

The page is empty on purpose. Adding content is the next guide.

## Notes

- The slug becomes part of the public address, so keep it short, lowercase, and without spaces. `notes` reads better than `Studio Notes 2026`.
- The title is not the page's heading. It names the page in the admin panel and feeds the browser tab. You still add a visible heading to the page itself.
- Changing the slug later changes the public address. Do it early, before anyone links to the page.

**Next:** [Add A Heading And A Paragraph](/guides/add-heading-and-text)
