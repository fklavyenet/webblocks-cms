---
guide: true
guide_slug: add-a-language
guide_series: J
guide_order: 44
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/add-a-language
cms_layout: docs
cms_title: Add A Language To A Site
card_description: Two steps — register the locale, then enable it on the site that needs it.
card_thumbnail: 03-site-locales.png
---

# Add A Language To A Site

**Goal:** Make a second language available so pages can be translated into it.
**Time:** 3 minutes
**You need:** Super admin access

## Steps

1. Select **System → Locales**. This is the installation-wide list of languages that exist at all.

> **Screenshot** `01-locales.png` — The Locales list.
> Alt: Locales screen listing the languages registered in the installation.

2. If your language is missing, select **Add Locale** and register it.

> **Screenshot** `02-add-locale.png` — The Add Locale form.
> Alt: Add locale form for registering a new language in the installation.

3. Now enable it for the site. Open **Sites**, edit the site, and go to the **Locales** tab.
4. Tick the languages this site publishes in, then select **Save Changes**.

> **Screenshot** `03-site-locales.png` — The site's Locales tab with a second language ticked.
> Alt: Site settings locales tab showing enabled languages for the site.

## Two Levels, On Purpose

**Installation locales** are the languages that exist. **Site locales** are the ones a particular site actually publishes in.

A multisite installation might know about five languages while the German subsidiary's site publishes in one. Registering a locale does not put it on any site; that is the second step, and it is deliberate.

## Notes

- **Each site must keep at least one locale enabled**, and the system default is always on. The CMS will not let you strip a site down to nothing.
- Adding a language does not translate anything. Existing pages keep their current language and gain the *option* of a translation — see [Translate A Page](/guides/translate-a-page).
- The site's first locale is its default. Visitors who ask for nothing in particular get that one.
- This is not the same as the admin interface language, which each person sets for themselves in [their profile](/guides/your-profile-and-language).

**Next:** [Translate A Page](/guides/translate-a-page)
