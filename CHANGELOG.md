# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

## 1.32.244

- Bumped CMS to `1.32.244`.
- Add a searchable standard locale picker to the Add Locale admin form and expose the same locale option catalog through the Internal Content API.
- Broaden locale code validation to accept route-safe BCP 47 style tags such as `zh-hant-hk` while preserving custom locale support for operator cases.

## 1.32.243

- Bumped CMS to `1.32.243`.
- Fix the Site edit screen so site-level Branding media pickers render outside a block editor context instead of raising a 500 error.
- Preserve Contact Form submit and success copy from Internal Content API content plans, and use German default public form labels when rendering German locale pages.
- Add Internal Content API locale create/update endpoints with `site-settings.write` capability checks so migration tools can correct install locales before applying localized content.

## 1.32.242

- Bumped CMS to `1.32.242`.
- Preserve authored Gallery media order when Internal Content API plans assign `gallery_items` or `gallery_media_ids`.

## 1.32.241

- Bumped CMS to `1.32.241` and pinned WebBlocks UI to `v2.7.17`.
- Show a non-dismissible System Updates progress modal with the version path and shared WebBlocks UI spinner when an operator starts or continues an update.

## 1.32.240

- Bumped CMS to `1.32.240`.
- Center the Gallery lightbox `Viewer title` in the viewer header.

## 1.32.239

- Bumped CMS to `1.32.239`.
- Add a Gallery `Viewer title` setting so lightbox modals can show the current image collection name without restoring legacy public Gallery headings.
- Stop public Gallery rendering from exposing technical import notes such as `Imported from ... during ... migration` as item captions, overlay meta, or lightbox metadata.

## 1.32.238

- Bumped CMS to `1.32.238`.
- Make sibling alternating media/text Grid blocks share one parent sequence so reordering adjacent profile grids no longer preserves editor-selected per-grid left/right placement.

## 1.32.237

- Bumped CMS to `1.32.237`.
- Make alternating media/text Grid ordering work when the Grid directly contains a Slider and a text Section, matching existing editorial page structures.

## 1.32.236

- Bumped CMS to `1.32.236`.
- Keep alternating media/text Grid blocks on the normal `wb-grid` wrapper while reordering direct Section columns by detected media/text content.

## 1.32.235

- Bumped CMS to `1.32.235`.
- Add a Grid setting that renders direct Section children as alternating media/text rows, so editors can reorder sections without manually maintaining left/right slider and copy placement.

## 1.32.234

- Bumped CMS to `1.32.234`.
- Add mode-awareness analysis to canonical site CSS API responses so migration and new-site tools can catch hard-coded light palette regressions before marking a site complete.
- Pin WebBlocks UI `v2.7.16` and add native Navbar Navigation active indicator and active matching settings so current-page menu state can be made visible without site-specific CSS.

## 1.32.233

- Bumped CMS to `1.32.233`.
- Allow existing `header-actions` blocks to update search, mode, and accent toggle settings through the Internal Content API.
