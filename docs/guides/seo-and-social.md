---
guide: true
guide_slug: seo-and-social
guide_series: I
guide_order: 43
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/seo-and-social
cms_layout: docs
cms_title: SEO Fields, Social Image, And Favicon
card_description: What search results and shared links show, and where to set it.
card_thumbnail: 01-page-seo.png
---

# SEO Fields, Social Image, And Favicon

**Goal:** Control how a page looks in search results and when someone shares it.
**Time:** 4 minutes
**You need:** A page, and an image for sharing

## Per Page

1. Open the page, scroll to **Translations**, and select **Edit translation**.
2. Under **SEO**, fill in **SEO title** and **SEO description**.
3. Set **OG title** and **OG description** if the shared version should read differently from the search result, and pick a **social image**.

> **Screenshot** `01-page-seo.png` — The translation editor's SEO section.
> Alt: Translation editor showing SEO title, description, keywords, and social image fields.

4. Select **Save Changes**.

## Per Site

1. Open **Sites** and edit the site.
2. Set the site-level **SEO title** and **description**, the **favicon**, and the default **social image**. These are the fallbacks every page inherits.

> **Screenshot** `02-sites.png` — The Sites screen.
> Alt: Sites screen listing the sites in the installation.

## Example

```text
SEO title:       Change a page address — WebBlocks CMS Guides
SEO description: Rename a page's slug and path, and set the SEO and social
                 fields that search engines and social networks show.
OG title:        (empty — the SEO title is fine)
Social image:    guides-og-change-address.png
```

## Which Field Shows Where

- **SEO title** — the clickable line in a search result. Front-load the words that matter; the tail gets cut off.
- **SEO description** — the grey text under it. Not a ranking factor, entirely a "should I click" factor.
- **OG title / OG description** — what a chat app or social network shows. Only fill these in when the framing should differ.
- **Social image** — the picture in that preview. Wide, and readable at postage-stamp size.
- **Favicon** — the tab icon, set per site.

## Notes

- **Empty is safe.** A blank SEO title falls back to the page title, and a blank page-level social image falls back to the site's. Fill in what improves on the default, not every field.
- These fields are **per translation**. A page in two languages needs its description written twice — a translated page with an English description is a common and avoidable miss.
- Write the description for a person. Keyword lists read as spam to both readers and search engines.
- Check a shared link before a launch. The social image is the one field nobody notices until it is wrong in public.
