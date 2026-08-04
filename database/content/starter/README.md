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
- `media` names an image file shipped beside the blueprint under `media/`. It is
  imported into the site's own Media Library once and bound to the block by
  media id — the only way native blocks take an image. Bare file names only; a
  path segment is ignored. An image that cannot be imported (missing file,
  read-only disk) leaves the block without media rather than failing anything.

## Shipped Artwork

`media/logo-mark.png` is `public/cms/brand/logo-mark.svg` rasterised at 96x96.

The size is part of the asset because the `image` block renders at the file's
own pixel size — it has no display-size setting — so the shipped file is what
decides how large the mark appears. 96px sits next to the 3rem the admin
sidebar brand uses. Do not put the mark in a CSS-sized media slot such as the
hero's split layout: that stretches it to the column width, which on a desktop
viewport meant a 490px logo dominating the page.

The mark sits beside the heading rather than above it because `cluster` is the
horizontal primitive — `display: flex` with `align-items` — holding the `image`
and the `content_header`. It needs `wrap: nowrap`: the header is a block-level
flex item, so with the default wrapping it claims the whole row and drops the
logo onto a line of its own. `hero` cannot express this shape at all; its media
renders as a background, or beside the copy on the right in the split layout.

It is a raster of the canonical SVG, not a redrawn copy — regenerate it from
that file whenever the brand mark changes, rather than editing the PNG. PNG
rather than SVG keeps the shipped asset off the SVG path the media pipeline
disables by default (`webblocks-cms.media.allow_svg_uploads`).

Starter artwork is deliberately not hot-linked from a CDN. A remote URL in
content would make every visitor of the customer's public site issue a
third-party request, which `docs/ai-page-building-guide.md` rules out, and it
would couple their home page to another host's uptime. Bundling the file means
the install is deterministic and works with no outbound network at all.

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
