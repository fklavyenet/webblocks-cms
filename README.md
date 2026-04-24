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

## Install Options

### Install Wizard

For a fresh install, run:

```bash
composer install
cp .env.example .env
php artisan serve
```

Then open:

- installer: `http://127.0.0.1:8000/install`
- public site: `http://127.0.0.1:8000/`
- admin: `http://127.0.0.1:8000/admin`

### Manual CLI Install

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

### Optional DDEV Local Install

```bash
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
ddev artisan db:seed
ddev artisan storage:link
```

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

## Roles And Permissions

- `super_admin`: full install-level access, including Users, sites, locales, settings, updates, backups, export/import, and site content
- `site_admin`: site-scoped admin access for assigned sites, including publishing and other editorial approvals
- `editor`: site-scoped editorial access for assigned sites, including draft editing and review submission

Users are install-level accounts, so the Users area lives under the admin `System` navigation rather than alongside site content tools.

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

Admin session expiry is handled defensively: after re-authentication, the original admin tab now resets transient overlay, drawer, and body-lock state so it does not stay dimmed or non-interactive.

These tools serve different purposes and are intentionally separate.

See `docs/revisions.md` and `docs/operations.md` for details.

## Privacy-Aware Visitor Reports V2

- Public page views are always counted when `CMS_VISITOR_REPORTS_ENABLED=true`, but tracking now has two modes.
- Without consent, WebBlocks stores only privacy-safe anonymous page view data: `site_id`, `page_id` when resolved, `locale_id` when resolved, `path`, and `visited_at`.
- With consent, WebBlocks keeps the richer Visitor Reports model, including sessions, unique visitor estimation, referrers, optional UTM campaign fields, and browser or device summaries.
- Accept enables optional analytics tracking for sessions, unique visitors, referrers, UTM campaigns, browser summaries, device summaries, and OS summaries.
- Decline still allows anonymous aggregate page view counting for Visitor Reports.
- Necessary Laravel cookies and sessions used for CSRF protection, admin authentication, forms, and general application security are separate from optional analytics consent.

### Visitor Report Config

- `CMS_VISITOR_REPORTS_ENABLED`: enables or disables all visitor report tracking.
- `CMS_VISITOR_UTM_ENABLED`: enables UTM capture for consented full tracking only.
- `CMS_VISITOR_CONSENT_BANNER_ENABLED`: shows or hides the public privacy settings banner and reopen link.

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

## Stack

- Laravel application
- server-rendered Blade views
- WebBlocks UI assets loaded via CDN
- optional install-level public overrides in `public/site/css/site.css` and `public/site/js/site.js`

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
