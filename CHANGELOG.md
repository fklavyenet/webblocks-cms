# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

- Move Page details, duplicate, layout slot summary, and slot block delete modal copy onto structured admin locale keys.
- Move Blocks, Block Types, and System Plugins listing copy onto structured admin locale keys.
- Move Block Type contract modal catalog, storage, translation, renderer, and gap copy onto structured admin locale keys.
- Move Page Layout Slot form identity, wrapper markup, trusted HTML, and status copy onto structured admin locale keys.
- Move Media asset picker controls, filters, empty states, upload, and modal actions onto structured admin locale keys.
- Move Page Slot block picker modal, tabs, search, table, and empty-state copy onto structured admin locale keys.
- Move Contact Messages listing filters, table, row delete, and bulk delete copy onto structured admin locale keys.
- Move System Plugin detail lifecycle, capabilities, settings, health, and uninstall copy onto structured admin locale keys.
- Move Contact Message detail, notification, classification, technical detail, and delete modal copy onto structured admin locale keys.
- Move Media edit preview, usage, metadata, file details, and delete modal copy onto structured admin locale keys.
- Move Page Slots card, source modal, and delete confirmation copy onto structured admin locale keys.
- Move Page Revision history copy onto structured admin locale keys.
- Move Page Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Page Translation form routing, SEO, Open Graph, and action copy onto structured admin locale keys.
- Move Shared Slot revision history and snapshot detail copy onto structured admin locale keys.
- Move Shared Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Shared Slots index, create, edit, and form copy onto structured admin locale keys.
- Move Search Index admin screen copy onto structured admin locale keys.
- Move Slot Types index copy onto structured admin locale keys.
- Move Site Clone and Delete admin copy onto structured admin locale keys.
- Move Site Domains, Site details, Site Assets, Public Theme, and Site Variables admin copy onto structured admin locale keys.
- Move Sites create/edit form tabs, branding, SEO, contact, and footer action copy onto structured admin locale keys.
- Move Page edit management, overview, publish modal, and translation table copy onto structured admin locale keys.
- Move System Settings general, project identity, mail, diagnostics, privacy, and runtime copy onto structured admin locale keys.
- Move Media Library listing, grid, preview, upload, fetch, folder, and bulk-delete copy onto structured admin locale keys.
- Move Visitor Reports admin screen copy onto structured admin locale keys for native translation readiness.
- Add a native-only admin translation audit mode that ignores the legacy HTML fallback map and blocks `LocalizeAdminHtml` removal until direct structured-key migration is complete.
- Move Profile, Slot Types, flash, and page action partial copy onto structured admin locale keys, bringing the admin translation audit to 100% coverage.
- Move System Icons index and edit modal copy onto structured admin locale keys.
- Move fallback, layout shell, content header, stat card, inline media/link, and shared icon badge block editor copy onto structured admin locale keys.
- Move Sidebar Nav Item, Sidebar Nav Group, and Sidebar Footer block editor copy onto structured admin locale keys.
- Move Users admin listing and form copy onto structured admin locale keys.
- Move Hero block editor copy onto structured admin locale keys.
- Move Gallery Items and Rich Text editor partial copy onto structured admin locale keys.
- Move Page Converter admin screen copy onto structured admin locale keys.
- Move Site Promotion admin screen copy onto structured admin locale keys.
- Move Plugin Catalog detail screen copy onto structured admin locale keys.
- Move CMS API Tokens main admin screen copy onto structured admin locale keys.
- Harden admin translation auditing so new admin Blade view families are discovered automatically and strict baseline checks fail on newly uncovered UI phrases.
- Add an admin translation quality gate script for German and Turkish admin locales.
- Move System Updates blocker copy and Export / Import admin screen copy onto structured admin locale keys.
- Move Columns, Link List, Feature Grid, and Contact Form block editor copy onto structured admin locale keys.
- Move Header Actions, Audio, Breadcrumb, and Download block editor copy onto structured admin locale keys.
- Move Link List Item, List, Table, Container, and Grid block editor copy onto structured admin locale keys.
- Move Runtime Status, Search Form, Sticky Navbar, Header, and Sticky Navbar settings copy onto structured admin locale keys.
- Move Accordion, Callout, Column Item, Download Inline, and Feature Item block editor copy onto structured admin locale keys.
- Document that the `LocalizeAdminHtml` bridge must be removed when admin translation migration is complete.
- Move Button Link, Trusted HTML, Section, Tabs, FAQ, Text, and TOC block editor copy onto structured admin locale keys.
- Move Slide and Gallery block settings copy onto structured admin locale keys.
- Move Sidebar and Navbar brand/navigation block editor copy onto structured admin locale keys.
- Move shared pagination and small block presentation settings copy onto structured admin locale keys.
- Move File, Video, Quote, Text Inline, and Feature Grid editor copy onto structured admin locale keys.
- Move Cluster and Slider block settings copy onto structured admin locale keys.
- Move CTA, Button, Navigation Auto, and shared background media editor copy onto structured admin locale keys.
- Move Image, Button Inline, Slide, Navigation Auto Inline, and API token capability copy onto structured admin locale keys.

## 1.34.1

- Bumped CMS to `1.34.1`.
- Move System Updates and Backups screen card, body, modal, action, and status copy onto the selected admin locale.
- Add regression coverage for localized System Updates and Backups admin screen body copy.

## 1.34.0

- Bumped CMS to `1.34.0`.
- Add an admin translation audit command for measuring hard-coded Blade UI copy coverage against the admin HTML fallback map.
- Broaden German and Turkish admin HTML fallback coverage to 100% for audited admin Blade UI copy across media, plugins, settings, visitor reports, contact messages, page/slot, site, revision, and system screens.

## 1.33.1

- Bumped CMS to `1.33.1`.
- Make Sites and Pages admin listing screens resolve primary screen, filter, table, and action copy from the selected admin locale.
- Add an admin HTML localization fallback so resource, system, media, user, locale, and report screens use the authenticated admin locale beyond the sidebar/topbar while deeper Blade migrations continue.

## 1.33.0

- Bumped CMS to `1.33.0`.
- Add the first file-based CMS translations layer for admin, public system copy, and block defaults.
- Add an install-wide admin panel language setting and use it in the admin shell/sidebar/topbar.
- Add per-user admin panel language preferences on the Profile screen, with system admin language fallback.
- Make public Search UI and Search Form defaults resolve copy from the current public locale.
- Make Contact Form default visitor labels resolve from the block translation catalog.
- Make public Comments and Rating system block copy and engagement success states resolve from the current public locale.
- Make CMS auth and password reset screens, auth validation copy, and reset email copy resolve from the admin locale.
- Make Dashboard and Engagement admin screens resolve visible interface copy from the admin locale.
- Make Contact Form, Comments, and Rating validation feedback resolve from the active public locale and keep engagement validation redirects on the relevant block.
- Make Engagement admin comment status flash messages resolve from the admin locale.
- Make the admin block type picker and Comments/Rating system block editor settings resolve copy from the admin locale.

## 1.32.246

- Bumped CMS to `1.32.246`.
- Render public page `<html lang>` from the page translation locale instead of the Laravel app fallback locale.

## 1.32.245

- Bumped CMS to `1.32.245`.
- Replace the Add/Edit Locale picker with a short standard language list and keep country variants/custom BCP 47 style tags behind custom locale details.
- Show the same locale picker on Edit Locale, with the current standard locale selected when available.
- Simplify Internal Content API locale options to the same curated standard language list.

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
