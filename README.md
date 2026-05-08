# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel-based, block-driven CMS for managing sites, pages, media, navigation, and editorial publishing from one admin interface. It includes install-level administration for users, updates, backups, site transfer tools, and system settings.

## Feature Summary

- block-based page building with reusable layouts, slots, and blocks
- multisite and locale-aware page management
- fixed WebBlocks CMS product identity in admin chrome, with public site identity managed per site
- admin-only Project Name and Project Tagline settings so multiple installs are easier to distinguish without changing public metadata
- install-level user management with `super_admin`, `site_admin`, and `editor` roles
- editorial workflow for pages with review and publishing states
- database-backed public site search scoped by site and locale, with a header-triggered modal UX, `/search` fallback page, Search Form block support, and a System > Search rebuild screen
- page revisions and in-place restore with actor, source, and event metadata when available
- media library and site-scoped navigation management
- install-level icon catalog management under `System -> Icons`, with WebBlocks UI manifest sync, catalog metadata editing, and filtered navigation-only icon pickers in admin forms
- site-scoped primary domains and alias domains for one-install multi-domain public routing
- primary `Sites` admin navigation near `Dashboard`, with site-domain management grouped under `System -> Domains`
- install wizard for first-run setup
- system updates, backups, and site export/import tools
- site-level Branding and SEO Defaults with public `<head>` fallback metadata and favicon support, plus locale-aware page-level SEO overrides on page translations
- site-scoped Shared Slots that can render reusable block trees publicly inside existing page slot wrappers, can be managed from the admin, can be assigned per page slot from the Edit Page screen, now have dedicated Shared Slot revision history and restore, and participate in site export/import and site clone workflows
- super-admin global Blocks index under `Pages` for cross-CMS block maintenance with compact `Search`, `Site`, `Page`, `Block Type`, `Status`, and `Locale` filters

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
5. Build page structure with `Section`, `Container`, `Cluster`, or `Grid`, then add `Header`, `Plain Text`, `Rich Text`, `Button Link`, `Card`, `Stat Card`, or `Breadcrumb` blocks inside that layout tree. `Plain Text` is for plain body copy only. `Rich Text` is for safe inline formatting such as bold, italic, inline code, links, paragraphs, and simple lists; keep headings, layouts, buttons, media, tables, and raw HTML composition as dedicated first-class blocks or features. `Code` is for multi-line escaped snippets and should not be replaced with Rich Text. Rich Text is edited visually in the admin through a small dependency-free safe HTML editor. Stored Rich Text content is a restricted safe HTML fragment, and public output still renders through the shipped WebBlocks UI `wb-rich-text` primitive using `wb-rich-text wb-rich-text-readable`. Selected description fields still support safe inline code formatting with backticks. Use `Breadcrumb` for header and context bars, and use `navigation-auto` only for actual menus. `Public Shell` is page-level and controls the outer public shell. Slot wrappers are resolved automatically from the page shell and slot name, so blocks stay focused on content instead of shell markup. For docs layouts, set the page `Public Shell` to `Docs`; header, sidebar, and main slots then map to the docs wrappers automatically. Shared Slots now render publicly as reusable site-scoped slot block trees inside those existing slot wrappers when the slot source points at a compatible Shared Slot. Shared Slots are dynamic references, not copied templates. Shared Slots can now be created and managed from the top-level admin navigation, including editing the Shared Slot block tree with the same block editor patterns used for page slots. On the Edit Page screen, each slot source can now be set to `Page Content`, `Shared Slot`, or `Disabled`. Switching away from page-owned content does not delete existing page-owned blocks, so editors can safely switch back later. For card actions, prefer `Card > Cluster > Button Link`; the legacy single card action fields remain available as a fallback.
6. For a docs-style context bar, add `Breadcrumb` and `Header Actions` to the `Header` slot. `Header Actions` renders system-owned theme utilities such as color mode, accent controls, and the public search trigger without requiring raw HTML blocks.
7. For a docs-style sidebar, add a `Sidebar` slot and set the page `Public Shell` to `Docs`, then add `Sidebar Brand`, `Sidebar Navigation`, and `Sidebar Footer` as top-level sidebar blocks. Inside `Sidebar Navigation`, add `Sidebar Nav Item` and optional `Sidebar Nav Group` blocks, or point the block at the site-scoped `Docs` navigation menu so admin-managed `Add Group` sections render as collapsible docs sidebar groups.
8. Publish the page as a `site_admin` or `super_admin`.
9. Open the public URL or preview link to confirm the live result.

For common editorial choices, `Code`, `Table`, `TOC`, and `Quote` are available as first-class block picker options and remain editable from the slot editor. Use `Header` as the canonical heading or title block, including optional shared anchor IDs for direct links and TOC targets. `TOC` renders links from explicit anchored `Header` blocks on the same public page, including nested headers inside layout wrappers such as `Section`, `Container`, `Grid`, `Cluster`, or `Card`. Do not use the legacy `Heading` block type.

`HTML (Trusted)` is also available in the block picker as an advanced escape hatch for `super_admin` users only. Use it only for deliberate static trusted markup that cannot be represented cleanly with first-class blocks. For normal editorial work, prefer `Rich Text` for safe formatted copy and `Code` for escaped code samples.

On the Edit Page screen, page settings and slot structure are managed separately, slot additions are available from a compact `Add Slot` dropdown, and each slot keeps a compact source summary in the list with `Manage Source` modal settings for `Page Content`, `Shared Slot`, or `Disabled`. Shared Slot choices are limited to active compatible Shared Slots from the same site. When a slot uses a Shared Slot or is Disabled, the page-owned block tree is preserved and clearly labeled as not currently rendered.

In the admin slot block tree, block deletion now uses a WebBlocks modal instead of a browser confirm dialog. The safe default still deletes only the selected block, which preserves the existing child promotion behavior when a wrapper such as `Section` or `Container` is removed. Editors can explicitly opt into `Also delete all nested child blocks` when they want to remove an entire nested subtree. The modal shows the selected block, whether it has children, direct child count, and total descendant count, and warns that recursive deletion is only recoverable through revision or backup restore flows.

On the Edit Site screen, keep internal site routing fields such as `Name`, `Handle`, `Domain`, and `Primary` separate from public identity. Public-facing `Branding` and `SEO Defaults` live on the site itself. Use `display_name`, `tagline`, favicon, social image, and site-level SEO defaults there for public metadata fallbacks. Locale-aware page-level SEO overrides now live on each page translation, where editors can override title, description, keywords, and Open Graph fields for one locale without changing the fixed WebBlocks CMS admin product identity.

In the admin slot editor, the Edit Slot Blocks list stays structure-focused as a compact one-row-per-block table with block type, a single primary summary, a dedicated children-count column, status, and actions. The Block Picker now opens with a default `Common` shortcut tab and additional `Layout`, `Content`, `Navigation`, `Advanced`, and `All` tabs so larger catalogs stay navigable without one long mixed list. `Advanced` only appears when the current user has eligible advanced block types such as `HTML (Trusted)`. Search works across the full eligible picker catalog instead of only the active tab, sort still applies within the currently visible tab or search result set, reset returns the picker to the default `Common` tab, and the modal keeps the same compact shared admin filter toolbar pattern. On narrow screens the table remains one-row-per-block and scrolls horizontally instead of collapsing labels into vertical letter stacks. Full content should be edited in the block edit modal or block edit page instead of being previewed in the slot list.

The slot editor block picker follows the published block catalog directly. `Table`, `TOC`, `Quote`, and `Header` appear when their catalog rows are published. The legacy `Heading` catalog row is removed rather than kept hidden, so it does not appear in normal picker or editor availability.

`Admin -> System -> Settings` now also owns Project Identity. `Project Name` and `Project Tagline` are admin-only context labels used in the topbar and admin browser titles so teams can distinguish one install from another. They do not change the fixed WebBlocks CMS sidebar brand or version footer, and they do not affect public site metadata, favicon, SEO defaults, search scope, or locale-aware page metadata.

Admin index and listing screens such as `Block Types`, `Blocks`, `Pages`, `Media`, `Contact Messages`, and `Users` should use the shared compact listing filter toolbar partial at `resources/views/admin/partials/listing-filters.blade.php`. The contract is: Search stays on the far left and grows to fill the remaining horizontal space, compact select and other small filter inputs sit to the right of Search, and Apply or Reset actions stay right-aligned on the same toolbar row on wide screens. Admin list pagination should use the shared `admin.partials.pagination` partial, and dense admin listings should enable its compact mode so the page links and compact summary render together in one row using the `from-to/total` format instead of a separate verbose summary line. The super-admin-only `Blocks` index is linked directly from the main sidebar under `Pages` and is intended for cross-site block maintenance, especially after imports or other bulk content changes. Its first compact filter set is limited to `Search`, `Site`, `Page`, `Block Type`, `Status`, and `Locale`. On the `Pages` index, the row-level `Page Details` action opens the standard admin modal pattern with grouped `Page` and `Status & Audit` cards and keeps only `Edit Page` plus `Open Public Page` when a public URL exists. Page Details also shows system-managed audit attribution for who created, last edited, published, archived, or submitted the page for review when that metadata exists; older, deleted-user, console, and imported records safely show `Not recorded` instead of guessing a user.

The `Admin -> Navigation` screen now uses the same query-driven modal pattern as newer admin screens instead of the old drawer. `Add Item` opens a modal for normal page or custom URL links. `Add Group` opens the same modal workflow preconfigured for a collapsible parent section. `Parent Group` only lists existing group items from the same site menu, so editors can clearly build docs-style sections such as `Patterns -> Overview / Dashboard Shell / Settings Shell`. Navigation item icons persist for normal links and groups, including group edits reopened through the edit modal. Public `Sidebar Navigation` blocks that read a selected navigation menu render those groups with the shipped `wb-nav-group` contract, keep normal links unchanged, automatically open a parent group when one of its child items is active, and load the CMS sidebar-navigation asset so those groups expand and collapse correctly on click.

`Admin -> System -> Icons` manages the install-level icon catalog used by admin pickers. The CMS stores labels, active state, sort order, categories, contexts, and keywords, while WebBlocks UI remains the source of shipped icon CSS classes and the manifest. The default sync source is `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.json`. Sync it with `ddev artisan icons:sync-webblocks-ui`, or override the source with `ddev artisan icons:sync-webblocks-ui --manifest=/path/or/url/webblocks-icons.json`. Navigation item and Sidebar Navigation icon selectors intentionally show only active catalog rows tagged for the `navigation` context; the full catalog remains on `System -> Icons`. Custom SVG upload or project-specific icon generation is not part of CMS core.

See `docs/getting-started.md` for the first-use workflow.

## Multisite Domains

- Public host resolution now prefers active `site_domains` records, then falls back to the legacy `sites.domain` value when needed, and only uses unknown-host fallback behavior where `cms.multisite.unknown_host_fallback` is intentionally enabled for local or compatibility scenarios.
- In the admin sidebar, `Sites` is a primary area directly under `Dashboard`, while `System -> Domains` owns public host and domain resolution management for sites.
- The site Domains screen stacks `Add Domain` first and `Assigned Domains` below it so both cards use the full admin content width. Assigned domain rows use compact action icons, and domain status, alias redirect behavior, primary-domain changes, and removal are managed through modal workflows instead of inline table forms.
- `System -> Domains` opens the current site's domain screen when the admin already has a site context. If no current site context is available across multiple accessible sites, it shows a compact site list with `Manage Domains` actions.
- In production, an unknown host should not silently render the default site. Point only the domains and subdomains you intend to serve at the CMS install.
- Each site can have one primary domain plus additional active alias domains. Canonical public URLs use the site's primary domain.
- Domain values are normalized lowercase hostnames only. Do not store protocols, paths, or query strings.
- Herne Panel or your server operator must own DNS, SSL, Nginx or virtual-host setup, and inbound server routing before a host reaches the CMS.
- WebBlocks CMS Domains only map an incoming host to a CMS Site, choose the primary canonical host, and optionally redirect alias hosts to that primary domain.
- Site export and import packages include domain metadata for inspection and portability, but imports skip conflicting live domains instead of taking them over automatically.
- Site clone clears copied live domains by default. Provide an explicit `target_domain` only when the clone should claim a new hostname.
- Internal domain automation endpoints live under `/admin-api/*` and are disabled unless `WEBBLOCKS_CMS_INTERNAL_API_TOKEN` is configured. Requests must send `X-WebBlocks-Internal-Token: <token>`.

## Documentation

- See the full documentation in the `docs/` directory:
- [Installation](docs/installation.md)
- [Getting Started](docs/getting-started.md)
- [Core Concepts](docs/core-concepts.md)
- [Users And Permissions](docs/users-and-permissions.md)
- [Editorial Workflow](docs/editorial-workflow.md)
- [Revisions](docs/revisions.md)
- [Operations](docs/operations.md)
- [Search](docs/search.md)
- [Updates](docs/updates.md)
- [Multisite](docs/multisite.md)
- [Localization](docs/localization.md)
- [Public Assets](docs/public-assets.md)
- [Renderer Contracts](docs/block-ui-renderer-contract.md)
- [Development Workflow](DEVELOPMENT.md)

## Project Layer

- Project-specific console commands belong under `project/`.
- Install-specific code should stay outside CMS core and inside `project/`.
- Release packages exclude `project/` so shipped artifacts contain reusable CMS product code only.
- Website-specific sync, import, migration, and seed workflows must not be added to CMS core.
- Project-layer WebBlocks UI website import payloads live under `storage/project/webblocksui.com`.
- These WebBlocks UI website files are project-specific migration assets, not CMS core release content.
- CMS core stays generic; native export/import-style payloads remain the portability mechanism for website content.
- WebBlocks UI docs imports now target the CMS default site by default through the payload convention `{ "target": "default_site" }`.
- Project-layer imports may create `HTML (Trusted)` blocks when a source page needs static trusted markup that should render exactly as imported, such as WebBlocks UI reference content.
- If the default site's docs-shell dependency pages do not exist yet, create or reconcile them with `ddev artisan project:webblocksui-setup-site`.
- Import the WebBlocks UI Architecture page with `ddev artisan project:webblocksui-import docs-architecture`.
- Import the WebBlocks UI Foundation page with `ddev artisan project:webblocksui-import docs-foundation`.
- Import the WebBlocks UI Layout page with `ddev artisan project:webblocksui-import docs-layout`.
- Import the WebBlocks UI Primitives page with `ddev artisan project:webblocksui-import docs-primitives`.
- Import the WebBlocks UI Icons page with `ddev artisan project:webblocksui-import docs-icons`.
- The Icons payload now preserves the source page's visual shipped-icon grid using shipped WebBlocks UI icon classes through one trusted static HTML block in the project-layer payload.
- Repair project-layer WebBlocks UI docs slot assignments and clean proven local debug artifacts with `ddev artisan project:webblocksui-repair`.
- The Architecture payload source is `https://webblocksui.com/docs/architecture.html` and the imported page metadata preserves the requested website path `/docs/architecture.html` while the current CMS route model serves the page at `/p/architecture`.
- The Foundation payload source is `https://webblocksui.com/docs/foundation.html` and the imported page metadata preserves the requested website path `/docs/foundation.html` while the current CMS route model serves the page at `/p/foundation`.
- The Layout payload source is `https://webblocksui.com/docs/layout.html` and the imported page metadata preserves the requested website path `/docs/layout.html` while the current CMS route model serves the page at `/p/layout`.
- The Primitives payload source is `https://webblocksui.com/docs/primitives.html` and the imported page metadata preserves the requested website path `/docs/primitives.html` while the current CMS route model serves the page at `/p/primitives`.
- The Icons payload source is `https://webblocksui.com/docs/icons.html` and the imported page metadata preserves the requested website path `/docs/icons.html` while the current CMS route model serves the page at `/p/icons`.
- Default local preview host: `webblocks-cms.ddev.site`.
- Full local preview URL: `https://webblocks-cms.ddev.site/p/architecture`.
- Foundation local preview URL: `https://webblocks-cms.ddev.site/p/foundation`.
- Layout local preview URL: `https://webblocks-cms.ddev.site/p/layout`.
- Primitives local preview URL: `https://webblocks-cms.ddev.site/p/primitives`.
- Icons local preview URL: `https://webblocks-cms.ddev.site/p/icons`.
- Before database-affecting import or repair work, create a safety dump such as `ddev export-db --file=before-webblocksui-docs-reimport-and-db-guard.sql.gz`.
- WebBlocks CMS blocks destructive database reset commands in normal local, development, and production environments. The guard blocks `migrate:fresh`, `migrate:reset`, `migrate:refresh`, and `db:wipe`, including normal console execution and `Artisan::call(...)` paths where Laravel emits the command start event.
- Use `WEBBLOCKS_ALLOW_DESTRUCTIVE_DB_COMMANDS=true` only when you intentionally need to bypass that safety guard.

## Developer Notes

- Refresh the product block catalog on an existing install with `ddev artisan db:seed --class=BlockTypeSeeder`. The seeder safely upserts product-owned block types such as `Header`, `Table`, `TOC`, `Quote`, and `Rich Text` without duplicating rows, and removes the legacy `heading` catalog row only when no live published blocks still reference it.
- In the admin layout, the mobile or narrow sidebar uses the standard WebBlocks UI sidebar contract, including a shell-local `data-wb-sidebar-backdrop`, so outside clicks close the sidebar without inline Blade scripts.
- Admin chrome product identity is fixed to `WebBlocks CMS`, `A modern block-based CMS`, and `WebBlocks CMS v{VERSION}` from `App\Support\WebBlocks`. System Settings and editable site fields do not change those labels.
- Public rendering ownership is split intentionally: page controls the outer shell (`default` or `docs`), slot name controls the public region wrapper semantics, and blocks render content inside those slot wrappers.
- `Header` is the canonical heading block. Its shared anchor is stored explicitly and reused for public `id` output plus same-page `TOC` links; `TOC` does not parse rendered HTML and does not depend on legacy `heading` blocks.
- Site-level public metadata now comes from the currently resolved site. `display_name`, `tagline`, favicon, social image, and SEO Defaults provide public `<head>` fallbacks by site and host context.
- Page-level SEO overrides live on `page_translations`, stay locale-aware, and affect public `<head>` metadata plus social metadata only.
- Metadata precedence is now: site label plus page translation SEO/title for the public title, page translation SEO override for description and keywords where applicable, site SEO defaults, then safe CMS fallback when no site or page context exists.
- Public page titles now default to `Site Label · Page Label`, using site `display_name`, then site `seo_title`, then site `name` for site context.
- Public search modal copy now names the resolved site being searched when a site label is available.
- Page-level SEO does not change CMS admin product identity, does not replace page body content, and is not treated as public search body content by default.
- `default` uses standard semantic wrappers such as `header`, `main`, `aside`, and `footer`. `docs` automatically maps header, sidebar, and main slots to the docs navbar, sidebar, and main wrappers.
- Existing pages remain site-scoped after creation. The normal `Edit Page` form shows the current site as read-only context and does not move pages between sites.
- Existing pages can also be copied through a dedicated `Duplicate page` admin action. Duplicate creates a new page and leaves the source page unchanged.
- Cross-site page moves are handled through a dedicated `Move to another site` admin action on the Edit Page screen. The normal `Save Changes` path still cannot change `site_id` directly.
- `super_admin` can duplicate pages into any site. `site_admin` and `editor` can duplicate only when they have access to both the source site and the target site.
- `super_admin` can move pages between any sites. `site_admin` can move pages only when they have access to both the source site and the target site. `editor` cannot move pages between sites.
- Duplicated pages always start as `draft`, even when the source page is published or in review.
- The duplicate workflow copies the page layout, public shell settings, translations, page slots, page-owned block tree, nested block order, block translations, and existing asset references.
- Duplicate does not copy navigation items, revision history, visitor/reporting rows, contact messages, or site transfer history.
- Duplicate can target the same site or another accessible site. Every copied locale must have a unique target path; conflicts block the duplicate instead of auto-renaming slugs.
- The duplicate screen collects the new default-locale title and slug plus explicit title/slug values for every additional copied locale so multilingual duplicates can be validated before writing.
- The duplicate screen now includes a Shared Slot compatibility summary for the currently selected target site whenever the source page uses Shared Slots.
- Shared Slots are site-scoped. Same-site duplicates keep existing `shared_slot_id` references as-is.
- Cross-site duplicate remaps only compatible same-handle Shared Slots from the target site. It never keeps a cross-site `shared_slot_id`.
- Missing or incompatible target Shared Slots still block cross-site duplication by default.
- The duplicate form now exposes `Disable incompatible Shared Slot-backed slots on the duplicated page` when relevant. When selected, only the duplicated page's affected Shared Slot-backed slots are written as `source_type = disabled` with `shared_slot_id = null`.
- The duplicate fallback does not create Shared Slots automatically and does not duplicate Shared Slot block trees into the page in this version.
- The page move workflow preserves the page id, workflow state, layout, translations, slots, page-owned blocks, block translations, ordering, and page revisions. It does not duplicate the page or block tree.
- Target-site path conflicts block the move. The first version does not auto-rename slugs or paths to resolve conflicts.
- Shared Slot references must already be compatible on the target site. Matching same-handle Shared Slots are remapped when they satisfy the existing site, shell, active-state, and slot-name compatibility rules. Missing or incompatible target Shared Slots block the move.
- Duplicate keeps same-site Shared Slot references as-is. Cross-site duplicate remaps only to compatible same-handle Shared Slots on the target site; missing or incompatible Shared Slots block the duplicate.
- Page-linked navigation rows keep the moved page link valid by moving those linked items into the target site scope, but broader navigation review remains manual after the move.
- Export / Import and Site Clone remain the site-level portability tools. They preserve page-level `Public Shell` settings such as `Docs` alongside pages, slots, blocks, Shared Slots, and Shared Slot-backed page slot assignments. `Move to another site` changes ownership of one existing page in place, while `Duplicate page` creates a new page copy inside the same CMS install.
- Shared Slots participate only at the slot-content layer. When a page slot source is `shared_slot`, the referenced Shared Slot block tree renders inside the resolved page slot wrapper if site scope matches and any optional Shared Slot shell or slot-name constraints are compatible. Invalid or cross-site shared-slot references render no shared content.
- The page editor now owns slot source assignment. Editors who can edit the page in its current workflow state can switch a slot between `page`, `shared_slot`, and `disabled`. Selecting a Shared Slot requires the same site, active status, and compatibility with the page shell and slot name.
- Shared Slots are managed under the site-level admin navigation alongside Pages, Navigation, and Media. `super_admin` can manage Shared Slots for all sites, `site_admin` can manage Shared Slots for assigned sites, and editors can access Shared Slot block editing within assigned sites using the same draft-only content-editing rule used for page content.
- Shared Slots now have their own revision history separate from page revisions. Shared Slot revisions capture Shared Slot metadata plus the reusable block tree behind the Shared Slot. Restoring one keeps the same Shared Slot id and existing `page_slots.shared_slot_id` references, so the restored Shared Slot immediately affects every page that uses it.
- Page records now store nullable audit references for `created_by_user_id`, `updated_by_user_id`, `published_by_user_id`, `archived_by_user_id`, and `review_requested_by_user_id`. These are system-managed audit fields, not editor-facing form inputs.
- Page revisions now store nullable actor plus compact `source` and `event` metadata so revision history can distinguish normal admin edits from console, restore, or project-import style workflows when callers provide that context.
- Shared Slot revisions follow the same actor plus `source` and `event` pattern, and Shared Slots themselves now store nullable created/updated audit user references.
- Deleting a referenced user does not delete pages, Shared Slots, or revisions. Audit foreign keys use null-on-delete, and admin UI falls back to `Not recorded` for deleted or unknown actors.
- Console or project import workflows do not fake authenticated users. They can leave audit user ids null and use workflow-specific sources such as `console` or `project_import` where available.
- Shared Slot revision viewing follows the same site-scoped access model as page revisions: `super_admin` and `site_admin` can view and restore within allowed sites, while editors can view but not restore.
- Shared Slot deletion is guarded. If any `page_slots.shared_slot_id` still references a Shared Slot, deletion is blocked and references remain intact.
- Shared Slots now travel with site portability tools. Site export/import packages include Shared Slot metadata plus the hidden internal source-page block tree, translations, and media references needed to rebuild the reusable Shared Slot in the target site. Page slots that use Shared Slots are exported by Shared Slot handle and remapped to the target-site Shared Slot during import or clone instead of keeping source database IDs.
- Shared Slot revision history does not travel with export/import or site clone. That matches the current page-revision portability boundary and keeps site transfer packages focused on live site content instead of editorial history.
- Hidden Shared Slot source pages remain an internal implementation detail. They are excluded from ordinary page admin listings, ordinary site page totals, normal exported page payloads, and public route resolution even though their block records are still used internally to preserve the existing block editor, translation, and asset flows.
- Search V1 is a core CMS feature backed by the derived `public_search_index` table. It indexes only published public pages, scopes rows by site and locale, includes compatible Shared Slot content in the consuming page record, and excludes hidden Shared Slot source pages from standalone results.
- Public search now uses a modal-first enhanced UX from the `Header Actions` search trigger when JavaScript is available, while `/search?q=term` and `/{locale}/search?q=term` remain the fallback and direct-link routes.
- Public modal results load from the locale-aware JSON endpoints `/search.json?q=term` and `/{locale}/search.json?q=term`.
- The first-class `Search Form` block renders a semantic GET search form that targets the current site's resolved search route and stores translated label, placeholder, and button text in block translation rows.
- Super admins can review derived search status and run a non-destructive rebuild from `Admin -> Maintenance -> Search`.
- `Admin -> System -> Settings` is the compact install-level settings screen for locale, timezone, privacy, version, and environment information. `Maintenance` remains reserved for operational tools such as Visitor Reports, Search, Backups, Export / Import, and Update.
- Rebuild the derived search index safely with `ddev artisan search:rebuild`, optionally scoped by `--site`, `--locale`, or `--page`.
- If the search table does not exist yet on an install, run `ddev artisan migrate` before `ddev artisan search:rebuild`.
- Search index rows are derived runtime data. They can exist in environment-level backups, but they are not required content payloads for export/import portability because rebuild can recreate them from pages, blocks, translations, and Shared Slots.
- Destructive database reset commands remain guarded. Search rebuild does not require `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe`.
- Generic public block wrappers are only for simple non-root-owning content blocks. Layout/root-owning blocks such as `Section`, `Container`, `Grid`, `Cluster`, `Card`, `Header`, and `Content Header` own their real WebBlocks UI root markup and carry their public block type metadata on that root instead of receiving an extra outer wrapper.
- `Code` blocks render as escaped plain `<pre><code>` output without the old card chrome or a visible language label. Language metadata may still be stored and exposed only as a sanitized `data-language` attribute on `<code>`.

## Build Artifacts

- The repository keeps `dist/` referenced as release-output space in the GitHub release workflow, while application layouts and docs also reference the WebBlocks UI package `dist` bundles loaded from CDN or published package paths.
- The local repo `dist/` path is ignored by git and is not part of normal application source.

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
