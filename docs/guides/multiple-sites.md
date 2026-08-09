---
guide: true
guide_slug: multiple-sites
guide_series: J
guide_order: 46
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/multiple-sites
cms_layout: docs
cms_title: Manage More Than One Site
card_description: One installation, several sites — what is shared and what is not.
card_thumbnail: 01-sites.png
---

# Manage More Than One Site

**Goal:** Work confidently in an installation that runs more than one site.
**Time:** 4 minutes
**You need:** Access to at least one site; super admin to create them

## Steps

1. Select **Sites**. Each row is a separate public site with its own domain, pages, and settings.

> **Screenshot** `01-sites.png` — The Sites list.
> Alt: Sites screen listing the sites in the installation with their domains.

2. Edit a site to reach its settings, grouped into tabs: **Site**, **Locales**, **Branding**, **SEO Defaults**, **Head Code**, **Contact**, **Variables**, **Assets**, **Appearance**.

> **Screenshot** `02-site-settings.png` — Site settings with its tabs.
> Alt: Site settings screen showing the site, locales, branding, and other tabs.

3. Use **Manage Domains** for the addresses this site answers on, and **Open Pages** to jump straight to its content.

## What Is Per Site And What Is Shared

| Per site | Shared across the installation |
| --- | --- |
| Pages, blocks, navigation menus | The Media Library |
| Shared Slots | User accounts |
| Domains, locales, branding, SEO defaults | The list of registered locales |
| Site variables, contact recipient | Block types and page layouts |

The Media Library being shared is the one that surprises people. An image uploaded while working on one site is available to all of them — convenient, and worth remembering before you upload something confidential.

## Notes

- **Check which site you are in before you create anything.** Most screens work on one selected site at a time, and a page created on the wrong site has to be [moved](/guides/duplicate-move-archive).
- Access is per site. An editor assigned to one site cannot see another's pages at all — see [Roles](/guides/roles-and-permissions).
- Shared Slots do not cross sites. Each site needs its own header, even if they look identical.
- Duplicating a page into another site is the fast way to start a second site from a first — and the copy always arrives as a draft.

**Next:** [Site Variables](/guides/site-variables)
