# WebBlocks CMS Core Boundary Audit

## 1. Executive summary

WebBlocks CMS already has a partial core/site separation, but it is not strong enough yet for a safe Craft-style updater.

The good news is that the runtime CMS mechanics are mostly recognizable as core: the page, slot, block, navigation, asset, contact-message, admin, and public rendering infrastructure are generic and reusable. The main risks are not in the engine itself, but in how the project currently bootstraps and ships content.

The largest boundary problem is that demo/starter content is treated as part of normal application setup. `database/seeders/DatabaseSeeder.php` seeds admin users, starter content, and the full showcase by default, while several migrations also mutate specific demo pages and demo-facing content. There is also demo metadata in core schema (`assets.demo_source_key`) and demo copy in shared public views.

Verdict: separation exists, but only partially. An updater would currently be risky because a product update cannot reliably distinguish core code/schema changes from demo content, seeded catalog data, and installation-specific site changes.

## 2. Classification by area

### Database and migrations

Classification: Mixed

Core:

- Generic schema tables are clearly core CMS structures:
  - `database/migrations/2026_04_08_000100_create_layouts_table.php`
  - `database/migrations/2026_04_08_000110_create_pages_table.php`
  - `database/migrations/2026_04_08_000120_create_blocks_table.php`
  - `database/migrations/2026_04_08_000130_create_navigation_items_table.php`
  - `database/migrations/2026_04_08_020000_create_page_types_table.php`
  - `database/migrations/2026_04_08_020010_create_layout_types_table.php`
  - `database/migrations/2026_04_08_020020_create_slot_types_table.php`
  - `database/migrations/2026_04_08_020030_create_block_types_table.php`
  - `database/migrations/2026_04_11_120000_create_asset_folders_table.php`
  - `database/migrations/2026_04_11_120100_create_assets_table.php`
  - `database/migrations/2026_04_11_140000_create_block_assets_table.php`
  - `database/migrations/2026_04_11_170000_create_page_slots_table.php`
  - `database/migrations/2026_04_17_090000_create_contact_messages_table.php`

Mixed:

- Several migrations seed or mutate catalog/content data instead of limiting themselves to schema changes:
  - `database/migrations/2026_04_11_170000_create_page_slots_table.php` seeds canonical slot types during migration.
  - `database/migrations/2026_04_17_090100_seed_contact_form_block_type.php` inserts a block type in a migration.
  - `database/migrations/2026_04_08_020100_add_catalog_foreign_keys_to_core_tables.php` backfills and creates catalog records from live content.

- Some migrations directly assume a specific seeded demo page exists and modify its block tree:
  - `database/migrations/2026_04_17_160000_upgrade_contact_page_to_contact_form_block.php`
  - `database/migrations/2026_04_17_161000_cleanup_legacy_contact_page_blocks.php`

- `database/migrations/2026_04_16_120000_add_demo_source_key_to_assets_table.php` adds demo-only identity to the core `assets` table. That is not general CMS schema; it is showcase management data.

### Seeders

Classification: Mixed

Core:

- Catalog seeders represent reusable CMS definitions:
  - `database/seeders/PageTypeSeeder.php`
  - `database/seeders/LayoutTypeSeeder.php`
  - `database/seeders/SlotTypeSeeder.php`
  - `database/seeders/BlockTypeSeeder.php`

Demo/Starter:

- `database/seeders/StarterContentSeeder.php` is clearly starter content. It creates `home`, `about`, `contact`, starter navigation, starter hero copy, starter footer copy, and CTA blocks.
- `database/seeders/FullShowcaseSeeder.php` is clearly demo/showcase content. It creates the full Northstar Labs site, legal pages, blog pages, pricing pages, case studies, demo assets, demo downloads, and demo navigation.

Mixed:

- `database/seeders/DatabaseSeeder.php` currently combines all of the following in the default seed path:
  - demo users (`admin@example.com`, `test@example.com`)
  - core catalog data
  - starter content
  - full showcase content

That means a normal `db:seed` operation is not just installing CMS defaults; it is installing a complete demo site.

### Models

Classification: Mostly Core

Core:

- Generic CMS/domain models are clearly core:
  - `app/Models/Page.php`
  - `app/Models/PageSlot.php`
  - `app/Models/Block.php`
  - `app/Models/BlockType.php`
  - `app/Models/NavigationItem.php`
  - `app/Models/Layout.php`
  - `app/Models/PageType.php`
  - `app/Models/SlotType.php`
  - `app/Models/Asset.php`
  - `app/Models/AssetFolder.php`
  - `app/Models/BlockAsset.php`
  - `app/Models/ContactMessage.php`

Site/Application data stored by core models:

- `Page`, `Block`, `NavigationItem`, `Asset`, and `ContactMessage` are core models that store site-specific content/data at runtime.

Mixed:

- `app/Models/Asset.php` includes `demo_source_key`, which is demo/showcase-specific state in a core model.
- `app/Models/Page.php` still defaults `page_type` to `'default'` on create, while the seeded catalog uses `'page'`, `'home'`, `'landing'`, etc. That is not a demo leak, but it does show catalog/runtime coupling is still uneven.

### Block system

Classification: Mixed

Core:

- The block engine itself is core:
  - `app/Models/Block.php`
  - `app/Http/Controllers/Admin/BlockController.php`
  - `app/Http/Controllers/Admin/BlockTypeController.php`
  - `resources/views/admin/block-types/*.blade.php`
  - `resources/views/admin/pages/slot-blocks.blade.php`
  - `resources/views/admin/pages/partials/*.blade.php`
- Public rendering resolves by slug via `Block::publicRenderView()` and admin editing resolves by slug via `Block::adminFormView()`. That is a proper generic mechanism.

Core but content-bearing definitions:

- `database/seeders/BlockTypeSeeder.php` defines reusable block types, including system types such as `page-title`, `page-meta`, `navigation-auto`, `related-content`, `auth-form`, and `cookie-notice`.

Mixed:

- The block type catalog is editable through admin routes (`routes/web.php`, `App\Http\Controllers\Admin\BlockTypeController`), but many block types are effectively product features shipped by the CMS. That makes ownership unclear: are block types product-managed core definitions, or site-local content model entries?
- `database/migrations/2026_04_17_090100_seed_contact_form_block_type.php` and `database/seeders/BlockTypeSeeder.php` both participate in block type definition, so block-type ownership is split across migrations and seeders.
- Some block definitions are generic, but many are really showcase-oriented examples of capability rather than minimal core necessities. Examples from `database/seeders/BlockTypeSeeder.php` include `pricing`, `cart-summary`, `checkout-summary`, `logo-cloud`, `team`, `faq-list`, `comments`, and `share-buttons`. They may still belong to core, but today they are shipped together with the showcase and not clearly separated from demo usage.

### Public rendering

Classification: Mixed

Core:

- Generic public page delivery is core:
  - `routes/web.php`
  - `app/Http/Controllers/PageController.php`
  - `app/Support/Pages/PublicPagePresenter.php`
  - `resources/views/pages/show.blade.php`
  - `resources/views/pages/partials/block.blade.php`
  - `resources/views/pages/partials/slot.blade.php`
  - `resources/views/pages/partials/slots/*.blade.php`
  - `resources/views/pages/partials/blocks/*.blade.php`

Site/Application:

- The actual `pages`, `blocks`, navigation trees, and uploaded assets rendered through this layer are site data.

Mixed:

- Shared public chrome still contains demo-specific copy:
  - `resources/views/pages/partials/slots/footer.blade.php` hardcodes `Demo installation for WebBlocks CMS.`
  - `resources/views/pages/partials/blocks/fallback.blade.php` uses demo text for `auth-form` and `cookie-notice` fallbacks such as `Sign in to the demo` and `This demo uses essential cookies only...`

- Shared brand/meta handling assumes shipped brand assets in `public/brand/*` via:
  - `resources/views/partials/head-meta.blade.php`
  - `resources/views/components/application-logo.blade.php`

That is acceptable for a distribution, but it is not yet separated into product-brand defaults vs site-brand overrides.

### Admin panel

Classification: Mostly Core

Core:

- Admin routes and controllers are largely generic CMS infrastructure:
  - `routes/web.php` under `/admin`
  - `app/Http/Controllers/Admin/DashboardController.php`
  - `app/Http/Controllers/Admin/PageController.php`
  - `app/Http/Controllers/Admin/NavigationItemController.php`
  - `app/Http/Controllers/Admin/MediaController.php`
  - `app/Http/Controllers/Admin/ContactMessageController.php`
  - `resources/views/layouts/admin.blade.php`
  - `resources/views/admin/**`

- The admin UI is mostly about reusable page/content/media/navigation management rather than any one specific site.

Mixed:

- Branding is product-branded by default through shared assets and `config('app.name')` / `config('app.slogan')` in:
  - `resources/views/layouts/admin.blade.php`
  - `resources/views/components/brand-copy.blade.php`
  - `resources/views/components/application-logo.blade.php`

- The dashboard itself is mostly generic, but the text is product-oriented and assumes the fixed slot model (`Header, Main, Sidebar, Footer`) in `resources/views/admin/dashboard.blade.php`.

### Branding

Classification: Mixed

Core/Product branding:

- Product identity is explicit in:
  - `README.md`
  - `composer.json` (`fklavyenet/webblocks-cms`)
  - `config/app.php` defaults for `APP_NAME`, `APP_SLOGAN`, `APP_VERSION`
  - `public/brand/*`

Site/Application branding:

- In a real installed site, the public-facing name, slogan, logo, og image, favicon set, and likely footer text should be site-specific.

Mixed:

- Shared UI currently uses the same brand assets and app metadata for both:
  - product/CMS identity
  - public-site identity

- Examples:
  - `resources/views/partials/head-meta.blade.php`
  - `resources/views/layouts/admin.blade.php`
  - `resources/views/layouts/public.blade.php`
  - `resources/views/components/application-logo.blade.php`
  - `resources/views/components/brand-copy.blade.php`

There is no separate concept of "CMS vendor brand" vs "installed site brand".

### Example content

Classification: Demo/Starter

Starter:

- `database/seeders/StarterContentSeeder.php` creates a lightweight starter site with starter pages and navigation.

Demo/Showcase:

- `database/seeders/FullShowcaseSeeder.php` creates the Northstar Labs showcase, including:
  - pages like `services`, `pricing`, `team`, `resources`, `documentation-guide`, `privacy-policy`, `terms-of-service`, `404-demo`
  - full navigation structures for `primary`, `footer`, `mobile`, `legal`
  - demo media generation and demo asset folders
  - block trees designed to demonstrate every visible block type

- `config/demo_media.php` and `app/Console/Commands/ImportDemoMedia.php` are demo-specific support for curated media imports.

Mixed:

- Demo media support has leaked into the main asset schema/model via `demo_source_key`.
- Demo-oriented fallback copy exists in shared public templates.

### Config/env

Classification: Mixed

Core:

- `config/contact.php` is a good example of reusable core config for a CMS feature.
- `config/app.php` contains expected install-level configuration for name, slogan, version, URL, locale, etc.

Site/Application:

- `.env` values for `APP_URL`, `APP_NAME`, `APP_SLOGAN`, mail settings, and contact recipient are install-local concerns.

Demo/Starter:

- `config/demo_media.php` is explicitly showcase/demo configuration.

Mixed:

- `config/app.php` is being used for both CMS product identity and public-site identity.
- `app/Console/Commands/ImportDemoMedia.php` consumes demo config from the main app container and binds assets directly onto specific demo pages by slug and block title.

### Docs

Classification: Mixed

Core/Product docs:

- `README.md` clearly presents this repository as the WebBlocks CMS product and names the package/brand.

Mixed:

- `README.md` does not clearly separate:
  - CMS core/product
  - starter/demo content
  - an installed site/application

- It documents product identity and some CMS features, but not the boundary model needed for safe product updates.

## 3. What is already clean

- The core content engine is real and reusable: `Page`, `Block`, `PageSlot`, `NavigationItem`, `Asset`, `ContactMessage`, and related support classes are not tied to Northstar Labs.
- Public page rendering is generic at the controller/presenter level: `app/Http/Controllers/PageController.php` and `app/Support/Pages/PublicPagePresenter.php` compose slots and blocks without hardcoding demo pages.
- The block render pipeline is generic: `Block::publicRenderView()` and `Block::adminFormView()` resolve by block slug and fall back predictably.
- The admin CRUD surface is mostly clean CMS infrastructure: pages, navigation, media, block types, slot types, and contact messages are managed through reusable controllers and views.
- Contact message handling is structured as a reusable CMS feature, not a one-page special case, in:
  - `app/Http/Controllers/ContactMessageController.php`
  - `app/Models/ContactMessage.php`
  - `config/contact.php`
  - `resources/views/pages/partials/blocks/contact_form.blade.php`
- Navigation rendering is generic at the engine level via `App\Support\Navigation\NavigationTree` and `navigation-auto` blocks.

## 4. What is mixed and why it is risky

### 1. Default seeding installs a demo site, not just CMS defaults

Files/areas:

- `database/seeders/DatabaseSeeder.php`
- `database/seeders/StarterContentSeeder.php`
- `database/seeders/FullShowcaseSeeder.php`

Why it is mixed:

- One default seed path currently creates product catalog data, demo users, starter pages, and the full showcase site.

Why it matters for updates/productization:

- An updater must know whether seeded changes are core definitions or install content. Right now reseeding or seed-driven updates could overwrite or collide with site content, navigation, assets, and block trees.

### 2. Migrations mutate demo content instead of only evolving schema

Files/areas:

- `database/migrations/2026_04_17_160000_upgrade_contact_page_to_contact_form_block.php`
- `database/migrations/2026_04_17_161000_cleanup_legacy_contact_page_blocks.php`

Why it is mixed:

- These migrations target a page with slug `contact` and reorder/delete concrete page blocks. That is content migration for a seeded demo/starter page, not generic schema evolution.

Why it matters for updates/productization:

- Product updates cannot safely assume a customer site still has a `contact` page or the same block tree. Running content-assumptive migrations in updater flows risks damaging real site content.

### 3. Demo-only asset identity lives in core schema and model state

Files/areas:

- `database/migrations/2026_04_16_120000_add_demo_source_key_to_assets_table.php`
- `app/Models/Asset.php`
- `app/Console/Commands/ImportDemoMedia.php`

Why it is mixed:

- `demo_source_key` is not general CMS asset metadata; it exists to track showcase imports.

Why it matters for updates/productization:

- The updater surface should not need to reason about demo media keys inside core asset records. This leaks showcase lifecycle concerns into core persistence and makes schema harder to keep stable across installs.

### 4. Shared public views still contain demo-specific copy

Files/areas:

- `resources/views/pages/partials/slots/footer.blade.php`
- `resources/views/pages/partials/blocks/fallback.blade.php`

Why it is mixed:

- These are shared runtime templates, but they contain demo language like `Demo installation for WebBlocks CMS` and `Sign in to the demo`.

Why it matters for updates/productization:

- Core updates to shared views can unintentionally impose demo copy on production sites. It also means the core rendering layer is not neutral.

### 5. Product brand and site brand are the same thing in code structure

Files/areas:

- `config/app.php`
- `resources/views/partials/head-meta.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/components/application-logo.blade.php`
- `public/brand/*`

Why it is mixed:

- The same config values and brand asset paths drive both the admin product shell and the public website shell.

Why it matters for updates/productization:

- A CMS updater needs a stable product-owned admin brand/shell while preserving site-owned public branding. Without that separation, updating shared assets or layouts can accidentally overwrite a site's identity.

### 6. Block/catalog definitions are partly core, partly mutable site data

Files/areas:

- `database/seeders/BlockTypeSeeder.php`
- `database/seeders/PageTypeSeeder.php`
- `database/seeders/LayoutTypeSeeder.php`
- `database/seeders/SlotTypeSeeder.php`
- `app/Http/Controllers/Admin/BlockTypeController.php`

Why it is mixed:

- These catalogs behave like shipped product definitions, but they are stored in the same mutable app database and editable from the admin. Ownership is unclear.

Why it matters for updates/productization:

- An updater needs a rule for what it is allowed to update. If block types are product-owned, user edits must be protected or moved elsewhere. If they are site-owned, product upgrades must not rewrite them.

### 7. Demo media import command binds directly to named demo pages and blocks

Files/areas:

- `app/Console/Commands/ImportDemoMedia.php`
- `config/demo_media.php`

Why it is mixed:

- The command is not only importing media; it is also mutating specific demo blocks by page slug and block title.

Why it matters for updates/productization:

- This is a showcase tool, not a core CMS operation. If it remains in the main product path, it blurs whether updater jobs can safely run content-assumptive commands.

## 5. Recommended target boundary model

### Core

Core should own:

- Laravel app/framework wiring required for the CMS product to run
- generic schema for pages, blocks, slots, navigation, assets, contact messages, users, and relationships
- product-owned admin infrastructure and reusable admin views
- public rendering engine and neutral base layouts/components
- block rendering/editing infrastructure
- product-owned catalog definitions only if they are treated as immutable/versioned core definitions
- feature config such as contact throttling and notification behavior
- updater foundations and version metadata

Concrete examples in this repo that should land in Core:

- `app/Models/{Page,Block,PageSlot,NavigationItem,Asset,AssetFolder,BlockAsset,ContactMessage,BlockType,PageType,LayoutType,SlotType}.php`
- `app/Http/Controllers/Admin/**`
- `app/Http/Controllers/PageController.php`
- `app/Support/**`
- `resources/views/admin/**`
- `resources/views/pages/show.blade.php`
- neutralized `resources/views/pages/partials/**`
- schema-only migrations for CMS tables
- `config/contact.php`

### Site/Application

Site/Application should own:

- public brand identity: site name, slogan, logos, favicons, og image, footer copy
- real page content, navigation content, legal text, media uploads, contact recipient, and environment values
- site-specific page templates/overrides if needed
- any custom block types or features added for a specific install

Concrete ownership targets:

- `.env` values for site identity and mail
- uploaded media in storage
- `pages`, `blocks`, `navigation_items`, `assets`, `contact_messages` runtime rows
- optional site override views or theme assets if introduced later

### Demo/Starter

Demo/Starter should own:

- starter seeders
- showcase seeders
- curated demo media config and import tools
- demo page trees, demo navigation, demo assets, demo copy, demo legal pages, demo blog content
- demo-only tests

Concrete ownership targets:

- `database/seeders/StarterContentSeeder.php`
- `database/seeders/FullShowcaseSeeder.php`
- `config/demo_media.php`
- `app/Console/Commands/ImportDemoMedia.php`
- any future demo-only storage manifests

Important rule:

- Demo/Starter may depend on Core, but Core must not depend on Demo/Starter.

## 6. Refactor priorities before building an updater

### Priority 1

- Stop using content-mutating migrations for demo/starter pages.
  - Move logic from `2026_04_17_160000_upgrade_contact_page_to_contact_form_block.php` and `2026_04_17_161000_cleanup_legacy_contact_page_blocks.php` into a demo/starter upgrade/seeding layer.
- Split `DatabaseSeeder` into explicit modes.
  - Example: core catalogs only, starter install, full showcase install.
  - Default app setup should not silently install the entire demo site.
- Decide ownership of block/page/layout/slot type catalogs.
  - Either make them product-owned/versioned core definitions, or treat them as site-owned data and stop shipping updates through generic seeders/admin edits.
- Remove demo-only metadata from core schema, or isolate it behind a demo-specific table/mechanism.
  - `assets.demo_source_key` is the clearest current example.
- Remove demo-specific text from shared core public views.
  - Neutralize `resources/views/pages/partials/slots/footer.blade.php` and `resources/views/pages/partials/blocks/fallback.blade.php`.

### Priority 2

- Separate product/admin branding from public site branding.
  - Keep CMS/admin identity stable, but make public brand assets/config explicitly site-owned.
- Move demo media tooling out of the main app path or clearly namespace it as showcase-only.
  - `app/Console/Commands/ImportDemoMedia.php`
  - `config/demo_media.php`
- Add docs for install modes and data ownership.
  - Core install
  - starter install
  - showcase install
  - what the updater may and may not touch

### Priority 3

- Introduce a more formal extension boundary for site-specific overrides, such as a site theme layer or install-specific view namespace.
- Revisit whether all shipped block types belong in the core distribution or whether some should move to optional modules/demo packs.
- Clean up catalog/runtime inconsistencies such as `Page` defaulting `page_type` to `'default'` while seeded types use `'page'`.

## 7. Updater readiness verdict

- Can WebBlocks CMS safely support a Craft-style update manager right now?

No.

- If not, what minimum boundary cleanup is required first?

At minimum, before building an updater:

- remove demo/starter content changes from migrations
- stop default seeding from installing demo/showcase content automatically
- define ownership for catalogs like block types and slot/page/layout types
- isolate demo-only asset/media tracking from core schema
- neutralize shared core views so they no longer ship demo-facing public copy

Once those boundaries are explicit, an updater can target core code and core schema with much lower risk to site content and showcase content.
