# Search

## Overview

Search V1 is a core CMS feature that provides database-backed public search for published page content.

- Search indexes only published public pages.
- Search rows are scoped by site and locale.
- Search uses a derived `public_search_index` table.
- Search does not require external services such as Meilisearch, Algolia, Elasticsearch, or Scout.

## What Gets Indexed

- page translation title, slug, and public path metadata
- page-owned published block trees in enabled slots
- compatible Shared Slot content as consumed by the public page

Search does not index:

- draft, in-review, archived, or hidden internal pages
- Shared Slot source pages as standalone search results
- disabled slots
- cross-site, inactive, shell-incompatible, or slot-incompatible Shared Slot content
- admin-only labels, block type names, CSS classes, or raw settings blobs as search content
- page SEO descriptions, keywords, and Open Graph metadata as public body-search content

Search may still use the page translation title because that is part of the public page identity, but page SEO overrides are otherwise treated as head metadata rather than searchable body content in Search V1.

## Public Search Routes

- default locale: `/search?q=term`
- non-default locale: `/{locale}/search?q=term`
- enhanced JSON endpoint: `/search.json?q=term`
- enhanced localized JSON endpoint: `/{locale}/search.json?q=term`

The current host resolves the site, and the route prefix resolves the locale using the same public routing model as pages.

## Public Search UX

The primary public UX is an enhanced search modal opened from the `Header Actions` search trigger when JavaScript is available.

- the trigger remains a normal link to the current locale's `/search` route, so direct links, no-JS usage, and accessibility fallback still work
- modal results are powered by `public_search_index`
- results remain scoped to the current resolved site and locale
- the modal description can name the resolved site label so users can see which site is being searched
- the fallback `/search` page remains the canonical non-JS and direct-link experience

The public search modal uses Site Identity only:

- site label precedence is `display_name`, then `seo_title`, then `name`
- Project Identity from `System -> Settings` is admin-only and does not affect public search copy or scope

The JSON endpoint returns safe structured payload data for the modal:

- normalized `query`
- `count`
- `minimum_length`
- `results` with `title`, `url`, and `excerpt`
- `no_results` message when applicable
- `minimum_query_length` message when applicable

The endpoint does not expose admin data, draft content, or raw unsafe HTML.

## Search Form Block

Use the first-class `Search Form` block to place a search form in any public slot.

- translated fields: accessible label, placeholder, button label
- shared fields: button visibility and button variant
- default target: the current site's resolved search route
- current `q` value is preserved when the block renders on the search page
- it continues to submit to `/search` as a normal GET form and is not forced into the modal flow

## Admin Screen

Super admins can review search status under `Admin -> Maintenance -> Search Rebuild`.

The screen shows:

- total indexed rows and last indexed timestamp
- coverage by site
- coverage by locale
- rebuild action

## Rebuild Command

Use the non-destructive rebuild command when you need to recreate derived search rows:

```bash
ddev artisan search:rebuild
ddev artisan search:rebuild --site=default
ddev artisan search:rebuild --locale=tr
ddev artisan search:rebuild --page=123
```

The command rebuilds only the requested search scope and does not modify CMS content.

If the search table is missing on the current install, run `ddev artisan migrate` first so the derived index can be created.

## Portability Boundary

`public_search_index` is derived runtime data.

- it may exist in environment backups and restores because those operate on the whole database
- it is not required site content for export/import portability
- export/import workflows should rebuild search rows after content transfer when needed

## Safety

Search V1 does not require destructive database reset commands.

- destructive command guards remain in place
- rebuilds clear and recreate only derived search rows in the requested scope
