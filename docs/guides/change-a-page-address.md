---
guide: true
guide_slug: change-a-page-address
guide_series: B
guide_order: 10
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/change-a-page-address
cms_layout: docs
cms_title: Change A Page Address
card_description: Rename the slug and path of a page, and set its SEO and social fields.
card_thumbnail: 02-translation-routing.png
---

# Change A Page Address

**Goal:** Change where a page lives, and fill in what search engines and social networks show.
**Time:** 3 minutes
**You need:** A page you can edit

## Steps

1. Open the page from **Pages** and select **Edit page**.
2. On the **Settings** tab, change the **Slug** and select **Save Changes**. This is the quick edit for the default language.

> **Screenshot** `01-settings-slug.png` — The Settings tab with the Title and Slug fields.
> Alt: Page settings tab showing the title and slug fields.

3. For the full address, scroll to **Translations** and select **Edit translation** on the row you want.
4. Under **Routing**, set the **Slug** and the **Path**. The path is the whole address, so a page can live under a section: `/guides/change-a-page-address`.

> **Screenshot** `02-translation-routing.png` — The translation editor showing Routing, Listing, and SEO.
> Alt: Translation editor with routing fields, list excerpt, and SEO fields.

5. While you are here, fill in **SEO title** and **SEO description**, and pick a **social image** if the page will be shared.
6. Select **Save Changes**, then use **Open** to check the new address resolves.

## Example

```text
Routing
  Name: Change A Page Address
  Slug: change-a-page-address
  Path: /guides/change-a-page-address

SEO
  SEO title:       Change a page address — WebBlocks CMS Guides
  SEO description: Rename a page's slug and path, and set the SEO and social
                   fields that search engines and social networks show.
```

## Notes

- Do this early. Once a page is published and linked to, changing its address breaks those links — the CMS will not leave a redirect behind for you.
- Slug and path are per language. Each translation has its own address, which is what lets `/about` and `/hakkimizda` be the same page.
- The SEO description is what shows under the title in search results. Write it for a person deciding whether to click, not for a keyword counter.
- Leaving SEO title empty is fine; the page title is used instead.

**Next:** [Rich Text: Formatting, Lists, And Links](/guides/rich-text)
