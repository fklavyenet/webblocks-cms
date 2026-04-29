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

## Block Foundation

- The active CMS block foundation is intentionally small and split into layout blocks and content blocks.
- Current layout blocks are `section`, `container`, `cluster`, and `grid`.
- Current content blocks are `header`, `plain_text`, `button_link`, and `card`.
- Current pattern blocks are `content_header`.
- All four block types are page and slot scoped, not site-global, and inherit site scope through the page and slot relationship.
- `section` is a top-level layout wrapper that renders only `<section class="wb-section">{children}</section>`.
- `container` is a layout wrapper that renders only `<div class="wb-container">{children}</div>`.
- `cluster` is a layout wrapper that renders only `<div class="wb-cluster">{children}</div>` and is intended for inline child grouping such as button link rows.
- `grid` is a layout wrapper that renders only `<div class="wb-grid wb-grid-*">{children}</div>` and is intended for responsive card and feature sections.
- `header` stores user-facing text in `block_text_translations.title` and stores the selected heading level as shared non-translatable block data in `blocks.variant`.
- `plain_text` stores user-facing text in `block_text_translations.content` and does not use shared user-facing content fields.
- `button_link` stores the translated label in `block_text_translations.title` and stores the shared URL and target in `blocks.settings` while keeping the shared button variant in `blocks.variant`.
- `card` stores translated `title`, `subtitle`, `description`, and optional action label in `block_text_translations` and stores the optional shared URL and target in `blocks.settings`.
- `content_header` stores user-facing `title`, `intro_text`, and `meta_items` in `block_text_translations` and stores the shared title level in `blocks.variant`.
- `section` and `container` have no translatable fields and no user-facing JSON content.
- `cluster` has no translatable fields and no user-facing JSON content.
- `grid` has no translatable fields and no user-facing JSON content.
- `section` and `container` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- `cluster` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- `grid` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- The block modal now exposes three tabs: `Block Info`, `Block Fields`, and `Settings`.
- The `Settings` tab is shared and non-translatable. It stores whitelist-based public appearance settings in `blocks.settings` and maps only to confirmed shipped WebBlocks UI classes.
- Currently available settings:
- `header`: `alignment` -> `wb-text-left`, `wb-text-center`, `wb-text-right`
- `plain_text`: `alignment` -> `wb-text-left`, `wb-text-center`, `wb-text-right`
- `content_header`: `alignment` -> `wb-text-left`, `wb-text-center`, `wb-text-right`
- `button_link`: `variant` -> `wb-btn wb-btn-primary`, `wb-btn wb-btn-secondary`
- `section`: `spacing` -> `wb-section-sm`, `wb-section-lg`
- `container`: `width` -> `wb-container-sm`, `wb-container-md`, `wb-container-lg`, `wb-container-xl`, `wb-container-full`
- `cluster`: `gap` -> `wb-cluster-2`, `wb-cluster-4`, `wb-cluster-6`; `alignment` -> `wb-cluster-center`, `wb-cluster-end`
- `grid`: `columns` -> `wb-grid-2`, `wb-grid-3`, `wb-grid-4`; `gap` -> `wb-gap-3`, `wb-gap-4`, `wb-gap-6`
- Arbitrary class entry is not supported.
- The default locale must always have a translation row for translatable blocks.
- Public rendering reads user-facing text from translation rows, not canonical fallback columns.
- Layout and primitive content blocks do not carry higher-level WebBlocks UI pattern markup. UI patterns will be introduced later, one by one, on top of this layout layer.

## Product Boundary

- WebBlocks CMS core contains reusable CMS engine features such as page building, multilingual content, multisite, media, navigation, workflow, backup, update, and generic site export/import.
- Site-specific code that must survive CMS updates belongs in the Project Layer under `project/`.
- Site-specific migration scripts, one-off legacy importers, and project-only reconstruction helpers do not belong in CMS core.
- CMS core does not ship website-specific UI docs or demo content generator commands.
- If a migration script is only relevant to a specific site, brand, or historical project, keep it in that project workspace instead of this repository.
- Generic site export/import remains part of CMS core and is the supported reusable transfer path.
- Website and demo content should be distributed as native CMS export/import snapshots instead of hard-coded PHP page builder commands in core.

## Project Layer

- The Project Layer is WebBlocks CMS's update-safe extension boundary for one install or website instance.
- Use `project/` for site-specific providers, routes, commands, config, and Blade views that should not be overwritten by CMS core updates.
- Do not place instance-specific behavior in core `app/`, `routes/`, `resources/`, or `config/` if that behavior must survive updates.
- The Project Layer is not the plugin system. V1 only supports install-local code living in `project/`.

### Structure

- `project/Providers/`
- `project/Routes/`
- `project/Console/Commands/`
- `project/config/`
- `project/resources/views/`
- `project/README.md`

### Init Command

Create the scaffold with:

```bash
ddev artisan project:init
```

The command only creates missing directories and starter files. It never overwrites existing project files.

### Loading Rules

- `project/config/*.php` loads under `project.*`, for example `project/config/sites.php` becomes `config('project.sites')`.
- `project/Routes/web.php` loads with the normal `web` middleware group when present.
- `project/Routes/api.php` loads with the normal `api` middleware group under the `api` prefix when present.
- `project/Routes/console.php` is optional and only loaded in console contexts.
- `project/resources/views` is available through the `project::` namespace, for example `view('project::docs.layout')`.
- `project/config/providers.php` may list provider classes such as `Project\Providers\ProjectServiceProvider::class`.

### Example

Example project route:

```php
use Illuminate\Support\Facades\Route;

Route::get('/project-health', fn () => 'ok');
```

Example project config:

```php
return [
    'default_site_handle' => 'main',
];
```

Example project view usage:

```php
return view('project::docs.layout', ['message' => 'Hello']);
```

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
5. Build page structure with `Section`, `Container`, `Cluster`, or `Grid`, then add `Header`, `Plain Text`, `Button Link`, and `Card` blocks inside that layout tree.
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

Admin JavaScript is organized under `public/assets/webblocks-cms/js/admin/`. Blade templates should not contain large inline scripts; admin behavior should be added to named JS modules and loaded through the admin layout.

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
- Public shell-free rendering does not inject cookie consent banners, preference modals, reopen controls, or overlay roots into page HTML.
- CMS can still sync Visitor Reports consent through the backend consent endpoint when an external site shell manages consent state.
- Necessary Laravel cookies and sessions used for CSRF protection, admin authentication, forms, and general application security are separate from optional analytics consent.
- Admin and auth shells keep their own CMS UI and are not affected by the shell-free public rendering mode.
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
- Public shell-free pages do not emit consent markup or overlay containers.
- When an external consent implementation emits state changes, CMS can still receive updates through `public.privacy-consent.sync` so Visitor Reports backend consent stays aligned with the site shell.

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
- [Block UI Alignment Audit — Phase 4](docs/block-ui-alignment-audit-phase-4.md)
- [Development Workflow](DEVELOPMENT.md)

## Public Rendering

- `section` renders only `<section class="wb-section">...</section>`.
- `container` renders only `<div class="wb-container">...</div>`.
- `section` may append only whitelisted spacing classes such as `wb-section-sm` or `wb-section-lg`.
- `container` may append only whitelisted width classes such as `wb-container-lg` or `wb-container-xl`.
- `header` renders only the selected semantic heading element and may append a whitelisted alignment class, for example `<h1 class="wb-text-center">Text</h1>`.
- `plain_text` renders only a semantic paragraph element and may append a whitelisted alignment class, for example `<p class="wb-text-right">Text</p>`.
- `button_link` renders only a direct anchor element such as `<a href="/start" class="wb-btn wb-btn-primary">Start here</a>` with optional `_blank` target plus `rel="noopener noreferrer"`.
- `cluster` renders only a semantic wrapper such as `<div class="wb-cluster wb-cluster-2">...</div>` and is intended for inline child grouping without adding extra wrappers around its children.
- `content_header` renders the WebBlocks UI docs header pattern as `<header class="wb-content-header">` with `wb-content-title`, optional `wb-content-subtitle`, and optional `wb-content-meta` rows.
- `button_link` label is translated per locale, while `url`, `target`, and `variant` remain shared across locales.
- In the CMS UI, `intro_text` maps to the rendered `wb-content-subtitle` paragraph.
- `content_header.meta_items` are stored as translated list data and render as ordered `<span>` items with `wb-content-meta-divider` inserted only between items.
- Nested rendering supports `section -> container -> header/plain_text` without extra wrappers.
- All current foundation blocks escape user-facing output safely.
- Primitive block rendering remains whitelist-based. Unverified or arbitrary classes are ignored.

## Public Rendering Mode

WebBlocks CMS now supports a shell-free rendering mode where:

- Only slots and blocks are rendered
- No default UI (footer, consent, overlays) is injected
- Blocks fully control the layout

This enables building pure WebBlocks UI sites directly from CMS.

### Block Picker UX

The slot editor uses a modal block type picker. Editors can click Add Block, search or select a block type, and immediately configure the new block without leaving the slot editor.
- The picker uses one database-driven block list ordered by `block_types.sort_order`, then by block name.
- Picker search matches block type name, slug, description, and category so editors can find blocks by handle and intent terms such as `button`, `cluster`, `intro`, or `layout`.
- Public block rendering follows a slug-to-renderer convention: block slug `x` resolves to `resources/views/pages/partials/blocks/x.blade.php`. Core renderers must not silently route one unrelated block type through another block's renderer.
- Deferred legacy blocks may still keep slug-matched compatibility renderers where needed to preserve existing public output, but that does not promote them to first-class WebBlocks UI patterns.
- Transitional duplicate patterns such as legacy Card Grid, Metric Card, and FAQ-list are retained only for compatibility and should be replaced by the aligned core primitives over time.
- Breadcrumb, Tabs, and Slider remain deferred until WebBlocks UI ships confirmed patterns or the public shell requires them.
- The detailed renderer contract lives in `docs/block-ui-renderer-contract.md`.

## Foundation Reset

- The active published foundation picker now includes `content_header`, `section`, `container`, `cluster`, `grid`, `header`, `plain_text`, `button_link`, and `card`.
- Legacy block type records may remain in the database as draft compatibility records for existing data and imports, but they are not part of the active foundation.
- `StarterContentSeeder`, `FullShowcaseSeeder`, `StarterInstallSeeder`, and `ShowcaseInstallSeeder` are intentionally quarantined until their content is rebuilt for the primitive foundation.
- For local development resets after changing the foundation, reseed block type metadata with `ddev artisan db:seed --class=BlockTypeSeeder` and remove old non-primitive page blocks with `ddev artisan cms:reset-primitive-blocks`.
- Use `ddev artisan cms:reset-primitive-blocks --dry-run` first if you want to inspect the impact.
- Future WebBlocks UI pattern blocks such as Hero, Promo, and similar higher-level patterns will be added later, one by one, on top of the current layout layer.

## System Updates

- Every in-app system update now creates a mandatory pre-update backup before installation begins.
- If pre-update backup creation fails, the update is stopped and the site stays online.
- The System Updates screen includes an optional `Download backup before update` checkbox.
- When the checkbox is left unchecked, the CMS creates the backup and continues the update immediately.
- When the checkbox is enabled, the CMS creates the backup first, then shows a two-step flow with `Download backup`, `Continue update`, and `Cancel` actions.
- Cancelling the pending flow keeps the created backup but does not install the update.
- Update installs preserve `.env`, `storage/`, and `project/` so instance-specific project files are not replaced by CMS package extraction.

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
