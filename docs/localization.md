# Localization

WebBlocks CMS stores localized content in translation tables for pages and blocks.

## Model

- `locales` defines the available locales.
- `page_translations` owns localized page identity such as title, slug, and path.
- block translation tables own localized block content.

## URL Behavior

- The default locale is prefixless.
- Non-default locales use `/{locale}` prefixes.
- Public URLs are generated only when the requested translation exists for the current site and locale.

## Editorial Rules

- The default locale should always have the canonical translation row.
- Shared block settings remain shared across locales.
- User-facing translated copy should live in translation rows, not shared settings.

## Related Docs

- [Multisite](multisite.md)
- [Getting Started](getting-started.md)
- [Core Concepts](core-concepts.md)
