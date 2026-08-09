---
guide: true
guide_slug: site-search
guide_series: I
guide_order: 42
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/site-search
cms_layout: docs
cms_title: Site Search
card_description: Put a search box on the site and keep the index honest.
card_thumbnail: 01-search-block-form.png
---

# Site Search

**Goal:** Give visitors a way to find pages, and understand what search actually looks at.
**Time:** 3 minutes
**You need:** A site with a few published pages

## Steps

1. In a slot — usually a header Shared Slot — select **Add Block**, then choose **Search Form**.
2. Fill in the **Accessible Label** (read by screen readers), the **Placeholder** visitors see, and the **Button Label**.
3. Choose a **Button Variant**, and turn **Show submit button** off if you want a bare field.
4. Select **Save New Block**.

> **Screenshot** `01-search-block-form.png` — The Search Form block settings.
> Alt: Search form block showing accessible label, placeholder, button label, and variant.

5. Check the index under **System → Search**. It shows what is currently searchable.

> **Screenshot** `02-search-index.png` — The search index screen.
> Alt: System search screen showing the public search index status.

## Example

```text
Accessible Label:   Search the journal
Placeholder:        Search notes and projects
Button Label:       Search
Show submit button: on
```

## What Search Can Find

Search runs against an index of **published** pages. Drafts, pages in review, and archived pages are not in it — which is correct, because a visitor cannot open them anyway.

The index updates as pages are published. If something published is missing, that is the moment to look at **System → Search** rather than at the search box.

## Notes

- **The header is the right home for search.** Putting it in a Shared Slot means one search box on every page instead of one per page — see [Build A Header As A Shared Slot](/guides/header-shared-slot).
- The **Header Actions** block already carries a search trigger. If your header uses it, you do not need a separate Search Form block as well.
- The **Accessible Label** is not decoration. A search field with no label is a mystery box to anyone using a screen reader.
- Search covers page content, not media files or documents. A PDF's contents are not searchable; the page that offers it is.

**Next:** [SEO Fields, Social Image, And Favicon](/guides/seo-and-social)
