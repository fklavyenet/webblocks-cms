# Starter Content Blueprints

Package-owned content applied once, to the empty home page a fresh install
provisions. It is not demo data and not a migration: `StarterContentInstaller`
writes it only into a page that has no blocks at all, so it can never overwrite
a live site's content or reappear after an update.

## Files

- `home.json` — the shipped default, used for every locale without its own file
- `home.{locale}.json` — optional per-locale copy, for example `home.de.json`

Lookup order for a default locale of `de-ch`: `home.de-ch.json`, `home.de.json`,
`home.json`. The first readable file wins.

## Schema

```json
{
  "schema": "webblocks.cms.starter-content.v1",
  "slots": {
    "main": [
      {
        "type": "section",
        "settings": { "spacing": "lg" },
        "translations": { "title": "..." },
        "children": []
      }
    ]
  }
}
```

- `slots` is keyed by slot slug. A slot the page does not have is skipped.
- `type` is a block type slug. It must be a published block type; an unknown one
  skips that block and its children rather than failing the install.
- `settings` are the block's own settings, exactly as the block editor stores
  them. `settings.url` also fills the block's `url` column, and `settings.variant`
  is used when no explicit `variant` is set.
- `translations` are flat, not locale-keyed: the copy is written for the
  install's default locale. Unsupported fields for a block type are ignored.
- `children` nest blocks; order in the array is the rendered order.

The shipped blueprint deliberately uses no `settings.icon_slug`: a fresh install's
icon catalog holds only the navigation-context fallback icons, and a content icon
renders as nothing until `php artisan icons:sync-webblocks-ui` has run. Use icons
in a custom blueprint only for installs that sync the catalog.

Keep the vocabulary aligned with `docs/ai-page-building-guide.md`, which is the
authoritative guide for composing WebBlocks CMS pages.

## Overriding

Point `WEBBLOCKS_CMS_STARTER_CONTENT_PATH` at your own directory to ship a
different starter page, or set `WEBBLOCKS_CMS_STARTER_CONTENT=false` to install
with an empty home page.
