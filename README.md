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
- Current content blocks are `header`, `plain_text`, `button_link`, `card`, `stat-card`, and `alert`.
- Current pattern blocks are `content_header`, `link-list`, and `link-list-item`.
- Current navigation blocks include `breadcrumb`, `header-actions`, `sidebar-brand`, `sidebar-navigation`, `sidebar-nav-item`, `sidebar-nav-group`, and `sidebar-footer` for docs or shell-adjacent navigation structures.
- All four block types are page and slot scoped, not site-global, and inherit site scope through the page and slot relationship.
- `section` is a top-level layout wrapper that renders only `<section class="wb-section">{children}</section>`.
- `container` is a layout wrapper that renders only `<div class="wb-container">{children}</div>`.
- `cluster` is a layout wrapper that renders only `<div class="wb-cluster">{children}</div>` and is intended for inline child grouping such as button link rows.
- `grid` is a layout wrapper that renders only `<div class="wb-grid wb-grid-*">{children}</div>` and is intended for responsive card and feature sections.
- `header` stores user-facing text in `block_text_translations.title` and stores the selected heading level as shared non-translatable block data in `blocks.variant`.
- `plain_text` stores user-facing text in `block_text_translations.content` and does not use shared user-facing content fields.
- `button_link` stores the translated label in `block_text_translations.title` and stores the shared URL and target in `blocks.settings` while keeping the shared button variant in `blocks.variant`.
- `card` stores translated optional eyebrow or label, `title`, `subtitle`, `description`, and optional action label in `block_text_translations`, stores the shared `variant`, optional shared URL, and target in `blocks.settings`, and accepts nested footer or action child blocks.
- `stat-card` stores the translated eyebrow or label in `block_text_translations.subtitle`, the translated metric value in `block_text_translations.title`, and the translated description in `block_text_translations.content`. Optional shared URL remains on `blocks.url`.
- `stat-card` now uses `wb-stat` pattern instead of `wb-card` for full UI alignment.
- `alert` stores translated optional `title` and required body text in `block_text_translations` and stores the shared alert variant in `blocks.settings`.
- `link-list` is a first-class container block for docs-style structured navigation rows and renders the WebBlocks UI `wb-link-list` wrapper.
- `link-list-item` stores translated `title`, `subtitle`, and `content` in `block_text_translations`, stores the shared URL on `blocks.url`, and renders one WebBlocks UI link row inside `wb-link-list`.
- `content_header` stores user-facing `title`, `intro_text`, and `meta_items` in `block_text_translations` and stores the shared title level in `blocks.variant`.
- `section` and `container` have no translatable fields and no user-facing JSON content.
- `cluster` has no translatable fields and no user-facing JSON content.
- `grid` has no translatable fields and no user-facing JSON content.
- `section` and `container` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- `cluster` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- `grid` may optionally store an admin-only shared name in block settings for editor tree labels and parent selection. That name is not rendered publicly and is not translated.
- `card` now supports nested footer content. Preferred action structure is `Card > Cluster > Button Link`.
- `card` supports `default` and `promo` variants. Promo maps to the shipped WebBlocks UI promo pattern and is intended for docs or CTA-style entry blocks.
- Card child footer blocks render inside `div.wb-card-footer`. The legacy single `action_label` and `url` fields continue to work as a fallback when no child footer blocks are present.
- Promo cards render optional eyebrow or label text above the title and prefer nested action structure such as `Card promo > Cluster > Button Link`.
- `stat-card` is the first-class metric card block for WebBlocks UI stats. Metric values like `0`, `6`, `14+`, and `173` are valid translated strings and must render in admin and public output.
- `alert` supports `info`, `success`, `warning`, and `danger` variants and renders WebBlocks UI alert markup for docs notes, proof points, and inline callouts.
- `breadcrumb` is a first-class system navigation block for header or context bars. It renders the current page breadcrumb trail from the active site and page translation context, stores only shared operational options in `blocks.settings`, and should not be used as a substitute for full navigation menus.
- `header-actions` is a first-class system navigation block for compact header utility controls such as color mode and accent actions. It pairs naturally with `breadcrumb` inside the `header` slot and is not a navigation menu.
- `sidebar-brand`, `sidebar-navigation`, `sidebar-nav-item`, `sidebar-nav-group`, and `sidebar-footer` are first-class docs sidebar blocks intended for the `sidebar` slot in the `docs` shell. They render only the inner contents of the docs aside and do not own the outer `aside.wb-sidebar` wrapper.
- `sidebar-brand` stores translated `title` and `subtitle` in `block_text_translations` and stores shared URL and target in `blocks.settings`.
- `sidebar-navigation` is a first-class container block that renders only `nav.wb-sidebar-nav > div.wb-sidebar-section`, stores translated navigation ARIA label in `block_text_translations.title`, and accepts only `sidebar-nav-item` and `sidebar-nav-group` children.
- `sidebar-nav-item` stores translated label text in `block_text_translations.title`, stores shared URL, target, icon, and active matching mode in `blocks.settings`, and renders the active class only on the `a.wb-sidebar-link` element.
- `sidebar-nav-group` stores translated group label in `block_text_translations.title`, stores shared icon and initial open state in `blocks.settings`, and accepts only `sidebar-nav-item` children.
- `sidebar-footer` stores translated callout title, body, and footer text in `block_text_translations` and stores the shared callout variant in `blocks.settings`.
- Public page structure is now configurable with controlled shared settings on pages and slots. `Public Shell` supports `default` and `docs`, and slot wrapper settings support controlled wrapper elements plus presets for `docs navbar`, `docs sidebar`, `docs main`, `default`, and `plain`.
- Docs shell rendering is layout-level, not block-level. Use page `Public Shell = Docs` with slot presets such as `Docs Navbar`, `Docs Sidebar`, and `Docs Main` to render the real WebBlocks UI docs contract: `.wb-dashboard-shell`, `.wb-sidebar-backdrop[data-wb-sidebar-backdrop]`, `<aside class="wb-sidebar" id="docsSidebar">`, `<header class="wb-navbar wb-navbar-glass">`, and `<main class="wb-dashboard-main">` without pushing shell classes into blocks.
- Docs shell landmark order is canonical and does not follow slot management sort order. The public docs shell always renders backdrop, sidebar, and then dashboard body with header and main, while the slot order in Edit Page remains an editor-facing management order.
- Docs sidebar shell responsibility stays at the page and slot layer. The `Docs Sidebar` slot preset owns `<aside class="wb-sidebar" id="docsSidebar">`, while sidebar blocks render only the brand, navigation, nav groups or items, and footer content inside that wrapper.
- `Docs Navbar` aligns header slot children with a full-width docs topbar row using shipped WebBlocks UI flex utilities such as `wb-flex`, `wb-items-center`, `wb-justify-between`, `wb-gap-3`, and `wb-w-full` instead of spacer divs.
- Slot wrapper classes are controlled presets, not arbitrary editor-provided class strings. The CMS validates allowed wrapper elements and wrapper presets server-side and falls back safely for unknown values.
- Existing saved `dashboard` shell values and `dashboard-*` slot presets are normalized to the `docs` shell equivalents during rendering and admin saves.
- `header-actions` uses shipped WebBlocks UI hooks directly: the mode button uses `data-wb-mode-cycle`, the accent menu uses the standard `wb-dropdown wb-dropdown-end` pattern, and the CMS public JavaScript only keeps ARIA and selected-state sync aligned with `WBTheme` and the dropdown runtime.
- Public mode compatibility depends on shipped WebBlocks UI classes and tokens instead of site-level fixed light colors. Site-specific public CSS should prefer tokens such as `var(--wb-bg)`, `var(--wb-surface)`, `var(--wb-text)`, `var(--wb-muted)`, `var(--wb-border)`, and `var(--wb-accent)` so pages remain readable in light, dark, and auto mode.
- `link-list-item.title` renders as `wb-link-list-title`, `link-list-item.subtitle` renders as `wb-link-list-meta`, `link-list-item.content` renders as `wb-link-list-desc`, and `link-list-item.url` becomes the item `href`.
- Link List Items support native drag-and-drop reorder in the admin editor through a small CMS admin JavaScript module and do not require any external drag-and-drop dependency.
- Edit Slot Blocks lists support native drag-and-drop sibling reordering in the admin editor without external dependencies. Drag-and-drop persists sibling order immediately, reorders only within the same parent group, and does not change nesting.
- Intended docs structure: `Section > Content Header or Heading > Link List > Link List Item` rows such as Getting Started, Architecture, Foundation, Layout, Primitives, Icons, Patterns, Utilities, and JavaScript.
- Example docs structure: `Section > Container > Content Header + Card promo + Alert`.
- CMS public CSS makes direct `wb-card-footer > .wb-cluster` children full width so cluster alignment options like `wb-cluster-end` work inside card footers.
- Existing eligible blocks can also be moved under card parents from the `Edit Block` modal when the card accepts that child type.
- The block modal now exposes three tabs: `Block Info`, `Block Fields`, and `Settings`.
- Edit Slot block lists hide internal order numbers and use CSS-based hierarchy guide lines for nested block readability.
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
- Public block flow spacing is managed at the parent renderer level with the WebBlocks UI `wb-stack` primitive for slot, `section`, and `container` block flows.
- Individual public block components should stay spacing-neutral and should not carry their own margin utilities for ordinary vertical rhythm.

### Catalog Sync

- Core block catalog updates are DB-backed. When a new first-class block type is added in code, refresh the local catalog with `ddev artisan db:seed --class=Database\\Seeders\\BlockTypeSeeder`.
- `BlockTypeSeeder` updates block type records in place by slug and is the approved non-destructive sync path for block catalog additions such as `stat-card`.
- Admin block editor behavior uses versioned plain JavaScript assets under `public/assets/webblocks-cms/js/admin/` instead of npm, Vite, or third-party drag-and-drop packages.
- The Edit Slot Blocks drag-and-drop flow persists sibling order immediately through a CMS admin endpoint, keeps the existing move up and move down controls as fallback actions, and does not change parent relationships.

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
## Public Layout Shells

### Docs Shell (Holy Grail)

- Semantic DOM order: `header -> main -> sidebar -> footer`
- Visual layout is handled by WebBlocks UI CSS, not by sidebar-first DOM hacks
- Suitable for docs and other content-heavy pages
- Page shell controls layout structure; blocks remain responsible only for content

### Why not sidebar-first?

- Better accessibility and landmark flow
- Cleaner SEO-facing HTML
- Simpler, shell-driven layout composition without block-level layout responsibility

5. Build page structure with `Section`, `Container`, `Cluster`, or `Grid`, then add `Header`, `Plain Text`, `Button Link`, `Card`, `Stat Card`, or `Breadcrumb` blocks inside that layout tree. Use `Breadcrumb` for header and context bars, and use `navigation-auto` only for actual menus. For docs layouts, set the page `Public Shell` to `Docs` and configure safe slot wrapper presets on the Header, Sidebar, and Main slots instead of placing shell classes on individual blocks. For card actions, prefer `Card > Cluster > Button Link`; the legacy single card action fields remain available as a fallback.
6. For a docs-style context bar, add `Breadcrumb` and `Header Actions` to the `Header` slot. `Header Actions` renders system-owned theme utilities such as color mode and accent controls without requiring raw HTML blocks.
7. For a docs-style sidebar, use the `Sidebar` slot with the `Docs Sidebar` preset, then add `Sidebar Brand`, `Sidebar Navigation`, and `Sidebar Footer` as top-level sidebar blocks. Inside `Sidebar Navigation`, add `Sidebar Nav Item` and optional `Sidebar Nav Group` blocks.
8. Publish the page as a `site_admin` or `super_admin`.
9. Open the public URL or preview link to confirm the live result.

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

### Site Deletion Rules

- Site deletion is explicit and removes site-scoped CMS content before deleting the site record.
- Deleted site-scoped data includes page revisions, pages, page translations, page slots, blocks, block translation rows, block asset links, navigation items, and locale assignments for that site.
- All site-scoped editorial history for the deleted site is removed with the site, including `page_revisions` rows.
- Shared assets and physical files are left intact.
- Primary site protection and last-site protection rules are unchanged.

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
- The Edit Slot block tree now preserves expanded nested block state locally in the browser per page and slot type, using `localStorage` keys like `webblocks.cms.slotBlocks.expanded.page.{pageId}.slot.{slotTypeId}`, so admin URLs stay clean.
- Nested block rows in Edit Slot now render with level-based table-cell indentation instead of visible character prefixes such as `—`.
- Blocks list now uses visual indent guides similar to code editors for better hierarchy readability.
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

## Backup Manager

- The Backups screen supports both `Create backup` and `Upload backup` actions.
- Backup list actions stay visible for every row. Running backups keep a visible delete action in a disabled state so the UI stays consistent and clearly shows that deletion is blocked while a backup is still active.
- `Upload backup` accepts a previously downloaded WebBlocks CMS backup archive from this backup system and validates the archive before it is registered.
- Uploaded backup archives are useful for disaster recovery, restoring a previously downloaded backup, or moving a backup into a local DDEV install for debugging.
- Backup upload validation requires a backup `manifest.json`, `database/database.sql`, safe archive paths, rejects site export/import packages, and rejects empty or obvious non-SQL dump content before restore.
- Uploaded backups are registered as normal backup records, appear in the existing Backups list and detail page, and reuse the same restore flow as locally created backups.
- Backup restore is a full-system restore that overwrites the current database and uploaded files.
- Backup restore is different from Export / Import, which creates a new site from a site package instead of replacing the current install.
- When the existing restore flow runs, it validates the selected archive first and only creates a fresh safety backup before applying a valid archive.
- MySQL and MariaDB backup creation uses raw SQL stdout from either direct `mysqldump` or `ddev exec --raw -- mysqldump` style execution. Restore feeds validated SQL content back through stdin instead of passing command text as SQL.
- Running backups that never finish are automatically marked as failed when the Backups screen loads once they are older than the configured stale threshold.
- Failed backups can be safely deleted from the Backups list, including their stored archive file when one exists.
- Interrupted or stuck running backup records can also be deleted from the Backups list with explicit confirmation when you know no backup process is still active.
- This running-backup cleanup path is intended for local, test, or interrupted runs and should only be used after confirming the backup job is no longer active.
- Restore history entries can be deleted from the backup detail page to clean up failed or completed restore audit rows created during testing.
- Deleting a restore history entry removes only the `system_backup_restores` row. It does not delete the source backup archive, safety backup archive, or related backup records.
- Configure stale running backup detection with `CMS_BACKUP_STALE_AFTER_MINUTES` or `config('cms.backup.stale_after_minutes')`. The default timeout is `10` minutes.

### Backup Execution Modes

- `CMS_BACKUP_EXECUTION=auto` selects `ddev` for local DDEV-hosted MySQL or MariaDB installs and otherwise uses direct local database binaries.
- `CMS_BACKUP_EXECUTION=direct` forces local `mysqldump` or `mariadb-dump` plus `mysql` or `mariadb` binaries.
- `CMS_BACKUP_EXECUTION=ddev` forces `ddev exec --raw -- ...` database dump and restore execution.

### Backup Restore Troubleshooting

- If restore reports invalid SQL dump content, inspect `database/database.sql` inside the backup zip.
- The SQL file must contain SQL dump text such as `-- MySQL dump`, `SET`, `CREATE TABLE`, `INSERT INTO`, or `DROP TABLE`.
- The SQL file must not start with shell or helper output such as `ddev exec`, `mysqldump`, or `You executed ...`.

## Stack

- Laravel application
- server-rendered Blade views
- WebBlocks UI assets loaded via CDN
- CMS core public and admin assets live under `public/assets/webblocks-cms/`
- `public/assets/webblocks-cms/css/public.css` is reserved for CMS public render and fallback styles.
- The public page layout loads `public/assets/webblocks-cms/css/public.css` before optional site-level `public/site/css/site.css` overrides.
- `public/assets/webblocks-cms/css/admin.css` is reserved for small CMS admin UI companion styles.
- optional install-level public overrides live under `public/site/css/site.css` and `public/site/js/site.js`
- site-specific hooks under `public/site/css/*` should not be used for core CMS admin assets.

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
## Docs Home Sync

Run `ddev artisan webblocks:sync-ui-docs-home-main` to import only the remaining CMS-managed main narrative sections from the WebBlocks UI docs home page into the default Home page `main` slot. The command is idempotent for its own imported section wrappers and does not modify the existing manual sections that precede them.
