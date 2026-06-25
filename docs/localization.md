---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/localization
cms_title: Localization
cms_layout: docs
cms_source_id: webblocks-cms:docs/localization.md
---

# Localization

WebBlocks CMS stores localized content in translation tables for pages and blocks.

## Model

- `locales` defines the available locales.
- `page_translations` owns localized page identity such as title, slug, path, and page-level SEO overrides.
- block translation tables own localized block content.

## URL Behavior

- The default locale is prefixless.
- Non-default locales use `/{locale}` prefixes.
- Public URLs are generated only when the requested translation exists for the current site and locale.

## Editorial Rules

- The default locale should always have the canonical translation row.
- Shared block settings remain shared across locales.
- User-facing translated copy should live in translation rows, not shared settings.
- Page SEO title, description, keywords, and Open Graph overrides are locale-aware editorial content and belong on the translation row for the locale that owns them.

## Related Docs

- [Multisite](multisite.md)
- [Getting Started](getting-started.md)
- [Core Concepts](core-concepts.md)
