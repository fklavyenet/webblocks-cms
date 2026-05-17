# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel-based, block-driven CMS for managing sites, pages, media, navigation, and editorial publishing from one admin interface. It includes install-level administration for users, updates, backups, site transfer tools, and system settings.

## Feature Summary

- block-based page building with reusable layouts, slots, and blocks
- multisite and locale-aware page management
- fixed WebBlocks CMS product identity in admin chrome, with public site identity managed per site
- admin-only Project Name and Project Tagline settings so multiple installs are easier to distinguish without changing public metadata
- install-level admin listing rows-per-page setting so paginated admin listings can use custom defaults such as `10` or `12` without affecting public pagination
- install-level user management with `super_admin`, `site_admin`, and `editor` roles
- editorial workflow for pages with review and publishing states
- database-backed public site search scoped by site and locale, with a header-triggered modal UX, `/search` fallback page, Search Form block support, and a System > Search rebuild screen
- page revisions and in-place restore with actor, source, and event metadata when available
- relational page-scoped CSS and JS assets stored in `page_assets`, rendered only on the owning public page, and managed from the `Edit Page -> Page Management -> Assets` tab through compact rows plus Add/Edit modals
- media library and site-scoped navigation management
- contact forms that store submissions first, then attempt synchronous email notification with admin-visible delivery status and failure details for saved messages
- install-level icon catalog management under `System -> Icons`, with WebBlocks UI manifest sync, catalog metadata editing, and filtered navigation-only icon pickers in admin forms
- site-scoped primary domains and alias domains for one-install multi-domain public routing
- primary `Sites` admin navigation near `Dashboard`, with site-domain management grouped under `System -> Domains`
- install wizard for first-run setup
- system updates, backups, site export/import tools with a direct Sites-list export shortcut, preserved navigation item icons, and package-based Site Promotion workflows
- site-level Branding and SEO Defaults with public `<head>` fallback metadata and favicon support, plus locale-aware page-level SEO overrides on page translations
- relational site-scoped `site_variables` with controlled `{{ site.variable_key }}` public token replacement, tabbed `Edit Site` sections, and portability through site clone and site export/import
- site-scoped Shared Slots that can render reusable block trees publicly inside existing page slot wrappers, can be managed from the admin, can be assigned per page slot from the Edit Page screen, now have dedicated Shared Slot revision history and restore, and participate in site export/import and site clone workflows
- managed install-level Page Layouts with validated public body classes, relational Page Layout Slots, Edit Page layout-slot comparison, and an explicit Add Missing Layout Slots workflow, while pages still store the selected layout handle on `public_shell` for backward compatibility
- a system-owned `Navbar` primitive block for reusable public header wrappers, plus composable `Navbar Brand` and `Navbar Navigation` blocks that can be placed anywhere inside a Navbar descendant tree, with `Cluster` as the reusable horizontal distribution primitive for navbar rows and other grouped layouts
- super-admin global Blocks index under `Pages` for cross-CMS block maintenance with compact `Search`, `Site`, `Page`, `Block Type`, `Status`, and `Locale` filters
- Pages index filters and sort state persist across Edit Page, slot editor, translation editor, and save flows so editors can return to the same filtered list context without rebuilding it manually
- the `Block Types` modal in the slot editor now keeps all block lists in `Name A-Z` order, limits filtering to search plus tab state, shows a header count badge for the current picker result set, only shows `Reset` after active search or tab changes, and keeps the search card visible while long result sets scroll inside the modal
- Pages listing card headers now include `Import Page`, a first-class admin modal workflow for creating one new draft page from a documented single-page JSON payload without using project-specific commands

## Installation

For a fresh install, first get the source code locally:

```bash
git clone https://github.com/fklavyenet/webblocks-cms.git
cd webblocks-cms
git remote set-url --push origin DISABLED
```

If you already created an empty target directory, use `git clone https://github.com/fklavyenet/webblocks-cms.git .` and then run `git remote set-url --push origin DISABLED` before continuing. CMS installations consume upstream releases and updates, but they must not publish or push back to the CMS upstream repository.

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
5. If you already have a compatible single-page JSON payload, use `Admin -> Pages -> Import Page` to import one new page into the selected site as draft. This page-scoped workflow is separate from the site-level `Export / Import` tool.
5. Build page structure with `Section`, `Container`, `Cluster`, or `Grid`, then add `Header`, `Plain Text`, `Rich Text`, `Button Link`, `Card`, `Stat Card`, or `Breadcrumb` blocks inside that layout tree. `Plain Text` is for plain body copy only. `Rich Text` is for safe inline formatting such as bold, italic, inline code, links, paragraphs, and simple lists; keep headings, layouts, buttons, media, tables, and raw HTML composition as dedicated first-class blocks or features. `Code` is for multi-line escaped snippets and should not be replaced with Rich Text. Rich Text is edited visually in the admin through a small dependency-free safe HTML editor. Stored Rich Text content is a restricted safe HTML fragment, and public output still renders through the shipped WebBlocks UI `wb-rich-text` primitive using `wb-rich-text wb-rich-text-readable`. Selected description fields still support safe inline code formatting with backticks. Use `Breadcrumb` for header and context bars, and use `navigation-auto` only for actual menus. `Page Layout` is the admin-facing name for the page-level outer wrapper concept. The stored compatibility field remains `public_shell`, while managed Page Layout records now own optional public body classes plus relational Page Layout Slots that drive slot wrapper resolution from the page layout and slot name so blocks stay focused on content instead of shell markup. `Default Layout` is for regular public pages. `Docs Layout` maps header, sidebar, main, and footer regions into the docs-oriented wrapper structure automatically. Shared Slots now render publicly as reusable site-scoped slot block trees inside those existing slot wrappers when the slot source points at a compatible Shared Slot. Shared Slots are dynamic references, not copied templates. Shared Slots can now be created and managed from the top-level admin navigation, including editing the Shared Slot block tree with the same block editor patterns used for page slots. Shared Slots can optionally be constrained to a `Page Layout`; the stored compatibility field on the Shared Slot remains `public_shell` for backward compatibility, empty remains generic, and a selected Page Layout requires exact handle matching. On the Edit Page screen, each slot source can now be set to `Page Content`, `Shared Slot`, or `Disabled`. Switching away from page-owned content does not delete existing page-owned blocks, so editors can safely switch back later. For card actions, prefer `Card > Cluster > Button Link`; the legacy single card action fields remain available as a fallback.
   `Card` can now be used for editable image cards inside `Grid`. Selected media is optional, selecting media is enough to render the card image, clearing media removes it, blank or legacy `none` image placement falls back to `top`, the figure renders inside `.wb-card-body`, shared image placement and alignment stay canonical, image alt text and caption are locale-aware, existing text and action behavior still works, nested `Cluster` or `Button Link` footer composition remains supported, the legacy single-action fallback remains available, and existing no-image cards stay valid.
6. For a docs-style context bar, add `Breadcrumb` and `Header Actions` to the `Header` slot. `Header Actions` renders system-owned theme utilities such as color mode, theme preset controls, accent controls, and the public search trigger without requiring raw HTML blocks. Public mode and accent behavior uses the shipped WebBlocks UI `data-wb-*` runtime, while CMS keeps only the separate public search modal runtime as CMS-owned JS. For a site header or reusable Shared Slot header, use `Navbar` as the primitive wrapper block, then add child blocks such as `Container`, `Cluster`, `Navbar Brand`, `Navbar Navigation`, `Header Actions`, or `Search Form` inside it. `Navbar` renders only `nav.wb-navbar` and its child blocks, does not add an automatic container, and keeps `Position` as its only built-in setting. `Navbar Brand` and `Navbar Navigation` must be placed somewhere inside the Navbar tree, but they do not need to be direct children of Navbar. Header slots are layout-neutral by default and do not force `wb-stack`; use `Container`, `Cluster`, or `Stack` inside the slot when you need layout. For horizontal header composition, set the `Container` flow to `None` and use `Cluster` settings to control width, distribution, alignment, wrapping, and gap.
7. For a docs-style sidebar, add a `Sidebar` slot and set the page `Page Layout` to `Docs Layout`, then add `Sidebar Brand`, `Sidebar Navigation`, and `Sidebar Footer` as top-level sidebar blocks. Inside `Sidebar Navigation`, add `Sidebar Nav Item` and optional `Sidebar Nav Group` blocks, or point the block at the site-scoped `Docs` navigation menu so admin-managed `Add Group` sections render as collapsible docs sidebar groups.
8. Publish the page as a `site_admin` or `super_admin`.
9. Open the public URL or preview link to confirm the live result.

For common editorial choices, `Code`, `Table`, `TOC`, `Quote`, `Hero`, `Columns`, and `CTA` are available as first-class block picker options and remain editable from the slot editor. Use `Header` as the canonical heading or title block, including optional shared anchor IDs for direct links and TOC targets. `TOC` renders links from explicit anchored `Header` blocks on the same public page, including nested headers inside layout wrappers such as `Section`, `Container`, `Grid`, `Cluster`, or `Card`. `Hero` and `CTA` keep promo copy locale-owned while managed child button URLs stay shared. `Columns` keeps intro copy locale-owned while child `Column Item` URLs stay shared. `Code`, `Table`, `Quote`, `TOC`, and `HTML (Trusted)` are content-style blocks, not general-purpose container parents: historical child rows are preserved safely, but new arbitrary child placement is not offered and public rendering ignores those child trees. Do not use the legacy `Heading` block type.

`Feature Grid` and `Feature Item` are also now published source-backed block types, but they remain transitional compatibility aliases over the shared Columns card presentation. `testimonial` and `stats` still render through Quote or Columns alias paths and are not treated as standalone published core contracts. `Image`, `Gallery`, `Download`, `File`, `Video`, and `Audio` are also shipped first-class block types. They use the same core catalog sync path, expose product-owned admin forms, and render publicly as media-owning blocks rather than arbitrary child containers.

`Gallery` is now a media-collection block. Editors manage gallery media as compact `Gallery Items` rows in the block editor instead of the older large selected-assets grid. Gallery selection order and presentation behavior stay shared, while per-item alt text, caption, overlay title, and overlay text are locale-owned through `block_gallery_item_translations`. The `Add Gallery Items` media picker mounts as a plain WebBlocks modal under the shared `#wb-overlay-root`, so WebBlocks UI `v2.7.6` stays responsible for stacked modal interactivity without the older CMS-owned wrapper or z-index stack. Public Gallery variants now differentiate clearly: `Grid` keeps regular equal cells, `Masonry` uses a staggered CSS-column layout with natural image heights, and `Collage` keeps the featured-first composition. The compact picker keeps the list-style row UI, defaults `Kind` to `Image`, uses the normal modal body as the single scroll container, keeps the filter card sticky at the top of that scrollable body while long result rows pass underneath, and renders a real compact empty or error state instead of leaving the picker looking stuck when no rows match or media rows fail to render. Gallery no longer owns a public intro heading or paragraph. The old `Gallery Title` and `Description` fields are no longer normal Gallery admin fields, and any legacy stored values may still exist in older content but are ignored by public rendering. When a gallery needs section headings or explanatory copy, use `Content Header` plus `Plain Text` or `Rich Text` before the Gallery block.

`HTML (Trusted)` is also available in the block picker as an advanced escape hatch for `super_admin` users only. Use it only for deliberate static trusted markup that cannot be represented cleanly with first-class blocks. For normal editorial work, prefer `Rich Text` for safe formatted copy and `Code` for escaped code samples. When trusted HTML includes shipped WebBlocks UI gallery viewer modal markup, the public page shell hoists that overlay content into the single shared page-owned `#wb-overlay-root` so gallery triggers can keep using the shipped viewer contract without rendering duplicate overlay roots.

On the Edit Page screen, Page Management and slot structure are managed separately. Page Management owns `Overview`, `Settings`, `Assets`, and `Layout Slots`, while the separate `Slots` card stays focused on direct page slot/source/block operations. Slot additions are available from a compact `Add Slot` dropdown, and each slot keeps a compact source summary in the list with `Manage Source` modal settings for `Page Content`, `Shared Slot`, or `Disabled`. Shared Slot choices are limited to active compatible Shared Slots from the same site. When a slot uses a Shared Slot or is Disabled, the page-owned block tree is preserved and clearly labeled as not currently rendered. The `Layout Slots` tab shows the `Page Layout Slots` comparison card that reports which Layout Slots are already present as Page Slots, which are missing, which extra Page Slots are being preserved for safety, plus current Disabled and Shared Slot-backed states. The `Add Missing Layout Slots` action is explicit and safe: it only creates missing Page Slots from the selected Page Layout and never deletes existing Page Slots, blocks, Shared Slot assignments, disabled states, revisions, or translations.

New page creation now uses the selected Page Layout's active managed Page Layout Slots first, with safe legacy fallback behavior only when managed slot definitions are unavailable. Changing a page's `Page Layout` on normal save does not silently rewrite slots; editors review the updated comparison card and choose `Add Missing Layout Slots` only when they want to add the newly missing Layout Slots.

`Edit Page -> Page Management` now groups `Overview`, `Settings`, `Assets`, and `Layout Slots` into one calmer top-level admin card. `Overview` owns page status, published context, and workflow actions. `Settings` owns the editable page form and the only `Save Changes` and `Cancel` footer. `Assets` is an advanced page-scoped feature for local `/site/...` CSS and JS files only and keeps its own compact asset card with header-owned `Add CSS asset` and `Add JS asset` modal actions. `Layout Slots` owns the `Page Layout Slots` comparison card and the `Add Missing Layout Slots` action so layout alignment is managed separately from direct slot editing. In V1, Page Assets are stored relationally in `page_assets`, not in `pages.settings`, only `super_admin` users can change them, CSS renders in the public `<head>`, JS renders in the public `<head>` with `defer`, and the configured files are loaded only on the owning public page. Public block renderers must not emit inline scripts. CMS core assets now live under `public/cms/`. Site-level overrides live under `public/site/{site_handle}/css/site.css` and `public/site/{site_handle}/js/site.js`, while page-level overrides live under `public/site/{site_handle}/pages/{page_slug}/page.{css,js}`. Site handles are canonical lowercase hyphenated identifiers such as `ui-webblocksui-com`. `public/storage` is the Laravel public storage symlink for `storage/app/public` and is separate from `/site/...` override assets. Legacy public named JS rows saved with `body_end` remain backward compatible, but public named JS output is normalized to `<head defer>`. When site Export / Import runs with `Include media files`, referenced `/site/...` physical files are packaged and restored too. Without that option, the page asset rows still transfer but the target install must already have those public files.

In the admin slot block tree, block deletion now uses a WebBlocks modal instead of a browser confirm dialog. The safe default still deletes only the selected block, which preserves the existing child promotion behavior when a wrapper such as `Section` or `Container` is removed. Editors can explicitly opt into `Also delete all nested child blocks` when they want to remove an entire nested subtree. The modal shows the selected block, whether it has children, direct child count, and total descendant count, and warns that recursive deletion is only recoverable through revision or backup restore flows.

On the Edit Site screen, settings are split into `Site`, `Locales`, `Branding`, `SEO Defaults`, and `Variables` tabs. `Manage Domains` stays a separate header action instead of a body tab or card. Public-facing `Branding` and `SEO Defaults` live on the site itself. Use `display_name`, `tagline`, favicon, social image, and site-level SEO defaults there for public metadata fallbacks. Locale-aware page-level SEO overrides now live on each page translation, where editors can override title, description, keywords, and Open Graph fields for one locale without changing the fixed WebBlocks CMS admin product identity.

Site `Handle` uses the canonical filesystem-safe CMS format: lowercase ASCII-safe text with hyphens as the only separator. Creating a new site auto-suggests the handle from `Name`, but once an admin edits `Handle`, later name edits do not overwrite it.

Site Variables are stored relationally in `site_variables`, not JSON. They are intended for simple reusable public text tokens such as support email addresses, repeated product labels, or legal copy. The only supported token format is `{{ site.variable_key }}` with optional inner whitespace. Unknown tokens, disabled variables, invalid keys, and non-site tokens remain unchanged. Replacement happens only in shared public rendering and public search indexing; admin forms always keep the raw stored token text.

In the admin slot editor, the Edit Slot Blocks list stays structure-focused as a compact one-row-per-block table with block type, a single primary summary, a dedicated children-count column, status, and actions. The Block Picker now opens with a default `Common` shortcut tab and additional `Layout`, `Content`, `Navigation`, `Advanced`, and `All` tabs so larger catalogs stay navigable without one long mixed list. `Advanced` only appears when the current user has eligible advanced block types such as `HTML (Trusted)`. Search works across the full eligible picker catalog instead of only the active tab, sort still applies within the currently visible tab or search result set, reset returns the picker to the default `Common` tab, and the modal keeps the same compact shared admin filter toolbar pattern. On narrow screens the table remains one-row-per-block and scrolls horizontally instead of collapsing labels into vertical letter stacks. Full content should be edited in the block edit modal or block edit page instead of being previewed in the slot list.

When a slot already contains blocks, the slot editor header now also exposes `Delete All Blocks`. This action is scoped to the current page slot or current Shared Slot only, requires explicit confirmation, shows top-level and nested block counts before submit, and records the change through the normal revision history flow.

Pages index list state is now preserved through the main editorial loop. When editors open a page, slot editor, translation form, or page-assets modal from a filtered Pages list, the admin keeps the same safe Pages return URL so `Back to Pages` and later save redirects return to the same list context.

The slot editor block picker follows the published block catalog directly. `Table`, `TOC`, `Quote`, and `Header` appear when their catalog rows are published. The legacy `Heading` catalog row is removed rather than kept hidden, so it does not appear in normal picker or editor availability.

`Admin -> System -> Settings` now also owns Project Identity plus the default admin listing pagination size. `Project Name` and `Project Tagline` are admin-only context labels used in the topbar and admin browser titles so teams can distinguish one install from another. `Admin listing rows per page` controls the default number of rows shown on paginated core admin listing screens, including `Media`, defaults to `15`, accepts custom numeric values such as `10` or `12`, and does not affect public pagination. These settings do not change the fixed WebBlocks CMS sidebar brand or version footer, and they do not affect public site metadata, favicon, SEO defaults, search scope, or locale-aware page metadata.

Admin index and listing screens such as `Block Types`, `Blocks`, `Pages`, `Media`, `Contact Messages`, `Users`, `Sites`, `Shared Slots`, and `Backups` should use the shared compact listing filter toolbar partial at `resources/views/admin/partials/listing-filters.blade.php` whenever the screen has real list-changing filters. The contract is: `Search` stays first on the far left and grows to fill the remaining horizontal space, `Site` or other context selectors come immediately after Search, then the remaining compact select or input filters follow, and `Apply` or `Reset` actions stay right-aligned on the same toolbar row on wide screens. Page headers should stay focused on title, count, and short description or context, while list-creation actions such as `New`, `Add`, `Upload`, `Clone`, or `Create backup` should live in the relevant listing card header instead of the page header. When a listing uses both page-header and card-header count badges, the page-header badge must show the total record count in the screen's base scope while ignoring active list filters, and the listing-card badge must show the filtered result count for the currently visible list. Admin list pagination should use the shared `admin.partials.pagination` partial, and dense admin listings should enable its compact mode so the page links and compact summary render together in one row using the `from-to/total` format instead of a separate verbose summary line. The super-admin-only `Blocks` index is linked directly from the main sidebar under `Pages` and is intended for cross-site block maintenance, especially after imports or other bulk content changes. Its first compact filter set is limited to `Search`, `Site`, `Page`, `Block Type`, `Status`, and `Locale`. On the `Pages` index, the row-level `Page Details` action opens the standard admin modal pattern with grouped `Page` and `Status & Audit` cards and keeps only `Edit Page` plus `Open Public Page` when a public URL exists. Page Details also shows system-managed audit attribution for who created, last edited, published, archived, or submitted the page for review when that metadata exists; older, deleted-user, console, and imported records safely show `Not recorded` instead of guessing a user.

`Admin -> Media` now uses `Media` consistently as both the admin-facing and canonical active internal concept. Legacy `Asset` names remain only in compatibility wrappers, historical migrations, and legacy payload normalization paths. The `Media Library` list keeps preview as the eye-icon modal action, but the media title and pencil icon both open `Edit Media: {title}` with a safe return URL back to the current filtered list. Its compact filter toolbar keeps `Search`, `Kind`, and `Usage`, and now also supports `Sort by` plus `Direction` so editors can safely reorder the shared media list by updated date, created date, title, filename, kind, folder, or real usage count without leaving the standard one-row admin listing pattern. On the edit screen, `Preview` and `Usage` remain visible as read-only context cards, `Media Information` owns editable metadata and folder assignment, read-only `File Details` are available from a modal with the copy-public-URL affordance, and delete remains available from Media list actions.

On `Admin -> System -> Block Types`, the compact filters now separate metadata from live usage. `Support` filters capability and content-source metadata such as system-generated vs user-authored behavior plus admin, render, or container support, while `Usage` filters actual block usage counts so admins can compare used and unused block type rows on an install. The index pencil action now follows the shared query-driven admin modal pattern, so install-specific block types open `Edit Block Type: {Name}` in a modal and save back to the same filtered or paginated list context. The direct edit route remains available as a no-JavaScript fallback and uses the same named heading. The same index now also exposes a read-only `Block Type Contract` modal per row so admins can inspect shipped contract details without editing schema, storage, translation ownership, or renderer behavior.

The `Admin -> Navigation` screen now uses the same query-driven modal pattern as newer admin screens instead of the old drawer. `Add Item` opens a modal for normal page or custom URL links. `Add Group` opens the same modal workflow preconfigured for a collapsible parent section. `Parent Group` only lists existing group items from the same site menu, so editors can clearly build docs-style sections such as `Patterns -> Overview / Dashboard Shell / Settings Shell`. Site and menu selection now live in the shared compact admin filter bar, while `Add Item` and `Add Group` stay with the selected navigation card context. Navigation item icons persist for normal links and groups, including group edits reopened through the edit modal. Public `Sidebar Navigation` blocks that read a selected navigation menu render those groups with the shipped `wb-nav-group` contract, keep normal links unchanged, automatically open a parent group when one of its child items is active, and load the CMS sidebar-navigation asset so those groups expand and collapse correctly on click.

`Admin -> System -> Icons` manages the install-level icon catalog used by admin pickers. The CMS stores labels, active state, sort order, categories, contexts, and keywords, while WebBlocks UI remains the source of shipped icon CSS classes and the manifest. This CMS release pins WebBlocks UI CDN assets to `v2.7.6` for stable runtime behavior, and the default icon sync source now matches that pinned release: `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@v2.7.6/packages/webblocks/dist/webblocks-icons.json`. Sync it with `ddev artisan icons:sync-webblocks-ui`, or override the source with `ddev artisan icons:sync-webblocks-ui --manifest=/path/or/url/webblocks-icons.json` for local development, testing, or a different pinned URL. Navigation item and Sidebar Navigation icon selectors intentionally show only active catalog rows tagged for the `navigation` context; the full catalog remains on `System -> Icons`. Custom SVG upload or project-specific icon generation is not part of CMS core.

Admin overlays continue to use the shared `#wb-overlay-root`. With the pinned WebBlocks UI `v2.7.6` runtime, stacked admin modals and pickers follow the shared overlay stack contract: nested overlays render above parent overlays, WebBlocks UI owns backdrop visibility, z-index, pointer lifecycle, Escape or outside-click behavior, and topmost interactivity, normal close controls still work through titlebar close or dismiss buttons, dirty admin forms still intercept `wb:overlay:close-request` only when closing would discard unsaved changes, and those dirty-close flows now use a stacked WebBlocks confirmation modal with `Keep editing` and `Close without saving` actions instead of the browser confirm dialog. The CMS no longer hides, reorders, or pointer-manages runtime-owned dialog backdrops.

The Gallery block editor keeps its current stacked overlay flow, but `Add Gallery Items` now uses a compact media picker result list instead of large cards. Results stay under `#wb-overlay-root`, open as a normal WebBlocks UI modal without CMS-owned shell sizing or surface overrides, keep search plus folder and kind filters, default `Kind` to `Image`, keep the modal body as the only scroll container, keep the filter card visible at the top of that body with a sticky CMS-scoped rule, show a small thumbnail with title, filename, folder, kind, and `Select` in one row, keep selected rows visibly selected, preserve the existing `Add Selected` flow back into the Gallery item table, keep natural compact row height even in long result lists, and show a real compact empty or error state instead of lingering placeholder rows.

See `docs/getting-started.md` for the first-use workflow.

## Multisite Domains

- Public host resolution now prefers active `site_domains` records, then falls back to the legacy `sites.domain` value when needed, and only uses unknown-host fallback behavior where `cms.multisite.unknown_host_fallback` is intentionally enabled for local or compatibility scenarios.
- In the admin sidebar, `Sites` is a primary area directly under `Dashboard`, while `System -> Domains` owns public host and domain resolution management for sites.
- The site Domains screen keeps `Add Domain` as a modal action in the `Assigned Domains` card header. Assigned domain rows use compact action icons, and domain status, alias redirect behavior, primary-domain changes, and removal are managed through modal workflows instead of inline table forms.
- `System -> Domains` opens the current site's domain screen when the admin already has a site context. If no current site context is available across multiple accessible sites, it shows a compact site list with `Manage Domains` actions.
- In production, an unknown host should not silently render the default site. Point only the domains and subdomains you intend to serve at the CMS install.
- Each site can have one primary domain plus additional active alias domains. Canonical public URLs use the site's primary domain.
- Domain values are normalized lowercase hostnames only. Do not store protocols, paths, or query strings.
- Herne Panel or your server operator must own DNS, SSL, Nginx or virtual-host setup, and inbound server routing before a host reaches the CMS.
- WebBlocks CMS Domains only map an incoming host to a CMS Site, choose the primary canonical host, and optionally redirect alias hosts to that primary domain.
- Site export and import packages include domain metadata for inspection and portability, but imports skip conflicting live domains instead of taking them over automatically.
- Site clone clears copied live domains by default. Provide an explicit `target_domain` only when the clone should claim a new hostname.
- Internal domain automation endpoints live under `/admin-api/*` and are disabled unless `WEBBLOCKS_CMS_INTERNAL_API_TOKEN` is configured. Requests must send `X-WebBlocks-Internal-Token: <token>`.

## Site Promotion

Site Promotion is the controlled one-way workflow for promoting site-owned content from a package into an existing target site.

- `Admin -> Sites` now includes a per-site `Export` row action that opens a modal, shows the selected site name and handle, and can include media or assets without leaving the Sites list.
- `Admin -> Maintenance -> Export / Import` now shows export history and import history together on one operational screen, with `Run Export` and `Run Import` actions in their listing card headers.
- `Admin -> Sites -> Promote` stays focused on applying an existing export or promotion package into a selected target site with dry run, safety backup, strategy selection, and preserve rules.

- Admin path: `Admin -> Sites -> Promote`
- V1 is package-based only and super-admin-only
- it is not raw database replication
- it is not a replacement for CMS core updates
- it can promote safe site identity fields, locale assignments, site variables, pages, page translations, page SEO fields, page slots, Shared Slots, Shared Slot block trees, navigation with optional item icon slugs, page assets, and optional physical media or `/site/...` public files
- it preserves install-level and runtime data such as users, sessions, jobs, backups, update history, visitor reports, contact submissions, live domains, environment configuration, internal tokens, and derived search rows
- dry run is required before apply
- apply creates a safety backup before content changes
- apply rebuilds the target site's derived public search index after promotion

Supported strategies:

- `additive_update`: create missing source content and update matching target content without removing extra target content
- `mirror`: create and update matching source content, then archive, deactivate, or remove absent target site-owned content where safe in V1

Typical use cases:

- local to staging delivery
- staging to live site-owned content promotion
- controlled content rollout between installs that must preserve runtime or environment-specific target data

CLI examples:

```bash
ddev artisan site-promotion:inspect storage/app/site-promotions/example.zip
ddev artisan site-promotion:dry-run storage/app/site-promotions/example.zip --target-site=ui-webblocksui-com --strategy=additive_update
ddev artisan site-promotion:apply storage/app/site-promotions/example.zip --target-site=ui-webblocksui-com --strategy=additive_update
```

## Documentation

- See the full documentation in the `docs/` directory:
- `AGENTS.md` is the compact AI and project working contract for repository-specific implementation rules.
- `DEVELOPMENT.md` defines the development and release workflow.
- `.editorconfig` and `pint.json` define the repository formatting standards.
- PHP files use 2-space indentation in this repository.
- `ddev composer format:test` checks Pint formatting and runs `scripts/check-php-indentation.php`, while `ddev composer format` applies Pint fixes.
- The indentation guard currently provides targeted enforcement until the historical PHP 4-space indentation drift is cleaned up in a dedicated baseline change.
- Repository-wide Pint cleanup is still a separate baseline task because the current codebase has historical formatting drift.
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
- [Page Layouts](docs/page-layouts.md)
- [Block Type Contracts](docs/block-type-contracts.md)
- [Renderer Contracts](docs/block-ui-renderer-contract.md)
- [Development Workflow](DEVELOPMENT.md)

## Project Layer

- Project-specific console commands belong under `project/`.
- Install-specific code should stay outside CMS core and inside `project/`.
- Release packages exclude `project/` so shipped artifacts contain reusable CMS product code only.
- Website-specific sync, import, migration, and seed workflows must not be added to CMS core.
- CMS core stays generic; native export/import-style payloads remain the portability mechanism for website content.
- Default local preview host: `webblocks-cms.ddev.site`.
- WebBlocks CMS blocks destructive database reset commands in normal local, development, and production environments. The guard blocks `migrate:fresh`, `migrate:reset`, `migrate:refresh`, and `db:wipe`, including normal console execution and `Artisan::call(...)` paths where Laravel emits the command start event.
- Use `WEBBLOCKS_ALLOW_DESTRUCTIVE_DB_COMMANDS=true` only when you intentionally need to bypass that safety guard.

## Developer Notes

- Refresh the core CMS block type catalog on an existing install with `ddev artisan block-types:sync-core`. The sync safely upserts product-owned block types such as `Header`, `Hero`, `Columns`, `CTA`, `Feature Grid`, `Table`, `TOC`, `Quote`, `Rich Text`, `Image`, `Gallery`, `Download`, `File`, `Video`, and `Audio` without duplicating rows or overwriting install-specific custom block types.
- In-app System Updates now run the core block type catalog sync automatically after migrations and before the installed version is marked complete, so existing installs stay aligned with the current shipped CMS block catalog during upgrades.
- Public CMS installations are update consumers, not upstream publishers. Keep `origin` fetchable if you want local git visibility, but disable push on installation working copies with `git remote set-url --push origin DISABLED`. Create CMS releases only from the real maintenance checkout, not from an installed site working copy.
- `BlockTypeSeeder` still uses the shared core block type sync path for fresh installs and keeps the existing legacy compatibility behavior, including drafting non-core rows and removing the legacy `heading` catalog row only when no live published blocks still reference it.
- `docs/block-type-contracts.md` documents the Phase 1 inventory, Phase 2 read-only admin visibility, and the Phase 3 contract-alignment fixes for currently published core block type contracts, including the shipped media and visual block set `image`, `gallery`, `download`, `file`, `video`, and `audio`, plus the Layout + Card group `section`, `container`, `grid`, `cluster`, `card`, and `content_header`. `ddev artisan block-types:contracts-audit` now exposes the same shipped contract detail in markdown and JSON that the admin contract modal reads from the shared registry.
- Gallery item translation rows now participate in page revisions, Shared Slot revisions, site clone, site export/import, site promotion, and safe site deletion. Site export packages also now include `data/block_gallery_item_translations.json`.
- `Navbar` keeps the persisted `sticky-navbar` handle for compatibility, but it now behaves as a primitive system navigation container. It renders only `nav.wb-navbar` and nested child blocks, keeps only `Position` (`static`, `sticky`, `fixed`) as a built-in setting, and does not add an automatic `Container`, brand wrapper, or generated menu markup.
- `Block::ownsPublicRoot()` now treats the persisted `sticky-navbar` slug as root-owning so Navbar output uses its real `<nav class="wb-navbar">` renderer root without an extra generic public wrapper.
- `Block::ownsPublicRoot()` also continues to treat `section`, `container`, `grid`, `cluster`, `card`, and `content_header` as root-owning so those renderers keep `data-wb-public-block-type` on their real public root instead of receiving a generic `wb-public-block` wrapper.
- `Navbar Brand` and `Sidebar Brand` now both use the same conservative shared URL contract: explicit saved brand URL first, otherwise the resolved site public home path when available, then `/` as the final safe fallback. Both support logo-only rendering when a logo exists, and their accessibility-only label stays shared so logo-only output still has a safe accessible name without forcing visible text.
- `Page Layout` is now a managed install-level CMS concept under `Admin -> System -> Page Layouts`. Pages still store the selected layout handle on `public_shell` for backward compatibility, while the runtime resolves that handle safely through the managed Page Layout catalog. Built-in `Default Layout` and `Docs Layout` remain backward compatible with existing pages, imports, exports, Shared Slots, and public rendering. Page Layouts now expose `Body Class` plus managed `Page Layout Slots` in the admin, while deprecated compatibility fields such as `shell_type`, `slot_schema`, and `wrapper_schema` stay out of the admin UI. Body Class is intended for layout-specific CSS on the public `<body>`, such as `layout-default` or `layout-docs`, and Page Layout Slots own the public wrapper element, id, and classes for each region. Wrapper classes can include hints such as `wb-sticky`, but sticky offset and stacking context remain site CSS concerns. When needed, define site-specific rules such as `header.wb-sticky { top: 0; z-index: 100; }` in `public/site/{site_handle}/css/site.css`. Advanced trusted layout HTML is limited to wrapper-adjacent layout markup only and must not be used for scripts. Edit Page compares current Page Slots against the selected Page Layout's managed Layout Slots and offers a safe `Add Missing Layout Slots` action that creates only missing slots. Custom V1 Page Layouts still reuse the existing `default` or `docs` runtime behavior conservatively, and Shared Slot compatibility remains conservative and exact by stored handle.
- Header slot wrappers are layout-neutral by default in the public shell. CMS does not force `wb-stack` around header content, so header composition should come from blocks inside the slot such as `Container`, `Cluster`, or `Stack`.
- `Container` is a width primitive first. Legacy containers still render stacked flow by default for compatibility, but editors can now set Container `Flow` to `None` when they need layout-neutral `wb-container` markup and compose spacing or alignment with child layout blocks instead.
- `Section`, `Container`, `Grid`, and `Cluster` remain layout primitives: shared layout settings and child structure stay canonical, while user-facing copy stays out of arbitrary settings JSON. `Card` remains a transitional content/container block with translated visible copy, shared URL/target/variant settings, optional shared selected media, locale-aware image alt/caption copy, shared image placement, alignment, and aspect settings, media-driven image visibility, the image figure rendered inside `.wb-card-body`, and the figure now consuming WebBlocks UI `wb-card-media` frame utilities so mixed-height card grids keep consistent media rhythm. Child footer actions and the older single-action fallback remain preserved intentionally. Existing no-image cards and older `image_position = none` card data remain backward compatible. `Content Header` keeps translated heading, intro, and meta copy, always renders its title as H1, and keeps only alignment shared.
- Legacy or transitional slugs such as `tabs`, `slider`, `menu`, `faq-list`, `showcase-list`, and `contact-info` are not part of the published core block contract set unless the shipped core catalog says so. Existing compatibility renderers remain supported conservatively, but new product work should prefer the documented first-class blocks instead of expanding those legacy paths.
- `Navbar Brand` and `Navbar Navigation` are the intended composable blocks for reusable header composition. They must be placed somewhere inside a `Navbar` tree, but they can sit under nested layout wrappers such as `Container` or `Cluster` instead of only as direct Navbar children. `Container` owns width, `Cluster` owns horizontal distribution, and `Stack` owns vertical flow. A recommended navbar pattern is `Navbar -> Container (Flow: None) -> Cluster (Width: Full, Justify: Between, Align: Center, Wrap: Nowrap) -> Navbar Brand + Cluster (Justify: End, Align: Center, Wrap: Nowrap) -> Navbar Navigation + Header Actions`. `Navbar Navigation` reads relational `navigation_items` rows from the selected CMS Navigation menu, keeps the desktop links visible in-place, and now renders a mobile burger toggle that opens the same menu content through the shipped WebBlocks UI dropdown contract. `Navbar Brand` renders logo and optional text using the shipped WebBlocks UI navbar classes, and now supports logo-only usage when a logo is present.
- `Sidebar Navigation` keeps the shipped WebBlocks UI `wb-sidebar-link` and `wb-nav-group-item` contracts. Manual `Sidebar Nav Group` children now render through the same `Sidebar Nav Item` semantics for href, target, icon, and active-state resolution instead of maintaining a separate nested-link drift path.
- Navbar styling should come from WebBlocks UI classes. CMS intentionally avoids creating a parallel navbar design system; where additional navbar primitives are needed, that work belongs in WebBlocks UI.
- Sticky navbar ownership is intentionally clean now: the page layout and header slot wrapper own page-level structure, while `.wb-navbar` owns sticky navbar behavior. CMS no longer emits `wb-cms-navbar--sticky` on either the header wrapper or the navbar.
- Header-to-main spacing belongs to the public shell wrapper, not the Navbar block. Keep Navbar primitive and adjust shell rhythm on the header or main slot boundary instead of adding navbar-specific margins.
- In the admin layout, the mobile or narrow sidebar uses the standard WebBlocks UI sidebar contract, including a shell-local `data-wb-sidebar-backdrop`, so outside clicks close the sidebar without inline Blade scripts.
- Admin chrome product identity is fixed to `WebBlocks CMS`, `A modern block-based CMS`, and `WebBlocks CMS v{VERSION}` from `App\Support\WebBlocks`. System Settings and editable site fields do not change those labels.
- Admin form actions belong in the owning card or modal footer, not a separate action-only card. Card footers and modal footers use the same placement: primary action first, cancel or secondary action second, and delete or destructive action last in a separate end-aligned danger group when present. Non-destructive footer actions start on the left, and page headers stay focused on navigation and context actions instead of form submit actions.
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
- The duplicate workflow copies the page layout, public shell settings, translations, page slots, page-owned block tree, nested block order, block translations, and existing media references.
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
- Export / Import and Site Clone remain the site-level portability tools. They preserve page-level `Page Layout` settings such as `Docs Layout` alongside pages, slots, blocks, Shared Slots, and Shared Slot-backed page slot assignments. `Move to another site` changes ownership of one existing page in place, while `Duplicate page` creates a new page copy inside the same CMS install.
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
