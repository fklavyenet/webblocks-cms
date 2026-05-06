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

## Public Search Routes

- default locale: `/search?q=term`
- non-default locale: `/{locale}/search?q=term`

The current host resolves the site, and the route prefix resolves the locale using the same public routing model as pages.

## Search Form Block

Use the first-class `Search Form` block to place a search form in any public slot.

- translated fields: accessible label, placeholder, button label
- shared fields: button visibility and button variant
- default target: the current site's resolved search route
- current `q` value is preserved when the block renders on the search page

## Admin Screen

Super admins can review search status under `Admin -> Maintenance -> Search`.

The screen shows:

- total indexed rows
- rows by site
- rows by locale
- last indexed timestamp
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

## Portability Boundary

`public_search_index` is derived runtime data.

- it may exist in environment backups and restores because those operate on the whole database
- it is not required site content for export/import portability
- export/import workflows should rebuild search rows after content transfer when needed

## Safety

Search V1 does not require destructive database reset commands.

- destructive command guards remain in place
- rebuilds clear and recreate only derived search rows in the requested scope
