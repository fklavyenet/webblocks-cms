---
guide: true
guide_slug: translate-a-page
guide_series: J
guide_order: 45
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/translate-a-page
cms_layout: docs
cms_title: Translate A Page
card_description: Give a page a second language, with its own address and its own SEO.
card_thumbnail: 02-add-translation.png
---

# Translate A Page

**Goal:** Publish the same page in a second language.
**Time:** 5 minutes
**You need:** A page, and a second language enabled on the site

## Steps

1. Open the page and scroll to **Translations**. Every enabled language has a row, and the ones without content are marked **Missing**.

> **Screenshot** `01-translations.png` — The Translations card showing each language and its state.
> Alt: Page translations card listing locales with their slug, path, and status.

2. Select **Add translation** on the language you want.
3. Under **Routing**, set the **Name**, **Slug**, and **Path** for that language. This is a separate address, not a variant of the first one.
4. Fill in the **SEO** fields for this language. They override the site defaults for this locale only.

> **Screenshot** `02-add-translation.png` — The Add Translation form.
> Alt: Add translation form showing routing fields for the German version of a page.

5. Select **Save**.
6. Translate the **block content** separately. Open the page's slot, switch the editing locale, and translate each block's text.

## What Is Shared And What Is Not

**Per language:** the page name, slug, path, SEO and Open Graph fields, and the text inside blocks.

**Shared across languages:** the block structure and order, the layout, the settings on each block, and the selected media.

That split is the whole point. You translate words, not layouts — and when someone reorders the blocks, every language follows.

## Notes

- **Each language needs a unique path.** `/notes` and `/de/notizen` are two addresses for one page; two languages cannot share one.
- A missing translation is not a broken page. Visitors get the language that exists; only the languages you fill in become reachable.
- Translate the **SEO description** too. A German page with an English description in search results is the most common half-done translation.
- Media is shared, but the **alt text and caption are per language** — so an image needs its description translated even though the file does not change.

**Next:** [Manage More Than One Site](/guides/multiple-sites)
