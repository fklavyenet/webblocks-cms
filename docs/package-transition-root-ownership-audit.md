# Package Transition Root Ownership Audit

## Scope

This audit reviews the current transition state between the Laravel app root and `packages/webblocks-cms` after the latest package-authority work.

Inspected areas:

- `app/`
- `routes/`
- `resources/views/`
- `database/`
- `public/cms/`
- `config/`
- `packages/webblocks-cms/`

## Executive Summary

The transition has advanced from boundary scaffolding into partial runtime authority.

- Active CMS admin and public route definitions are package-owned through `packages/webblocks-cms/routes/admin.php` and `packages/webblocks-cms/routes/public.php`.
- A meaningful package runtime slice now exists for public entry controllers, many page or block support classes, selected admin controllers and requests, selected models, selected Blade views, and selected public assets.
- Root `app/` still contains a large compatibility layer for moved package runtime classes. This is intentional and currently preserves old imports, route references, view references, and downstream app compatibility.
- Root `app/` also still contains major active root-owned product behavior for install, auth, profile, user management, transfers, promotion, backup or restore, updates, project-layer loading, and many models with no package counterpart yet.
- Root `resources/views/` is mixed. Many admin and public rendering files are now thin compatibility wrappers to `webblocks-cms::...`, including the admin layout plus the selected shared admin partial/component layer, but install, auth, profile, and broader shared application components remain root-authoritative.
- Root `database/migrations/` remains fully authoritative. Package migration loading is intentionally disabled and the package migration directory still contains only reserved-boundary documentation.
- Root `public/cms/` remains the active runtime asset path even where identical package-owned public assets and admin CSS or JS source copies now exist. This is still a compatibility-driven root authority, not a fully package-served asset strategy.
- Root `config/` remains the live install override layer. Package config provides CMS defaults for a subset of files, but Laravel still reads root config as the active application config surface.

## Classification Legend

- `compatibility_wrapper`: root file exists only to preserve old imports, view paths, route references, or downstream compatibility.
- `active_root_authority`: runtime still actively depends on the root file as the source of behavior.
- `install_owned`: should remain in the Laravel app root long term, such as app-specific User/auth/install/environment pieces.
- `defer_until_update_flow`: should not move until migrations, installer, updater, backup or restore, Composer update, or asset publishing strategy is redesigned.
- `candidate_for_next_package_batch`: likely movable in a focused follow-up.
- `unclear_needs_review`: cannot be safely classified without deeper dependency tracing.

## Area Summary

| Major area | Current active authority | Package counterpart | Root compatibility layer | Blocker | Recommended next action |
| --- | --- | --- | --- | --- | --- |
| Route definitions | Package for CMS admin and public route trees; root for install, auth, profile | `packages/webblocks-cms/routes/admin.php`, `packages/webblocks-cms/routes/public.php` | `routes/web.php` requires package route files | Install/auth/profile remain app-root concerns | Keep route authority split; next reduce wrapper assumptions only after remaining handlers are deliberately classified |
| Public models | Package for moved models | `packages/webblocks-cms/src/Models/{Block,ContactMessage,Locale,Page,PageSlot,PageTranslation,PublicSearchIndex,Site,SiteDomain,SystemSetting,VisitorEvent}.php` | root `App\Models\...` wrappers for those 11 models | Downstream imports and current app namespace expectations | Keep wrappers for now; next package batch can continue model migration in another coherent slice |
| Admin runtime slices | Package for moved controllers, requests, support classes, views, the admin layout, and selected shared admin partials/components | package `src/Http/Controllers/Admin/*`, `src/Http/Requests/Admin/*`, `src/Support/*`, `resources/views/admin/*`, `resources/views/layouts/admin.blade.php`, and `resources/views/components/admin/form-actions.blade.php` for moved batches, now including live `slot-types` and `system settings` route surfaces | root wrappers under `app/Http/Controllers/Admin`, `app/Http/Requests/Admin`, `app/Support`, `resources/views/admin`, `resources/views/layouts/admin.blade.php`, and `resources/views/components/admin` | Compatibility for existing `App\...` imports and unresolved root-owned adjacent flows | Next focus should be model/support cleanup for already package-owned slices or admin asset and brand strategy, not updater, migrations, auth/User, or asset serving changes |
| Install/auth/profile/project-layer | Root | none for most of this area | none or not applicable | Installer writes `.env`, auth uses root `User`, project layer loads app-specific routes/views/providers | Keep root-owned long term or defer until starter/install redesign |
| Database migrations | Root | no executable package counterpart yet | not applicable | Existing installs, migration history, updater and installer assumptions | Do not move yet; redesign package migration and post-update strategy first |
| Seeders | Mixed: root entrypoints, package defaults for low-risk catalogs | package seeders for `CoreCatalogSeeder`, `IconCatalogSeeder`, `PageTypeSeeder`, `LayoutTypeSeeder`, `SlotTypeSeeder` | root seeder wrappers for moved seeders | `DatabaseSeeder`, `BlockTypeSeeder`, `PageLayoutSeeder`, install seeders still root-coupled | Next batch could package more seeders only if install/update entrypoints are kept stable |
| Blade views | Mixed: package owns moved public and admin runtime views, the admin layout, and selected shared admin partials/components; root still owns install/auth and shared app components | package `resources/views` for moved public shells, block partials, moved admin pages, `layouts/admin.blade.php`, selected admin partials, and `components/admin/form-actions` | many root view files are one-line includes to `webblocks-cms::...` | Active admin assets, brand files, and install/auth screens still tie runtime to root | Next batch should focus on model/support cleanup for package-owned slices or admin asset and brand strategy, not an asset authority move yet |
| Public assets under `public/cms` | Root active runtime path, package duplicate source now exists for moved public files plus admin CSS or JS source files | `packages/webblocks-cms/public/cms/...` | root files currently act as compatibility runtime copies rather than wrappers | No authoritative asset publish/sync/update strategy yet; brand assets are still root-only | Defer full runtime move until asset publishing and update flow are redesigned |
| Config | Root live config with package-merged defaults for CMS-owned files | package `config/{cms,contact,demo_media,webblocks-updates,webblocks-cms}.php` | root config acts as install override layer | Laravel root config remains app authority; override semantics are intentional | Keep current split; next batch can document per-file target ownership more explicitly |

## Detailed Audit

### `app/`

#### Current active authority

Mixed.

- Package authority is active for the moved public controllers, selected admin controllers, selected admin requests, selected support classes, and 11 moved CMS models.
- Root authority is still active for install, auth, profile, user, many site and system flows, most console operations, and all models without a package counterpart.

#### Package counterpart if it exists

Current package-owned runtime slices include:

- `packages/webblocks-cms/src/Models/`
  - `Block`
  - `ContactMessage`
  - `Locale`
  - `Page`
  - `PageSlot`
  - `PageTranslation`
  - `PublicSearchIndex`
  - `Site`
  - `SiteDomain`
  - `SystemSetting`
  - `VisitorEvent`
- `packages/webblocks-cms/src/Http/Controllers/Public/`
  - `PageController`
  - `PublicSearchController`
  - `ContactMessageController`
  - `PublicPrivacyConsentController`
- `packages/webblocks-cms/src/Http/Controllers/Admin/`
  - moved page/block/media/navigation/shared-slot/icon batch
- `packages/webblocks-cms/src/Http/Requests/`
  - moved public contact request
  - moved admin page/block/media/navigation/shared-slot/icon batch
- `packages/webblocks-cms/src/Support/`
  - moved public-rendering, pages, media, navigation, blocks, icons, admin pagination, and related low-risk helper batch

#### Root compatibility layer if it exists

Confirmed thin wrappers exist for moved package classes.

Examples:

- root model wrappers:
  - `app/Models/Page.php`
  - `app/Models/Block.php`
  - `app/Models/Site.php`
- root public controller wrappers:
  - `app/Http/Controllers/PageController.php`
  - `app/Http/Controllers/PublicSearchController.php`
  - `app/Http/Controllers/ContactMessageController.php`
  - `app/Http/Controllers/PublicPrivacyConsentController.php`
- root admin controller wrappers:
  - `app/Http/Controllers/Admin/PageController.php`
  - `app/Http/Controllers/Admin/BlockController.php`
  - `app/Http/Controllers/Admin/MediaController.php`
  - `app/Http/Controllers/Admin/IconCatalogController.php`
- root request wrappers:
  - `app/Http/Requests/ContactMessageRequest.php`
  - moved admin request wrappers such as `PageRequest`, `BlockRequest`, `MediaUploadRequest`, `SharedSlotRequest`
- root support wrappers:
  - `app/Support/Pages/PageRouteResolver.php`
  - `app/Support/PublicRendering/SiteAssetResolver.php`
  - `app/Support/Blocks/PublicOverlayRegistry.php`
  - `app/Support/Icons/IconCatalog.php`

#### Blocker if it remains root-owned

Root-owned `app/` areas fall into four main blocker groups.

1. Install-owned boundaries

- `app/Models/User.php`
- auth controllers and requests
- `app/Http/Controllers/ProfileController.php`
- `app/Providers/AuthServiceProvider.php`
- `config/auth.php` depends on `App\Models\User`

2. Installer or project-root boundaries

- `app/Http/Controllers/Install/InstallWizardController.php`
- `app/Support/Install/*`
- `app/Providers/ProjectLayerServiceProvider.php`
- `app/Support/ProjectLayer/ProjectLayer.php`

These touch `.env`, install-state persistence, provider loading, and root project customizations.

3. Update, backup, restore, transfer, promotion, and operational workflows

- `app/Support/System/*`
- `app/Support/System/Updates/*`
- `app/Http/Controllers/Admin/SystemUpdateController.php`
- `app/Http/Controllers/Admin/SystemBackupController.php`
- `app/Http/Controllers/Admin/SiteExportController.php`
- `app/Http/Controllers/Admin/SiteImportController.php`
- `app/Http/Controllers/Admin/SitePromotionController.php`
- related requests, commands, and models such as `SystemUpdateRun`, `SystemBackup`, `SiteExport`, `SiteImport`

These still depend on root file layout, DDEV or system execution, archive handling, backup state, and current in-app updater assumptions.

4. Remaining domain areas with no package counterpart yet

- site domains admin API controller path
- users
- legacy asset and media compatibility classes
- many models such as `Media`, `Asset`, `PageAsset`, `NavigationItem`, `BlockType`, `PageLayout`, `SharedSlot`

#### Recommended next action

- Keep all existing wrappers.
- Treat the moved page/block/media/navigation/shared-slot/site/locale/operational admin/model batches as complete for now.
- Choose the next implementation batch by domain, not by scattered class type.
- Best next `app`-adjacent batch: focused model/support compatibility cleanup for already package-owned runtime slices, or an admin asset and brand strategy pass now that the admin layout is package-owned.
- Explicitly exclude installer, updater, backup or restore, and `User` from the next batch.

#### Classification

`compatibility_wrapper`

- moved `App\Models\...` wrappers for the 11 package models
- moved public controller wrappers
- moved admin controller wrappers for page/block/media/navigation/shared-slot/icon paths
- moved admin controller wrappers for Site, Site Domain, Site Variable, and Locale paths
- moved admin and public request wrappers
- moved support wrappers under `app/Support/Pages`, `PublicRendering`, `Blocks`, `Media`, `Navigation`, `Icons`, `Visitors`, `Sites`, `Search`, `Admin`
- moved Site and Locale support wrappers under `app/Support/Sites` and `app/Support/Locales`
- root seeder wrappers for package-owned catalog seeders

`install_owned`

- `app/Models/User.php`
- auth controllers and requests
- `app/Http/Controllers/ProfileController.php`
- `app/View/Components/AppLayout.php`
- `app/View/Components/GuestLayout.php`
- `app/Providers/AuthServiceProvider.php`

`active_root_authority`

- `app/Http/Controllers/Admin/UserController.php`
- root-only admin controllers for install/update, backup/restore, export/import, promotion, users, and remaining unmoved screens
- requests supporting those root-only controllers
- root-only models without package counterparts

`defer_until_update_flow`

- `app/Http/Controllers/Install/InstallWizardController.php`
- `app/Support/Install/*`
- `app/Support/System/*`
- `app/Support/System/Updates/*`
- `app/Http/Controllers/Admin/SystemUpdateController.php`
- `app/Http/Controllers/Admin/SystemBackupController.php`
- `app/Http/Controllers/Admin/SiteExportController.php`
- `app/Http/Controllers/Admin/SiteImportController.php`
- `app/Http/Controllers/Admin/SitePromotionController.php`
- related requests, commands, and models

`candidate_for_next_package_batch`

- remaining model/support compatibility cleanup for already package-owned runtime slices

`unclear_needs_review`

- mixed asset/admin domain leftovers such as asset-specific requests versus renamed media paths
- any root-only console commands whose long-term home depends on installer or updater redesign

### `routes/`

#### Current active authority

Mixed with clear intent.

- Package owns active CMS admin and public route definitions.
- Root owns install, auth, profile, and compatibility loading of package route files.

#### Package counterpart if it exists

- `packages/webblocks-cms/routes/admin.php`
- `packages/webblocks-cms/routes/public.php`
- `packages/webblocks-cms/routes/diagnostics.php` for guarded diagnostics

#### Root compatibility layer if it exists

- `routes/web.php` keeps the app-root entrypoint and `require`s the package admin and public route files.

#### Blocker if it remains root-owned

- Install wizard routes depend on root installer classes and views.
- Auth and profile routes depend on root `User`, auth controllers, and root auth views.

#### Recommended next action

- Keep `routes/web.php` as the app-owned shell.
- Do not try to move install/auth/profile routes into the package.
- Only reduce compatibility loading once the final consumer-app bootstrap story is explicit.

#### Classification

`active_root_authority`

- `routes/web.php` for install, profile, and package-route loading
- `routes/auth.php` for auth flows
- `routes/console.php` for app console registration
- `routes/api.php` if app-specific or project-layer related

`install_owned`

- install, auth, profile route entrypoints in root route files

`compatibility_wrapper`

- the `require base_path('packages/webblocks-cms/routes/admin.php')` and `public.php` bridge in `routes/web.php`

### `resources/views/`

#### Current active authority

Mixed.

- Package owns moved public layout, page shells, search shells, slot entry views, public block renderer partials, and a meaningful admin subset such as media, navigation, page, page-layout, shared-slot, icon views.
- Root still owns install, auth, profile, and shared application component views.

#### Package counterpart if it exists

Confirmed package view counterparts exist for:

- public layout and entry views
- public block partial tree
- search modal and search pages
- moved admin page/shared-slot/media/navigation/page-layout/icon views
- moved admin site/domain/locale, dashboard, contact-message admin, visitor report, and system search views
- diagnostics and reserved runtime status views

#### Root compatibility layer if it exists

Many root view files are one-line includes or extends to package namespaced views.

Examples:

- `resources/views/layouts/public.blade.php`
- `resources/views/pages/show.blade.php`
- `resources/views/search/show.blade.php`
- `resources/views/search/partials/modal.blade.php`
- `resources/views/pages/partials/slot.blade.php`
- `resources/views/pages/partials/block.blade.php`
- many `resources/views/admin/...` files for moved admin surfaces
- many `resources/views/pages/partials/blocks/*.blade.php` files

#### Blocker if it remains root-owned

Root-owned view areas still tie directly to root app boundaries.

- install views depend on installer flow and root guest or auth shell
- auth and profile views depend on root auth stack and root `User`
- the package-owned admin layout still references root asset paths, brand assets, auth/profile routes, and app layout components
- package-owned source copies now exist for admin CSS/JS, but active admin CSS/JS remain paired with root runtime asset and branding boundaries
- the dedicated admin shell/assets audit now documents the remaining boundary in `docs/package-transition-admin-shell-assets-audit.md`

#### Recommended next action

- Keep the thin Blade wrappers.
- The selected shared admin partial/component layer, including the flash and page-actions edge cases, is now package-owned with root wrappers.
- The admin layout move is complete; the next view-adjacent batch should not move admin asset authority until asset-path, brand, publish, sync, and update semantics are explicit.
- Keep root `public/cms` admin asset paths authoritative until the admin asset-path, brand, publish, sync, and update semantics are explicit, even though package-owned source copies now exist.
- Do not mix install/auth views into that batch.

#### Classification

`compatibility_wrapper`

- moved public entry templates
- moved public block renderer partials
- moved page/shared-slot/media/navigation/page-layout/icon admin views
- selected shared admin partial wrappers for `page-header`, `listing-filters`, `pagination`, and `audit-actor`
- selected shared admin edge-case wrappers for `flash` and `page-actions`
- selected shared admin component wrapper for `components/admin/form-actions`

`active_root_authority`

- `resources/views/install/**`
- `resources/views/auth/**`
- `resources/views/profile/**`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/components/**` used by install/auth/app shell
- `resources/views/dashboard.blade.php`
- `resources/views/welcome.blade.php`
- `resources/views/partials/head-meta.blade.php`

`install_owned`

- install/auth/profile/app-shell views that are naturally Laravel-app-facing

`candidate_for_next_package_batch`

- focused model/support compatibility cleanup for already package-owned runtime slices
- admin shell readiness design, but not an admin shell move, after asset and brand semantics are explicit

`unclear_needs_review`

- components that mix generic app-shell concerns with CMS product branding

### `database/`

#### Current active authority

Root is authoritative.

- `database/migrations/` is still the only executable migration source.
- `DatabaseSeeder` remains the live top-level seed entrypoint.

#### Package counterpart if it exists

- package seeders exist for low-risk catalog seeders
- package migration directory exists only as reserved-boundary documentation

#### Root compatibility layer if it exists

- root seeder wrappers exist for moved package seeders, for example `database/seeders/CoreCatalogSeeder.php`

#### Blocker if it remains root-owned

- migration history and existing installs are rooted in current root filenames
- package migration loading is intentionally disabled
- installer, updater, backup or restore, and installed-version workflows still assume root-led schema lifecycle
- `DatabaseSeeder` writes installed version state and guards against seeding installed sites

#### Recommended next action

- Keep migrations root-authoritative.
- Do not move executable migrations until a package migration and post-update plan exists.
- If another seeder batch is needed, move only catalog-style seeders that can keep root entrypoints stable.

#### Classification

`compatibility_wrapper`

- root seeder wrappers:
  - `CoreCatalogSeeder`
  - `IconCatalogSeeder`
  - `PageTypeSeeder`
  - `LayoutTypeSeeder`
  - `SlotTypeSeeder`

`active_root_authority`

- all root migrations
- `DatabaseSeeder`
- `BlockTypeSeeder`
- `PageLayoutSeeder`
- `FoundationSiteLocaleSeeder`
- install or showcase seeders

`install_owned`

- baseline Laravel tables such as `create_users_table`, jobs, cache, session-related schema, because they support the app root and auth layer

`defer_until_update_flow`

- all CMS schema migrations
- any export/import/backup-related schema or seed lifecycle changes

`candidate_for_next_package_batch`

- additional low-risk catalog seeders if they can remain callable through root wrappers

### `public/cms/`

#### Current active authority

Root runtime path remains authoritative.

- Active runtime serves assets from root `public/cms/...`.
- Package now contains real public asset copies for moved public runtime files.

#### Package counterpart if it exists

Confirmed package counterparts exist for:

- `packages/webblocks-cms/public/cms/css/public.css`
- `packages/webblocks-cms/public/cms/js/public/public-search-modal.js`
- `packages/webblocks-cms/public/cms/js/public/sidebar-navigation.js`
- `packages/webblocks-cms/public/cms/package-boundary.json`

The moved public CSS and JS files are currently duplicated byte-for-byte between root and package.

#### Root compatibility layer if it exists

- Root files act as the active compatibility runtime asset path.
- This is not a wrapper pattern in code, but it is effectively a compatibility runtime layer because views still reference `asset('cms/...')`.

#### Blocker if it remains root-owned

- package assets are not served automatically from the package path
- current runtime still depends on root URLs like `asset('cms/css/public.css')`
- publish or sync timing during install and update is not yet authoritative
- brand assets under `public/cms/brand/` appear root-only and are referenced by active root views

#### Recommended next action

- Do not move active runtime authority yet.
- Redesign asset publishing or syncing first.
- When ready, treat public runtime assets and branding assets as separate decisions:
  - CMS-owned shared CSS or JS can move to package-published assets
  - install branding may remain root-owned override content

#### Classification

`active_root_authority`

- all root `public/cms/**` as the current served path

`defer_until_update_flow`

- shared public CSS and JS that now also exist in package, because authoritative serving depends on publish or sync strategy
- active admin CSS and JS under `public/cms/css/admin.css`, `public/cms/js/admin/**`, and `public/cms/js/admin-sortable-list.js` until admin asset publishing or sync strategy is explicit

`install_owned`

- likely branding assets under `public/cms/brand/**` unless the product later defines package defaults plus override semantics

`candidate_for_next_package_batch`

- none until asset publishing and update semantics are explicit

`unclear_needs_review`

- any admin asset whose ownership depends on whether it is product default behavior, install override behavior, or temporary root compatibility copy

### `config/`

#### Current active authority

Root config files remain the live application authority.

- Package config is merged as defaults through `WebBlocksCmsServiceProvider::registerConfig()`.
- Root config files still provide install-level application config and override semantics.

#### Package counterpart if it exists

Confirmed package defaults exist for:

- `packages/webblocks-cms/config/webblocks-cms.php`
- `packages/webblocks-cms/config/cms.php`
- `packages/webblocks-cms/config/contact.php`
- `packages/webblocks-cms/config/demo_media.php`
- `packages/webblocks-cms/config/webblocks-updates.php`

#### Root compatibility layer if it exists

- Root config is not just compatibility. It is the intentionally active override layer.

#### Blocker if it remains root-owned

- Laravel bootstrap reads root config as the install's app configuration surface
- many values are environment- and install-specific
- some root configs are fundamentally app-owned: `app.php`, `auth.php`, `cache.php`, `database.php`, `filesystems.php`, `logging.php`, `mail.php`, `queue.php`, `services.php`, `session.php`
- even CMS-owned config such as `cms.php` and `webblocks-updates.php` still drives root-owned installer, updater, backup, and operational behavior

#### Recommended next action

- Keep the current package-default plus root-override model.
- Do not attempt to remove root CMS config files yet.
- If refinement is needed, document which root CMS config keys are intended to remain install overrides versus which are true package defaults.

#### Classification

`install_owned`

- `app.php`
- `auth.php`
- `cache.php`
- `database.php`
- `filesystems.php`
- `logging.php`
- `mail.php`
- `queue.php`
- `services.php`
- `session.php`

`active_root_authority`

- root CMS config files as active install overrides:
  - `cms.php`
  - `contact.php`
  - `demo_media.php`
  - `webblocks-updates.php`

`compatibility_wrapper`

- none; these root config files are still intentionally authoritative for the install

`defer_until_update_flow`

- `webblocks-updates.php`
- updater, backup, install, and migration-adjacent keys in `cms.php`

`candidate_for_next_package_batch`

- none required; current split is already appropriate for this phase

## Remaining Root-Owned Inventory By Classification

### `compatibility_wrapper`

- root `App\Models\{Block,ContactMessage,Locale,Page,PageSlot,PageTranslation,PublicSearchIndex,Site,SiteDomain,SystemSetting,VisitorEvent}`
- moved root public controllers in `app/Http/Controllers/`
- moved root admin controllers in `app/Http/Controllers/Admin/` for page/block/media/navigation/shared-slot/icon/site/locale/operational flows
- moved root requests in `app/Http/Requests/` for page/block/media/navigation/shared-slot/icon and contact submission flows
- moved root support classes under `app/Support/` for public rendering, moved pages support, blocks support, media support, icons, search query, admin pagination, visitor reporting, system-search schema checks, and related helper classes
- root Blade wrappers under `resources/views/` for moved public and admin view surfaces
- root seeder wrappers for package-owned low-risk catalog seeders
- root route compatibility loading from `routes/web.php` into package admin and public route files

### `active_root_authority`

- install, auth, profile, user management, system settings, backup/restore, updates, import/export, promotion, and project-layer runtime in `app/`
- all root-only models with no package counterpart yet
- root `routes/web.php`, `routes/auth.php`, `routes/console.php`, and likely `routes/api.php` for app-owned concerns
- root install/auth/profile/admin-shell/shared-component Blade views
- root `database/migrations/**`
- root `database/seeders/DatabaseSeeder.php`, `BlockTypeSeeder.php`, `PageLayoutSeeder.php`, install/showcase seeders
- root `public/cms/**` as the served asset path
- root `config/{cms,contact,demo_media,webblocks-updates}.php` as live install override config

### `install_owned`

- `app/Models/User.php`
- auth, profile, and app-shell code and views
- root route entrypoints for install/auth/profile
- Laravel app config files such as `auth.php`, `database.php`, `session.php`, `mail.php`, `services.php`, `app.php`
- likely branding assets under `public/cms/brand/**`

### `defer_until_update_flow`

- installer controllers and support
- backup, restore, update, site export, site import, site promotion classes, requests, controllers, commands, and related models
- all root migrations
- active runtime asset authority under `public/cms/` until package publishing or sync is redesigned
- updater and migration-adjacent config such as `webblocks-updates.php`

### `candidate_for_next_package_batch`

- additional low-risk catalog seeders if root entrypoints stay intact
- focused remaining model/support compatibility cleanup for already package-owned runtime slices

### `unclear_needs_review`

- root-only admin assets that support partly package-owned admin screens
- mixed legacy asset versus media flows
- any console commands that straddle package-owned runtime slices and root-only operational flows

## Recommended Next Implementation Batch

The next implementation batch should be a focused **remaining model/support compatibility cleanup** for already package-owned runtime slices, or an **admin asset and brand strategy pass** now that the admin layout is package-owned. It should not move active `public/cms` admin asset authority yet, and it should not be migrations or update flow.

Why this is the best next batch:

- it continues the existing pattern of shrinking root compatibility only where package-owned runtime authority already exists
- it avoids the highest-risk installer, updater, backup, restore, and migration boundaries
- it avoids forcing asset publishing, branding, Composer-update, or root migration redesign before those boundaries are explicitly designed
- it continues reducing meaningful remaining root view assumptions for package-owned admin screens without moving another operational flow prematurely

Explicitly avoid for the next batch:

- `database/migrations/`
- installer flow
- backup or restore flow
- system update flow
- asset authority under `public/cms/`
- active admin asset authority under root `public/cms/**`
- `User` and auth ownership

## Notes

- This audit intentionally does not recommend deleting or moving files yet.
- Root compatibility wrappers still provide real value and should remain until the consumer-package transition is materially further along.
- The package is not yet ready to be treated as a fully self-sufficient Composer consumer runtime because migrations, updater flow, asset serving, installer flow, and several app-root domains remain root-authoritative.
