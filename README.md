# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel-based, block-driven CMS for managing sites, pages, media, navigation, and editorial publishing from one admin interface. It includes install-level administration for users, updates, backups, site transfer tools, and system settings.

## Feature Summary

- block-based page building with reusable layouts, slots, and blocks
- multisite and locale-aware page management
- install-level user management with `super_admin`, `site_admin`, and `editor` roles
- editorial workflow for pages with review and publishing states
- page revisions and in-place restore
- media library and site-scoped navigation management
- install wizard for first-run setup
- system updates, backups, and site export/import tools

## Data Model Principles

- DB-first architecture
- `page_translations` is authoritative for page title, slug, and path
- `pages` stores structural and editorial metadata such as site, type, layout, workflow, and SEO fields
- `block_*_translations` tables are authoritative for translatable block content
- `blocks` stores structure, placement, shared config, assets, status, and variant or meta fields
- canonical page and block text columns, where still present, are compatibility-only and not the active source of truth
- contact form `submit_label` and `success_message` live in translation rows; block `settings` is reserved for shared operational config
- no JSON storage is used for user-facing page or block content

## Installation

For a fresh install, first get the source code locally:

```bash
git clone git@github.com:fklavyenet/webblocks-cms.git
cd webblocks-cms
```

If you already created an empty target directory, use `git clone git@github.com:fklavyenet/webblocks-cms.git .` instead. After the source code is present locally, continue with one of the install paths below.

### DDEV Quick Start

```bash
ddev config --project-type=laravel --docroot=public --project-name=<your-project-name>
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
```

For a fresh install with the browser flow, open `/install` after the source code, dependencies, and local environment are in place. The install wizard guides database setup, environment creation, core install steps, and first super admin creation.

Typical local URLs:

- installer: `/install`
- public site: `/`
- admin: `/admin`

### Manual CLI Install

```bash
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
ddev artisan db:seed
ddev artisan storage:link
```

If you are not using DDEV, the equivalent Laravel dev server flow is:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Then open:

- installer: `http://127.0.0.1:8000/install`
- public site: `http://127.0.0.1:8000/`
- admin: `http://127.0.0.1:8000/admin`

See `docs/installation.md` for the complete install guide.

## Quick Start

1. Install the CMS with the browser wizard or the CLI flow.
2. Sign in to `/admin`.
3. Create or edit a site if your install uses more than one site.
4. Create a page. New pages start as `draft`.
5. Add blocks, save your edits, and submit the page for review when ready.
6. Publish the page as a `site_admin` or `super_admin`.
7. Open the public URL or preview link to confirm the live result.

See `docs/getting-started.md` for the first-use workflow.

## Admin Navigation

- Dashboard
- Direct links: Pages, Navigation, Media, Contact Messages
- Maintenance: Visitor Reports, Settings, Backups, Export / Import, Update
- System: Users, Sites, Locales, Slot Types, Block Types

## Roles And Permissions

- `super_admin`: full install-level access, including Users, sites, locales, settings, updates, backups, export/import, and site content
- `site_admin`: site-scoped admin access for assigned sites, including publishing and other editorial approvals
- `editor`: site-scoped editorial access for assigned sites, including draft editing and review submission

Users are install-level accounts, so the Users area lives under the admin `System` navigation group rather than under direct editorial links.

See `docs/users-and-permissions.md` for the full permissions model.

## Editorial Workflow

Pages use four workflow states:

- `draft`
- `in_review`
- `published`
- `archived`

`draft`, `in_review`, and `archived` pages are not public. Only `published` pages are routable on the public site. Editors can prepare content and submit it for review, while `site_admin` and `super_admin` users can also publish, archive, and move pages back to draft.

See `docs/editorial-workflow.md` for status and role details.

## Revisions And Operations

- Revisions are page-level editorial recovery snapshots. Restoring a revision first creates a fresh pre-restore safety revision.
- Export / Import is for moving one site's content between installs.
- Backup / Restore is for recovering the install environment.
- System Updates handle installed version checks and in-app update runs.

### Development Version Policy

- Local source development does not automatically change the installed version.
- A dev environment may show an older installed version until a real release is created.
- Do not use the `System Updates` button to apply ordinary local or source-level changes.
- Release flow may synchronize the dev installed version only after a real tag and published release exist.
- See `DEVELOPMENT.md` for the full development and release workflow.

Admin session expiry is handled defensively: after re-authentication, the original admin tab now resets transient overlay, drawer, and body-lock state so it does not stay dimmed or non-interactive.

These tools serve different purposes and are intentionally separate.

See `docs/revisions.md` and `docs/operations.md` for details.

## Multisite & Multilingual

- each site can have its own domain
- each site has its own enabled locale set
- the default locale is prefixless in public URLs
- non-default locales use a locale prefix
- public page resolution, navigation, and preview links are site-aware and locale-aware
- URLs are generated only when the requested translation exists and the locale is valid for the site

## URL Behavior

- default locale page URLs use `/p/{slug}`
- localized page URLs use `/{locale}/p/{slug}`, for example `/tr/p/hakkinda`
- the default locale is not prefixed, so `/en/...` is intentionally not routable when `en` is the default locale
- home routes follow the same rule: `/` for the default locale and `/{locale}` for non-default locales
- admin preview and navigation page links are only generated when a valid site-scoped translation exists

## Privacy-Aware Visitor Reports V2

- Public page views are always counted when `CMS_VISITOR_REPORTS_ENABLED=true`, but tracking now has two modes.
- Without consent, WebBlocks stores only privacy-safe anonymous page view data: `site_id`, `page_id` when resolved, `locale_id` when resolved, `path`, and `visited_at`.
- With consent, WebBlocks keeps the richer Visitor Reports model, including sessions, unique visitor estimation, referrers, optional UTM campaign fields, and browser or device summaries.
- Accept enables optional analytics tracking for sessions, unique visitors, referrers, UTM campaigns, browser summaries, device summaries, and OS summaries.
- Decline still allows anonymous aggregate page view counting for Visitor Reports.
- Public pages use the WebBlocks UI Cookie Consent pattern: one `wb-cookie-consent` bottom banner plus one shared `wb-modal` preference center mounted inside `#wb-overlay-root`.
- The public footer legal area includes a persistent `Cookie settings` control that reopens the shared preference modal after consent has already been saved.
- The browser-facing source of truth follows the WebBlocks UI pattern storage model: `localStorage` stores `wb-cookie-consent` plus `wb-cookie-consent-preferences`.
- CMS also syncs the Visitor Reports consent cookie from the pattern state so backend tracking can continue to distinguish anonymous basic tracking from consented analytics tracking.
- `Accept all`, `Reject`, and `Save preferences` persist consent. Closing the banner or modal with `X` only dismisses the UI for the current page view and does not save a choice.
- Necessary Laravel cookies and sessions used for CSRF protection, admin authentication, forms, and general application security are separate from optional analytics consent.
- Cookie consent UI only renders in the public layout. Admin and auth shells keep the shared WebBlocks UI CDN assets but do not render the consent banner.
- The admin `Settings` screen includes a dedicated `Cookie settings` card for consent-banner controls and future privacy options.

### Visitor Report Config

- `CMS_VISITOR_REPORTS_ENABLED`: enables or disables all visitor report tracking.
- `CMS_VISITOR_UTM_ENABLED`: enables UTM capture for consented full tracking only.
- `CMS_VISITOR_CONSENT_BANNER_ENABLED`: shows or hides the public privacy settings banner and reopen link.
- `CMS_VISITOR_CONSENT_COOKIE_NAME`: overrides the backend consent cookie name used for Visitor Reports cookie sync.

### Cookie Consent Integration

- Public layout CDN assets use the WebBlocks UI jsDelivr endpoints:
  `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css`
  `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css`
  `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js`
- The consent banner markup lives in `resources/views/partials/public-privacy-consent.blade.php`.
- The shared preference modal is mounted by `resources/views/layouts/public.blade.php` inside `#wb-overlay-root`.
- The public footer reopen control lives in `resources/views/pages/partials/slots/footer.blade.php`.
- When the WebBlocks UI pattern emits `wb:cookie-consent:change`, CMS posts the updated state to `public.privacy-consent.sync` so Visitor Reports backend consent stays aligned with the browser-side pattern state.

### Report Semantics

- Page views include all privacy-safe anonymous views and all consented full views.
- Unique visitors, sessions, referrers, campaigns, and device summaries require analytics consent.
- Anonymous privacy-safe rows are not treated as `Direct / None` campaign traffic.

## Documentation

- [Installation](docs/installation.md)
- [Getting Started](docs/getting-started.md)
- [Users And Permissions](docs/users-and-permissions.md)
- [Editorial Workflow](docs/editorial-workflow.md)
- [Revisions](docs/revisions.md)
- [Operations](docs/operations.md)
- [Block UI Renderer Contract](docs/block-ui-renderer-contract.md)
- [Development Workflow](DEVELOPMENT.md)

## Block UI Renderer Contract

- Public block output maps to shipped WebBlocks UI primitives and patterns.
- The detailed renderer contract lives in `docs/block-ui-renderer-contract.md`.
- New or changed block renderers must follow this contract.
- Public layout mode selection controls whether a page renders as a default stack layout, a sidebar composition, or a future explicit content-shell mode.

## Stack

- Laravel application
- server-rendered Blade views
- WebBlocks UI assets loaded via CDN
- CMS core public assets live under `public/assets/webblocks-cms/`
- optional install-level public overrides in `public/site/css/site.css` and `public/site/js/site.js`

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
