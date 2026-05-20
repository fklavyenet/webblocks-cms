# Changelog

## Unreleased

- Fix the root `composer.json` package metadata so tagged releases install correctly as `fklavyenet/webblocks-cms` through Composer by exposing `WebBlocks\\Cms\\` package autoloading and Laravel provider discovery at the repository root, while preserving the maintenance-repo workflow through explicit local provider loading.

- Add the first package-consumer install path for `fklavyenet/webblocks-cms` with a package-owned `webblocks:install` command that safely patches `App\Models\User`, runs the focused fresh-install CMS schema, installs `public/cms` assets, seeds baseline catalogs and settings, records the installed version, and creates the first active `super_admin` without requiring Breeze, Jetstream, Laravel UI, Fortify, or manual host route edits.
- Keep maintenance-repo package migrations inert by default while adding focused consumer install coverage for command registration, fresh-install migration discovery, safe backup-first `User.php` patching, idempotent reruns, first-admin creation, and post-install login, admin, and public home readiness.

## 1.32.7

- Fix the updater duration deprecation warning by normalizing measured millisecond durations before they are persisted onto the update run record or passed into the int-typed `UpdateResult` DTO, keeping updater behavior and output unchanged while removing the implicit float-to-int conversion path.
- Add focused regression coverage for updater duration normalization so fractional measured durations are stored and reported consistently without warning-producing implicit casts.

## 1.32.6

- Fix the post-update reporting compatibility bug where a successful update could still be marked failed at the final `UpdateResult` construction step when the pre-update backup arrived as the root `App\Models\SystemBackup` wrapper instead of the package model.
- Keep the updater or reporting boundary intentionally narrow by accepting both root and package `SystemBackup` instances in `UpdateResult`, and add focused regression coverage proving a completed update result can safely carry the root pre-update backup wrapper.

## 1.32.5

- Complete this package-authoritative runtime cleanup pass by moving the remaining package-owned helper implementations such as `BlockTranslationWriter` and `CoreBlockTypeCatalogSyncer` into `packages/webblocks-cms/src/Support/Blocks/`, switching package internals like demo media kind resolution to package-owned support classes, and leaving matching root `App\Support\...` entrypoints only as compatibility wrappers.
- Remove the dormant old `admin/layouts`, `admin/layout-types`, and `admin/page-types` CRUD surfaces from the active package or root runtime without restoring those legacy screens, while keeping the accepted root-owned boundaries unchanged for `App\Models\User`, `App\Support\WebBlocks`, install/auth/profile entrypoints, install-update guards, root migration authority, root `public/cms/...` runtime asset paths, and required legacy aliases.
- Update package bootstrap, admin, public, and shared-slot coverage to assert package-authoritative `WebBlocks\Cms\...` runtime classes instead of stale root-wrapper expectations, including package-owned models, support services, icon sync wiring, and contact notification mailables where behavior is intentionally package-owned now.
- Fix the real package-transition regressions that remained after the authority shift by allowing `App\Models\User::hasSiteAccess()` to accept package `Site` instances, preserving required package-root compatibility bindings for backup or restore or promotion flows, and keeping contact, export/import, and promotion behavior aligned with the active runtime boundary.
- Fix SQLite restore, transaction, and installed-version test lifecycle blockers in the system backup or restore layer, add focused regression coverage for the SQLite restore path, and keep the full `ddev artisan test` suite green for the accepted dirty `main` release scope.
- Refresh the release notes and transition docs so they describe the final state honestly: safely movable CMS-owned source is package-owned, root wrappers remain only where backward-compatible entrypoints are still intentional, and package transition consolidation is complete for this pass without broadening migration, updater, installer, auth/User, or runtime asset ownership.

## 1.32.4

- Finalize the package-transition consolidation metadata and diagnostics so `WebBlocksCmsServiceProvider` and `webblocks:package-status` now report the real package-owned route, view, model, seeder, and movable asset-source authority alongside the intentional root compatibility wrappers.
- Clarify in focused tests and docs that the safely movable CMS-owned source is now package-owned, while active runtime asset URLs still use root `public/cms/...` compatibility paths and the remaining boundaries stay install/auth, the app-owned `User` model, and root migration or update authority.
- Fix the late full-suite package-transition regressions by restoring the real package-owned site transfer and site promotion views, removing recursive package/root Blade wrapper loops, and preserving the promotion screen preselection and cancel-action behavior expected by the existing admin contract.
- Restore release-gate compatibility for contact notifications and legacy transitional block catalogs by routing contact mail through the root wrapper mailable entrypoint and keeping legacy draft compatibility rows for `text`, `tabs`, `slider`, `menu`, `faq-list`, `showcase-list`, and `contact-info`.

## 1.32.3

- Move the remaining safe operational admin route batch into `packages/webblocks-cms` for Slot Types and System Settings, adding package-owned controllers, the System Settings request, and package-owned admin views while preserving root `App\...` and root Blade compatibility wrappers.
- Update package admin route authority, package-status reporting, and focused bootstrap or route coverage so the active `admin.slot-types.*` and `admin.system.settings.*` surfaces now execute through the package without changing install, update, backup/restore, export/import, promotion, auth/User, migration, config, or runtime asset URL ownership.
- Copy admin CSS and JS source authority into `packages/webblocks-cms/public/cms/`, including `css/admin.css`, `js/admin/**`, and `js/admin-sortable-list.js`, while keeping root `public/cms/...` runtime files and admin layout asset URLs unchanged as compatibility paths.
- Extend package asset readiness, bootstrap coverage, and transition docs so package-owned admin asset source files are tracked through the existing `webblocks-cms-assets` and package-status boundary without moving brand assets or replacing root runtime asset authority yet.
- Move `resources/views/layouts/admin.blade.php` into package view authority as `webblocks-cms::layouts.admin`, keep the root layout as a compatibility wrapper, and update package-owned admin views to extend the package layout namespace while leaving root `public/cms` admin asset and brand runtime authority unchanged.
- Extend focused bootstrap, dashboard, and package-status coverage so the package-owned admin layout and root wrapper are verified without moving admin CSS/JS, brand assets, auth/profile/install/app/guest layouts, migrations, updater, backup/restore, export/import, promotion, or release flow.
- Move the remaining shared admin partial edge cases into `packages/webblocks-cms/resources/views/admin/partials`, including flash messages and page actions, while preserving root Blade compatibility wrappers and keeping admin shell/assets root-owned.
- Extend shared admin partial rendering coverage and package-status reporting so package-owned admin views use the package namespace for flash and page-actions partials while root include paths remain available.
- Move the selected shared admin partial/component layer into `packages/webblocks-cms/resources/views`, including page headers, listing filters, pagination, audit actor output, and form actions, while preserving root Blade compatibility wrappers.
- Update package-owned admin views and package-status reporting so moved shared admin partials/components resolve through the `webblocks-cms::` namespace while the admin shell, admin CSS/JS, brand assets, auth/profile/install views, migrations, updater, backup/restore, export/import, promotion, and release flow remain unchanged.
- Add the admin shell and asset ownership audit, documenting that selected shared admin partials are the safest next package batch while the admin shell and active admin CSS/JS stay blocked by explicit asset publishing and brand override strategy.

## 1.32.2

- Move the focused operational admin runtime slice into `packages/webblocks-cms`, including Dashboard, Contact Messages admin review, Visitor Reports, and System Search controllers, directly supporting query/status helpers, and package-owned admin views while preserving root compatibility wrappers.
- Extend focused operational admin and package-status coverage so active routes use package controllers, package views resolve through `webblocks-cms::`, root wrappers remain present, and excluded update, backup/restore, export/import, promotion, auth/User, migration, config, and public asset boundaries stay unchanged.
- Move the Site and Locale admin runtime slice into `packages/webblocks-cms`, including Site, Site Domain, Site Variable, and Locale controllers, form requests, directly supporting models and support services, and package-owned admin views while preserving root compatibility wrappers.
- Extend focused Site/Locale admin and package-status coverage so active routes use package controllers, package views resolve through `webblocks-cms::`, root wrappers remain present, and excluded install/update, migration, export/import, promotion, auth/User, and public asset boundaries stay unchanged.
- Add package-owned `webblocks-cms.php` config defaults for diagnostics, admin, public, and migration boundary switches, keeping diagnostics, public status routes, admin status routes, and package migrations disabled by default while enabling package admin route loading for active package-owned admin routes.
- Extend focused provider and console coverage so `webblocks:package-status` reports the new package config defaults, active package admin route loading, disabled status slices, and the still-disabled migration boundary.
- Harden the package-owned icon catalog runtime batch by having `webblocks:package-status` and focused bootstrap coverage verify the package-owned active admin route and icon-sync command while root wrappers remain available only for backward-compatible imports.
- Move the `icons:sync-webblocks-ui` command implementation into package `src/Console/` while keeping the root `App\Console\Commands\SyncWebBlocksUiIconsCommand` class as a compatibility wrapper.
- Escalate the icon catalog slice from compatibility-wrapper execution to package authority by registering `icons:sync-webblocks-ui` from the package service provider and pointing the active icon catalog admin route at the package controller directly, while leaving root wrappers in place only for backward-compatible imports.
- Start the Resource Authority phase by moving the active icon catalog admin index and edit-modal views into the package view namespace, updating the package controller to render `webblocks-cms::admin.system.icons.index`, and leaving root Blade files as compatibility wrappers.
- Move the active icon catalog admin route definitions from root `routes/web.php` into package `routes/admin.php`, making the package route file authoritative for that admin surface while keeping the package admin status route separately disabled by default.
- Complete the first Install / Update / Starter boundary pass by adding a package-owned public asset marker and starter stubs, making `webblocks-cms-assets` and `webblocks-cms-stubs` publish real package resources while package migrations intentionally remain disabled and root-compatible.
- Continue the runtime-authority phase by making package `routes/admin.php` and `routes/public.php` the active CMS admin and public route trees while reducing root `routes/web.php` to install, auth, profile, and compatibility loading of package-owned CMS route files.
- Move the public page, search, contact-message, and privacy-consent entry slice into package-owned controllers, form request, and `webblocks-cms::public.*` entry views while keeping root `App\Http\...` classes as compatibility wrappers.
- Extend focused public, search, bootstrap, runtime-slice, and package-status coverage so the new package-owned route authority and public entry slice are verified without changing current root migration, asset, or System Update authority.
- Refine the package architecture documentation and README so they describe the current package-owned runtime route authority honestly, including the remaining root-owned blockers such as models, broader support code, admin view trees, and assets.
- Document the post-Step-1 blocker map and next extraction recommendation: keep migrations, assets, and System Update root-authoritative for now, and prepare the next large package batch around the remaining public rendering layer plus its page or block or shared-slot model and support dependencies rather than another small isolated slice.
- Continue the public rendering authority migration by moving the active public layout, page shell, slot shell, and public search views into package `resources/views/` under the `webblocks-cms::` namespace while reducing the root public entry Blade files to compatibility wrappers.
- Move the first public-rendering support batch into package `src/Support/`, including page-route resolution, public page presentation, shared-slot presentation, slot-wrapper resolution, trusted HTML overlay extraction, public overlay/body-end registries, public search query orchestration, site resolution, visitor event logging, and site asset resolution, while keeping root `App\Support\...` classes as compatibility wrappers.
- Keep the public runtime model layer root-owned for now because Pages, Blocks, Sites, Locales, Search index records, Visitor events, and related relationships still cross admin and public runtime boundaries too broadly for a safe extraction in this batch.
- Make the package public asset boundary more concrete by adding the public layout CSS and JS used by the moved package-owned layout into `packages/webblocks-cms/public/cms/`, while keeping active runtime asset URLs rooted at `public/cms/...` for compatibility in the current phase.
- Extend `webblocks:package-status` and focused bootstrap or runtime coverage so they report the new package-owned public view and support authority honestly, including the remaining root compatibility layer for models, broader block renderers, migrations, and runtime asset paths.
- Move the full active public block renderer partial tree into `packages/webblocks-cms/resources/views/pages/partials/blocks/`, making the package namespace authoritative for shipped core block renderers while leaving root `resources/views/pages/partials/blocks/*` files as thin compatibility wrappers.
- Keep install-specific or custom root block renderers available through the existing `Block::publicRenderView()` root fallback, and add focused coverage proving package block partials resolve first while safe missing-renderer fallback behavior remains unchanged.
- Extend package-status and bootstrap coverage so public block renderer authority and public asset readiness are reported explicitly: package block partials and package public assets are present, root compatibility wrappers and root `public/cms/...` assets still exist, active runtime asset URLs still point at the root compatibility path, and root models, root migrations, and System Update remain unchanged blockers.
- Start the Public Model Compatibility Foundation batch by moving `Locale`, `Site`, `SiteDomain`, `ContactMessage`, `PublicSearchIndex`, `VisitorEvent`, and `SystemSetting` into package `src/Models/` while keeping root `App\Models\...` classes as compatibility wrappers.
- Update package-owned public runtime imports and package-status reporting so package public controllers, route patterns, public site resolution, search querying, visitor logging, and related support now prefer `WebBlocks\Cms\Models\...` where those model slices are package-owned, while `Page`, `PageTranslation`, `PageSlot`, `Block`, and `User` remain root-owned for now.
- Document the partial model authority boundary honestly: root migrations remain authoritative, System Update and install flow remain unchanged, runtime asset URLs still use root `public/cms/...` compatibility paths, and consumer or starter validation remains blocked by the remaining root-owned page or block model surface.
- Move the `Page`, `PageTranslation`, `PageSlot`, and `Block` model core into package `src/Models/` while keeping root `App\Models\...` wrappers as compatibility entrypoints.
- Update package-owned runtime typing, package status output, and focused bootstrap coverage so the package model foundation now includes the full page and block core while root migrations, root asset paths, System Update, and the app-owned `User` model remain unchanged.
- Move the active admin runtime for Pages, Blocks, Media, Shared Slots, Navigation, Block Types, and Page Layouts into `packages/webblocks-cms`, including the package-authoritative controllers, form requests, support services, and admin view trees that now back those routes.
- Keep root `App\...` classes and root `resources/views/admin/...` files as compatibility wrappers for the moved admin slices so existing imports, route references, and downstream overrides do not break during the transition.
- Extend package bootstrap and package-status coverage for the larger admin runtime boundary, and keep the one root rich-text editor partial concrete because compatibility checks still read that root Blade file directly instead of resolving it through the package view namespace.
- Refine the package architecture documentation so it now describes the broader admin runtime authority honestly: the editorial admin surfaces above are package-owned, while Sites, Users, System, install flow, migrations, root runtime assets, and System Update remain root-authoritative blockers.
- Fix the release-gate compatibility boundary exposed by the full suite after the package transition: contact form notification flow now accepts the package-authoritative `ContactMessage` model through the existing root notifier and mailable entrypoints, and the block-type contracts audit continues to report stable root compatibility view paths even though runtime authority now lives in package views.

## 1.32.1

- Continue the package seeder boundary by moving `CoreCatalogSeeder` into `packages/webblocks-cms/database/seeders/` while keeping root `Database\Seeders\CoreCatalogSeeder` as the compatibility entrypoint for existing installs, tests, and current root seeding flows.
- Extend the read-only `webblocks:package-status` command plus focused provider coverage so they report package-owned `CoreCatalogSeeder` presence alongside the existing package catalog seeders and confirm the root compatibility wrapper remains in place.
- Continue the first package-owned runtime migration with the isolated icon catalog management batch by moving `IconCatalogController`, `IconCatalogItemUpdateRequest`, `IconCatalog`, and `WebBlocksIconManifestSyncer` into `packages/webblocks-cms/src/` while keeping root `App\...` classes as compatibility wrappers for existing routes, commands, requests, and views.
- Extend the read-only `webblocks:package-status` command plus focused provider coverage so they report the package-owned icon runtime batch and confirm the root compatibility wrappers remain in place.
- Refine package resource-ownership documentation around guarded route and view slices, legacy root migrations, reserved package public assets and stubs, and the compatibility rule that current root routes, views, migrations, assets, installer flow, and System Update behavior remain authoritative outside intentionally moved package boundaries.
- Extend the read-only `webblocks:package-status` command so it also reports route and view Composer readiness, root compatibility state for routes, views, migrations, and assets, package Composer metadata and provider discovery readiness, target Composer install and update flow notes, and starter-foundation readiness without mutating runtime or install state.

## 1.32.0

- Start Runtime Migration Phases 1-2 by moving the guarded package diagnostics runtime slice to a package-owned controller under `packages/webblocks-cms/src/Http/Controllers/Diagnostics/`, wiring the package diagnostic route file to that handler, and rendering the existing package diagnostic view while keeping diagnostics route loading off by default behind the explicit package guard.
- Add the first focused package admin runtime slice as one isolated super-admin-only status page under `packages/webblocks-cms/routes/admin.php` with a package-owned controller and Blade view on a reserved `/admin/_webblocks-cms/...` path, keeping normal CMS admin areas root-owned and unaffected.
- Add the first focused package public runtime slice as one isolated static status page under `packages/webblocks-cms/routes/public.php` with a package-owned controller and Blade view on a reserved `/_webblocks-cms/...` path, keeping root public page rendering, search, multisite routing, and block rendering authoritative.
- Extend the read-only `webblocks:package-status` command so it reports diagnostics runtime slice status, package admin slice status, package public slice status, the new route guards, and the continued no-mutation transition rule.
- Add focused bootstrap and runtime coverage proving the new package diagnostics, admin, and public slices stay disabled by default, can be enabled explicitly through their guards, render through package-owned handlers or views, and do not override root admin or public route or view behavior.
- Document `Runtime Migration Phases 1-2` in the package architecture guide, including what moved, what remains root-owned, and why the first real package-owned runtime slices stay isolated and guard-disabled by default.
- Start the next low-risk package transition slice by moving package-owned catalog seeders for icons, page types, layout types, and slot types into `packages/webblocks-cms/database/seeders/`, while keeping root `Database\Seeders\...` classes as compatibility wrappers for existing installs, tests, and update entrypoints.
- Resume low-risk runtime support migration by moving `AdminPagination`, `BlockTypeIndexState`, `MediaIndexState`, and `PageIndexState` into `packages/webblocks-cms/src/Support/` while keeping root `App\Support\...` compatibility wrappers so current controllers, requests, and tests remain stable.
- Extend the read-only `webblocks:package-status` command and package bootstrap coverage so they report package seeder ownership, root compatibility wrappers, low-risk runtime support moves, and Composer path or seeder autoload readiness without changing current migration or update authority.

## 1.31.65

- Complete the remaining package boundary pilots by clarifying the reserved package migration, public asset, and stub directories, explicitly keeping package migration loading guard-disabled, and leaving package asset or stub publishing inert until real package-owned files exist.
- Expand the read-only `webblocks:package-status` diagnostic command so it reports migration boundary status, public asset boundary status, stub boundary status, the Composer-managed update target note, and the continued rule that root runtime remains authoritative without publishing, migrating, clearing cache, writing files, mutating database state, or changing install or update state.
- Add focused bootstrap coverage proving the remaining package boundaries stay inert in normal runtime, including no package migration loading and no package asset or stub publish registration while those directories still contain only reserved-boundary markers.
- Mark the boundary pilot phase complete in the package architecture documentation and define the next phase as the first real package-owned runtime slice.
- Keep this checkpoint intentionally non-invasive by moving no runtime code, no PHP source classes, and no active root routes, views, migrations, public assets, controllers, requests, models, services, seeders, or update-flow behavior.

## 1.31.64

- Activate the Package Route Boundary Pilot by adding one package-owned diagnostic route file at `packages/webblocks-cms/routes/diagnostics.php`, proving a concrete package route-file boundary without moving any active root admin or public routes.
- Guard package diagnostic route loading explicitly in the package service provider so package diagnostic routes stay out of normal runtime unless the internal diagnostic guard is intentionally enabled for a focused check.
- Extend the read-only `webblocks:package-status` diagnostic command so it reports package route boundary presence, package route file status, expected diagnostic route file existence, package diagnostic route guard state, and whether that diagnostic route is currently loaded in active runtime.
- Add focused bootstrap coverage proving the package diagnostic route file exists, stays absent from normal runtime by default, can be loaded only when the explicit guard is enabled, and does not override active root route or root view behavior.
- Keep this checkpoint intentionally non-invasive by moving no active root admin or public routes, no root views, and no additional PHP source classes.

## 1.31.63

- Activate the Package View Namespace Pilot by adding one package-owned internal diagnostic Blade view at `packages/webblocks-cms/resources/views/diagnostics/package-status.blade.php`, proving that the `webblocks-cms::` namespace now resolves a real package view without moving any active root admin or public views.
- Extend the read-only `webblocks:package-status` diagnostic command so it reports package diagnostic view presence and can optionally run `--view-check` to render that package view through the namespace, confirming package view loading without writing files, cache, config, database, or install state.
- Add focused bootstrap and console coverage for package view namespace registration, package diagnostic view existence, successful namespaced rendering, and the continued rule that root runtime and root view resolution remain authoritative.
- Keep this checkpoint intentionally non-invasive by moving no active root admin or public runtime views, no runtime routes, and no additional PHP source classes.

## 1.31.62

- Start the Package Resource Boundary Pilot by hardening the in-repo package service provider around the package-owned config default set, keeping root config files as install-level overrides, registering the package view namespace safely for future package-owned views, and keeping package publish behavior explicit and inert unless a developer intentionally runs `vendor:publish`.
- Add explicit reserved package resource boundary markers under package `routes/`, `resources/views/`, `database/migrations/`, `public/`, and `stubs/` so the next transition phase has concrete package-owned directories without moving any active root runtime resources yet.
- Expand the read-only `webblocks:package-status` diagnostic command into a clearer resource-readiness report that lists expected package config defaults, matching root override presence, reserved versus populated package resource directories, provider load state, and the transition safety note that root runtime remains authoritative unless resources are intentionally moved and wired.
- Keep this checkpoint intentionally non-invasive by moving no additional runtime-heavy PHP classes and by leaving active root routes, views, migrations, public assets, controllers, requests, models, services, and System Update behavior unchanged.

## 1.31.61

- Record the completed `v1.31.60` package source checkpoint in the package architecture documentation, confirm the successful `fklavye.ddev` update after that release, and explicitly pause further opportunistic low-risk PHP source moves until a dedicated runtime-heavy dependency audit phase is planned.
- Add a documented `Next Phase: Package Resource Boundary` plan covering package defaults versus root install overrides, migration or route or view or asset ownership strategy, stubs, Composer-managed post-update flow, and the future starter-project split direction without changing active runtime ownership yet.
- Expand the package-owned read-only `webblocks:package-status` diagnostic command so it reports package resource boundary presence, current package config files, package service provider load status, and an explicit note that root runtime remains authoritative during the transition unless resources are intentionally moved and wired.
- Keep this checkpoint intentionally non-invasive by moving no additional runtime-heavy PHP source classes and by leaving active routes, views, migrations, assets, and System Update behavior unchanged.

## 1.31.60

- Add the initial package architecture transition documentation plus an in-repo `packages/webblocks-cms/` skeleton, including local path Composer wiring for the future package split without moving existing CMS runtime code yet.
- Refine the in-repo package service provider into a guarded bootstrap contract for future package config, routes, views, migrations, assets, and stubs, while keeping current root runtime behavior unchanged until real package-owned runtime files are moved.
- Start the first package-owned default config move by adding package `config/webblocks-updates.php` and merging it under the existing `webblocks-updates` key while keeping the root config file in place as the install override.
- Classify root config ownership for the package transition and add package `config/contact.php` as the next small CMS-owned default while keeping the root contact config file in place as the install override.
- Add package `config/demo_media.php` as the next CMS-owned default config while keeping the root demo media config file in place as the install override during the transition.
- Complete the initial CMS-owned package default config set by adding package `config/cms.php` while keeping the root CMS config file in place as the install override during the transition.
- Prove package console bootstrap with a new read-only `webblocks:package-status` diagnostic command registered from the package service provider without moving any existing root console commands.
- Start the PHP source transition by moving the low-risk pure helper `SearchTextNormalizer` into package `src/Support/Search/` and updating the narrow search-support references without changing runtime behavior.
- Continue the Search support boundary transition by moving the small `PublicSearchRebuildResult` value object into package `src/Support/Search/` while keeping search indexing, schema, and query orchestration root-owned for now.
- Audit the next non-Search Support helper candidates for the package transition and intentionally move none in this step because `MediaKindResolver`, `DatabaseExecutionStrategyResolver`, `SiteHandle`, and `SiteDomainNormalizer` still cross current controller, model, request, migration, or database-runtime risk boundaries.
- Add the first actionable `app/Support` migration map to the package architecture documentation, grouping support namespaces by concern and classifying which areas are package-ready only after dependency isolation versus which must remain root-owned for dedicated later phases.
- Continue the PHP source transition by moving the tiny contact notification result value object `ContactMessageNotificationResult` into package `src/Support/Contact/` while keeping contact mail sending, recipient resolution, and contact runtime services root-owned.
- Continue the PHP source transition by moving the small block-type contract DTO `BlockTypeContract` into package `src/Support/BlockTypes/` while keeping block type registries, admin contract flows, and the audit command root-owned.
- Audit the stateless `LayoutMarkup` helper and intentionally keep it root-owned for now because its current references still cross page-layout request validation, admin form rendering, and public slot-wrapper behavior inside the broader Pages/PublicRendering boundary.
- Continue the PHP source transition by moving the low-risk inline formatter `InlineRichTextRenderer` into package `src/Support/Formatting/` while keeping the richer `SafeRichTextRenderer` sanitization contract root-owned for now.

## 1.31.59

- Refactor `Card` into a composable WebBlocks UI shell block that owns the outer `article.wb-card` renderer and now composes nested `Card Header`, `Card Body`, and `Card Footer` region blocks instead of the older mixed promo or media contract.

- Add `card_header`, `card_body`, and `card_footer` as published layout-category core block types, scope those region blocks so they can only be created directly under `Card`, and keep region blocks themselves container-capable for normal nested editorial or layout children.

- Update the slot editor Block Types picker so top-level block creation still shows `Card` without exposing the Card region blocks as normal top-level choices, while Add Child on a Card now defaults to the visible region-block picker state and shows `Card Header`, `Card Body`, and `Card Footer` immediately.

- Align the public Card renderer contract with shipped WebBlocks UI structure: `Card` owns the outer `wb-card` shell, region renderers own `div.wb-card-header`, `div.wb-card-body`, and `div.wb-card-footer`, and the older legacy Card copy fallback still renders only when a Card has no region children.

- Ensure existing installs receive the new Card region block type rows and corrected layout-category metadata through the normal `block-types:sync-core` path that System Update already runs during upgrades.

## 1.31.58

- Fix slot block create and edit modal validation so failed saves now redirect back to the same Add Block or Edit Block modal context, keep old input, and render the validation summary inside the reopened slot editor modal instead of only behind the underlying Edit Slot page.

- Fix Contact Form block create and edit validation so admin saves no longer fail when the reusable Contact Form editor omits `store_submissions`; the request now safely defaults storage to enabled, keeping the existing behavior that messages are always persisted before notification delivery.

- Fix the shipped core block catalog so `Contact Form` is restored to the normal published sync path and appears again in the slot editor Block Types picker, including existing installs that receive the corrected row through `ddev artisan block-types:sync-core`.

## 1.31.57

- Fix the Gallery `Add Gallery Items` modal filter reachability by moving the compact search and filter card into a dedicated non-scrolling region between the modal header and the normal scrollable body, so filters stay reachable while compact media rows, empty state, and error state continue to scroll inside the standard `.wb-modal-body` without reintroducing a nested results region.

## 1.31.56

- Fix the Gallery `Add Gallery Items` modal sizing regression left in `v1.31.55` by constraining the Gallery picker dialog to the viewport and making only the normal `.wb-modal-body` scroll, so the modal header, sticky filters, compact result rows, and footer all stay reachable without reintroducing a nested results scroll region.

## 1.31.55

- Replace the broken `v1.31.54` Gallery `Add Gallery Items` nested results-region scroll layout with a safer modal-body-scroll contract: the normal WebBlocks modal body stays the only scroll container, the compact filter card is now sticky at the top of that body, and the modal header plus footer remain owned by the standard WebBlocks UI modal structure.

## 1.31.54

- Fix the Gallery `Add Gallery Items` picker layout so the search and filter card stays visible directly under the modal header while long compact media result rows scroll inside their own viewport-safe results region above the normal footer.

## 1.31.53

- Fix the shared compact single-media selector contract so parent block forms for `Image`, `Card`, `File`, `Download`, `Video`, and `Audio` no longer render the legacy selected preview grid under the compact selected-media summary, while nested picker modals keep their compact result rows and Gallery keeps `Gallery Items` as its unchanged canonical multi-select UI.

- Refine the single-select block media chooser pattern so `Image`, `Card`, `File`, `Video`, `Audio`, and `Download` now keep the media selector in its own compact muted card with header-owned choose or replace plus remove actions, keep the selected-media or empty-state summary inside that card, and leave normal block content fields below the media section while the nested `#wb-overlay-root` picker modal, compact list rows, disabled inline upload, and single non-duplicated selected-media summary behavior stay unchanged.

- Remove the remaining `Upload to Library` card from the Gallery `Add Gallery Items` picker so Gallery stays a selection-only multi-select modal with the existing compact list rows, default `Image` filter, selected-row state, and `Add Selected` footer flow while uploads remain owned by `Admin -> Media`.

- Remove the redundant selected-assets summary and thumbnail grid from the Gallery block editor so `Gallery Items` remains the single canonical selected-item UI for ordering, previews, per-item summaries, edit actions, and removal while `Add Gallery Items`, `Remove All`, and the nested multi-select `Add Selected` picker flow stay unchanged.

- Simplify the `Content Header` contract by removing the admin `Title Level` field, stopping new writes of Content Header heading-level variants, and always rendering Content Header titles as `<h1 class="wb-content-title">` while safely ignoring any legacy saved heading-level values.

## 1.31.52

- Fix the public Gallery variant contract so `Masonry` is no longer a barely staggered copy of `Grid`: the CMS now renders Masonry through a real CSS-column layout with natural image heights, keeps `Grid` as the equal-cell gallery, and preserves the existing featured-first `Collage` composition.
- Standardize the canonical Gallery variant value and visible label to `masonry` / `Masonry` while still accepting legacy saved `masonary` values and rendering them through the Masonry path for backward compatibility.

## 1.31.51

- Fix the Gallery `Add Gallery Items` modal surface regression from `v1.31.50` by removing the CMS-owned `.wb-gallery-picker-modal` shell override and inherited picker dialog background from `public/cms/css/admin.css`, so WebBlocks UI `v2.7.6` again owns modal centering, panel surface, border, and shadow.
- Keep the Gallery picker as a normal runtime-owned modal under `#wb-overlay-root`, preserve compact picker row readability, and continue leaving the removed `.wb-picker-results--compact { min-block-size: 0; }` override absent.

## 1.31.50

- Fix the `Add Block: Gallery -> Add Gallery Items` admin freeze regression from `v1.31.49` by removing the CMS-side runtime-backdrop synchronization from `public/cms/js/admin/core.js`, leaving stacked modal backdrop visibility, pointer lifecycle, and topmost interactivity entirely to WebBlocks UI `v2.7.6`.
- Keep the Gallery nested picker and block editor on the shared `#wb-overlay-root` contract, preserve dirty-form close guards and fallback modal opening behavior, and keep the compact picker row-height fix from `v1.31.49` unchanged so long result rows stay readable.

## 1.31.49

- Fix the stacked Edit Slot and Gallery admin modal regression by keeping server-rendered CMS modal targets on the shared `#wb-overlay-root` contract while the pinned WebBlocks UI `v2.7.6` runtime continues to own the active stacked dialog backdrop, pointer lifecycle, and topmost interactive state.
- Remove the compact Gallery picker `min-block-size: 0` override so dense compact media rows keep their natural content height while the picker body remains the scrollable, viewport-safe container for long result lists.

## 1.31.48

- Remove the bad inherited background rule from the slot editor `Block Types` modal so the runtime-owned dialog surface no longer picks up transparent or overlapping card visuals.
- Stop treating WebBlocks UI `.wb-card` as the `Block Types` picker height and scroll container, keeping cards as visual grouping only while long result tables scroll through the modal body or table wrapper instead.
- Preserve the runtime-owned admin modal lifecycle from `v1.31.46`, keep exactly one client-side picker tab panel active and visible at a time, and leave shared WebBlocks UI `.wb-card { overflow: hidden; }` behavior unchanged.

## 1.31.47

- Fix the slot editor `Block Types` modal layout regression from `v1.31.46` by removing the fragile picker-specific nested flex sizing rules, restoring a normal modal body flow, and keeping search, tabs, and result tables readable for both `Common` and `All`.
- Keep the runtime-owned admin modal lifecycle from `v1.31.46` while ensuring the slot block picker still shows only one active client-side tab panel at a time and continues to open block editors normally.

## 1.31.46

- Fix the slot editor `Block Types` modal so long catalogs such as `All` stay inside the viewport, keep the search toolbar visible, and scroll the results card internally without pushing content past the modal footer.
- Harden runtime-owned admin modal autoload behavior so migrated server-rendered modals open exactly once through WebBlocks UI `v2.7.6`, avoid legacy backdrop collisions, and preserve normal close plus dirty-form guard behavior.
- Preserve Gallery block editor interactivity and the nested `Add Gallery Items` picker stack while keeping the canonical compact picker rows plus deterministic empty and error states intact.
- Align the slot editor `Block Types` modal filter toolbar with the shared admin listing filter pattern by restoring the `Apply` label, showing `Reset` only when search or tab state is active, and placing `Reset` after `Apply`.
- Simplify the `Block Types` modal by removing category and sort filters so every tab and search result stays in `Name A-Z` order, and add a header count badge for the current picker result set.
- Separate the `Block Types` modal search toolbar and tabbed results into distinct muted cards so the filter area reads clearly apart from the picker content.

## 1.31.45

- Fix the Gallery block edit modal regression after `v1.31.44` by opening the server-rendered slot block editor through the WebBlocks UI `v2.7.6` modal runtime instead of exposing it only with server-side visible classes, restoring the runtime-owned backdrop dimming, pointer lifecycle, and normal close behavior.
- Preserve the nested `Add Gallery Items` picker stack above the Gallery editor while keeping the compact media rows plus empty and error states aligned with the shared WebBlocks UI overlay contract and without reintroducing the removed CMS-owned overlay wrapper.

## 1.31.44

- Fix the Gallery block editor modal so it again opens with the normal WebBlocks backdrop and dimming, removing the stale CMS-owned dialog wrapper behavior that hid the active backdrop under the shared `#wb-overlay-root` runtime.
- Fix the Gallery `Add Gallery Items` picker lifecycle so the CMS keeps one canonical picker modal in the response, avoids surfacing a stale source modal behind the runtime-owned visible picker, and continues to let WebBlocks UI `v2.7.6` own stacked overlay behavior.
- Fix the compact Gallery picker final rendering so matching image rows render deterministically, compact empty and error states replace the stuck placeholder-like result state, and the default `Image` kind plus search and folder filtering stay aligned with the picker data contract.

## 1.31.43

- Fix the Gallery `Add Gallery Items` compact picker so the detached plain WebBlocks modal uses the normal large dialog footprint instead of the previous oversized wide panel while staying under `#wb-overlay-root` and the shared WebBlocks UI `v2.7.6` overlay stack.
- Fix Gallery picker result rendering and filter state so the default `Image` kind shows matching media rows again, folder or search filtering can reach real empty states, and compact error messaging no longer leaves the picker looking stuck on placeholder rows.

## 1.31.42

- Fix the Gallery `Add Gallery Items` overlay stacking regression by rendering the compact picker as a plain WebBlocks modal under `#wb-overlay-root`, letting the WebBlocks UI `v2.7.6` runtime own the active stacked dialog layer instead of relying on CMS-only wrapper and z-index overrides.

## 1.31.41

- Pin CMS-owned WebBlocks UI runtime assets and the default icon manifest sync source to `v2.7.6`, keeping public and admin layouts aligned on the current shipped CDN URLs.
- Replace the dirty admin overlay browser confirm flow with a stacked WebBlocks confirmation modal on `wb:overlay:close-request`, so unsaved-change discard stays in the shared overlay stack while confirmed closes still finish programmatically without re-triggering the guard.
- Compact the Gallery `Add Gallery Items` media picker into a list-style selector with small thumbnails, one-row metadata, visible selected-state styling, and the same stacked overlay plus `Add Selected` behavior.

## 1.31.40

- Pin CMS-owned WebBlocks UI runtime assets and the default icon manifest sync source to `v2.7.5`, aligning admin and public overlays with the updated shared overlay stack behavior and close-request runtime contract.
- Standardize representative CMS admin modal and picker close behavior around `wb:overlay:close-request`, so normal detail or preview modals keep standard Escape, outside-click, close-control, and dismiss-control behavior while dirty form overlays now guard discard flows without blocking confirmed programmatic close.

## 1.31.39

- Fix the stacked admin Gallery `Add Gallery Items` picker so the detached overlay now uses an explicit CMS-owned z-index contract for its shell, backdrop, and panel, keeping the picker above the still-open parent Gallery editor modal after the `#wb-overlay-root` refactor.
- Restore pointer-safe nested picker interaction so media cards, `Select`, `Add Selected`, and close actions stay clickable while the parent Gallery editor remains open but visually de-emphasized underneath.

## 1.31.38

- Fix the admin Gallery `Add Gallery Items` picker so its overlay now mounts under the shared admin `#wb-overlay-root` instead of inside the parent Gallery editor modal, preventing clipping by modal body overflow or max-height while keeping the editor open underneath.
- Polish stacked Gallery modal layering with a stronger page-level backdrop, deterministic overlay ordering, and viewport-bounded internal picker scrolling so the picker header and footer stay reachable on normal desktop and smaller admin viewports.

## 1.31.37

- Fix the real Gallery existing-item editor persistence bug by aligning server-rendered item modal ids with the admin Gallery row binding logic, so `Done` now updates the exact hidden `gallery_items[*][alt_text|caption|overlay_title|overlay_text]` inputs, live row summaries, and saved `block_gallery_item_translations` values for the active locale.
- Simplify the public Gallery renderer by letting the root `.wb-gallery` own the block wrapper directly and removing the unnecessary inner `section`, while preserving ordered media output, the single `.wb-gallery-grid` layout wrapper, lightbox wiring, legacy `data-wb-gallery-meta`, and shared overlay registration.

## 1.31.36

- Fix Gallery item metadata editing so existing item modals now sync alt text, caption, overlay title, and overlay text back into the hidden gallery-item inputs and compact summaries before block save, restoring persistence through `block_gallery_item_translations`.
- Add CMS-owned public Gallery layout styling for the shipped variant, columns, gap, and aspect-ratio settings so supported Gallery settings now visibly affect public output instead of only adding inert classes.

## 1.31.35

- Polish the Gallery block editor so `Gallery Items` owns a normal admin card header with a live item-count badge plus header actions for `Add Gallery Items` and `Remove All`, while removing the old inline explanatory callouts from the main editing path.
- Strengthen the Gallery media picker as a true in-modal overlay layer with a local backdrop, stronger panel separation, and live item-count or empty-state updates so the still-open Gallery editor remains stable without underlying table action bleed-through.

## 1.31.34

- Move the Gallery block media picker out of the inline `Gallery Items` flow into an overlay-style picker inside the still-open Gallery editor modal, keeping the compact list stable while preserving existing add, append, remove-all, and ordering behavior.
- Rename the Gallery picker action from `Add More Assets` to `Add Gallery Items` while keeping the existing media library filters, folder and kind controls, upload flow, and selection handling intact.

## 1.31.33

- Remove the redundant `Gallery Assets` selected-assets card and thumbnail grid from the Gallery block editor so the compact `Gallery Items` list remains the single canonical admin UI for adding, removing, reordering, and editing gallery items.

## 1.31.32

- Refine the core `gallery` block into a compact `Gallery Items` editor so ordered media selection is managed as small list rows with per-item edit and reorder controls instead of the older large selected-assets grid.
- Remove Gallery title and description from normal Gallery editing and public rendering, keep any legacy stored values ignored by the public renderer for backward compatibility, and guide editors to use `Content Header` plus `Plain Text` or `Rich Text` before Gallery when section copy is needed.
- Move per-gallery-item alt text, caption, overlay title, and overlay text into the new locale-owned `block_gallery_item_translations` model while keeping gallery media selection, ordering, and presentation behavior shared.
- Carry gallery item translation rows through page revisions, Shared Slot revisions, site clone, site export/import, site promotion, safe site deletion, and export packaging, including the new `data/block_gallery_item_translations.json` archive manifest.
- Clean up the public Gallery renderer so it preserves ordered gallery media, lightbox behavior, and the shared `#wb-overlay-root` plus `data-wb-gallery-target` contract without rendering legacy gallery heading or paragraph output.

## 1.31.31

- Pin CMS-owned default WebBlocks UI CDN assets and the default icon manifest sync source to `v2.7.3` so public and admin layouts consume the released card media frame utilities instead of older `v2.7.x` assets.
- Update the core `card` public renderer to use WebBlocks UI `wb-card-media` alignment and aspect modifiers inside `.wb-card-body`, while preserving media-driven visibility, no-image cards, legacy `image_position = none` fallback, nested child footer actions, and the older single-action fallback.

## 1.31.30

- Refine the core `card` block image UX so selected media now controls image visibility, older blank or legacy `image_position = none` values fall back safely to `top`, and the public card image figure renders inside `.wb-card-body` instead of flush against the outer card edge.
- Expand shared Card image presentation settings with `top`, `middle`, and `bottom` placement plus shared horizontal alignment, keep image alt and caption locale-aware, preserve locale-only shared-field protection, and keep nested child footer actions plus the legacy single-action fallback backward compatible.

## 1.31.29

- Enhance the core `card` block with optional shared media selection so editors can build editable image cards inside `Grid` and other existing layout trees without introducing a separate install-specific card variant.
- Make Card image alt and caption copy locale-aware while keeping image presentation settings shared, and preserve backward compatibility for existing no-image cards, nested child action composition, and the legacy single-action fallback.
- Add focused regression coverage for Card media persistence, locale-only edit preservation, public rendering, and contract reporting so the shipped Card contract stays aligned with the actual admin and renderer behavior.

## 1.31.28

- Continue the Phase 3 Legacy / Transitional cleanup by keeping `tabs`, `slider`, `menu`, and `faq-list` documented honestly as legacy draft-era compatibility slugs instead of falsely promoting them into the published core contract set, while `showcase-list` and `contact-info` remain public-only compatibility renderers without shipped core contract entries.
- Preserve the existing legacy renderer behavior for those transitional paths, keep contract modal and audit output safely undocumented for non-published target slugs, and harden settings-driven public links in `showcase-list` and `contact-info` so unsafe URLs are ignored without adding any new tabs or slider JavaScript architecture.

## 1.31.27

- Publish the corrected Phase 3 Marketing / Structured Content cleanup release as `v1.31.27`, superseding the accidentally published `v1.31.26` tag that pointed at the previous `v1.31.25` release commit without changing that already-published tag.
- Include the completed contract cleanup set for `hero`, `columns`, `column_item`, `cta`, `feature-grid`, and `feature-item`, plus the missed release changes in `BlockController` and `BlockTypeSeeder` that preserve shared URLs during locale-only edits and stop legacy draft seeding from overwriting promoted core metadata.

## 1.31.26

- Note: the corrected package for this release work is `v1.31.27`. The already-published `v1.31.26` tag mistakenly points to the previous `v1.31.25` release commit and is left untouched.

- Continue the Phase 3 Marketing / Structured Content contract cleanup by promoting the real shipped source-backed set `hero`, `columns`, `column_item`, `cta`, `feature-grid`, and `feature-item` into the published core catalog and aligning the shared contract registry, contracts audit output, shipped admin contract modal data, docs, and focused regression coverage around those contracts.
- Keep docs and contract reporting honest about current shipped behavior by preserving `feature-grid` and `feature-item` as transitional source-backed aliases over the shared Columns or Column Item presentation paths, while `testimonial` and `stats` remain alias-only render behavior instead of being misrepresented as standalone published core contracts.
- Preserve shared CTA and structured child URLs during locale-only edits so translated updates for promo buttons and column or feature items no longer overwrite shared canonical links, and add focused regression coverage for that save behavior.

## 1.31.25

- Continue the Phase 3 block type contract cleanup for the Layout + Card group by aligning `section`, `container`, `grid`, `cluster`, `card`, and `content_header` across the shared contract registry, audit output, root-ownership reporting, request or save ownership, docs, and focused regression coverage.
- Expand `block-types:contracts-audit` so both markdown and JSON outputs expose the same contract detail already used by the shipped admin contract modal, align contract or admin serialization for list-shaped fields, and document the real Card storage model where visible copy stays translation-backed without assuming any nonexistent `blocks.eyebrow` column.

## 1.31.24

- Finalize the omitted Phase 3 media or visual block contract cleanup pieces from `v1.31.23` by marking `image`, `gallery`, `download`, `file`, `video`, and `audio` as root-owning public blocks, restoring their shipped documented contract entries in the shared registry, and fixing contract audit or admin modal serialization to emit the real `translatable_fields` payload.
- Keep the scope limited to the leftover contract model, registry, and root-ownership changes that were unintentionally left out of `v1.31.23`, without changing the broader media block request, renderer, docs, or test behavior already released.

## 1.31.23

- Continue Phase 3 block-type contract cleanup by publishing the media or visual core block contracts for `image`, `gallery`, `download`, `file`, `video`, and `audio`, aligning the shared contract registry, audit output, admin modal coverage, request normalization, save behavior, media relationship ownership, public renderer root ownership, docs, and focused regression tests around one shipped source-backed contract.
- Preserve submitted gallery media ordering through `AdminAuthorization::filterAllowedMediaIds()` so multi-select admin saves keep the editor-selected gallery item order when canonical `block_media` rows are written.
- Stop legacy draft seeding in `BlockTypeSeeder` from overwriting promoted core metadata for shipped media block types after the core catalog sync has published them.
- Keep backward compatibility for existing saved media content by preserving legacy gallery settings fallbacks where they still affect public output, while stopping new arbitrary child placement and public child-tree rendering for these non-container media blocks.

## 1.31.22

- Continue Phase 3 block-type contract cleanup by aligning `navbar-brand` and `sidebar-brand` around the same conservative shared URL fallback and logo-only accessible-name contract, while keeping translated visible copy and shared logo or operational settings intact.
- Reduce sidebar navigation renderer drift by reusing the same manual sidebar item output semantics for standalone `sidebar-nav-item` blocks and nested `sidebar-nav-group` children, and update the shared contract registry, audit output, docs, and regression coverage so the resolved navigation or brand gaps match shipped behavior.

## 1.31.21

- Continue Phase 3 block-type contract cleanup by aligning the persisted `sticky-navbar` Navbar block with root-owning public rendering and removing the extra generic wrapper path where Navbar already owns the real `nav.wb-navbar` root.
- Tighten the published non-container contract for `code`, `table`, `quote`, `toc`, and `html` so new arbitrary child placement is no longer eligible in the slot builder, public renderers no longer emit historical child trees, and the shared contract registry, audit command, docs, and regression coverage match the shipped behavior while preserving existing saved child rows.

## 1.31.20

- Start Phase 3 block-type contract standardization by aligning the shipped `code` and `table` blocks with the existing text-translation architecture, preserving breadcrumb display settings on save, rendering stat-card URLs publicly, and surfacing translated link-list intro copy in the public renderer.
- Update the shared contract registry, read-only Block Types contract modal, audit command output, and focused regression coverage so the resolved gaps for `code`, `table`, `breadcrumb`, `stat-card`, and `link-list` match current shipped behavior.

## 1.31.19

- Add a read-only `Block Type Contract` modal to `Admin -> System -> Block Types` so each listed row can surface shipped contract details such as admin form source, translation ownership, shared settings, media or relationship paths, child rules, public renderer ownership, and known gaps without changing edit behavior.
- Refactor the Phase 1 contract audit into a shared block type contract registry so the admin modal and `ddev artisan block-types:contracts-audit` use the same core contract source and custom or draft rows can fail safely with an informational no-contract message.

## 1.31.18

- Add Phase 1 Block Type Contracts documentation with a read-only published core block inventory that documents current admin form sources, translation and shared ownership, media or relationship paths, renderer ownership, known gaps, and recommended next phases without changing block behavior.
- Add the safe developer audit command `ddev artisan block-types:contracts-audit` plus focused console coverage so shipped block-type catalog and admin or public support-file presence can be re-audited without touching database content.

## 1.31.17

- Standardize admin listing count badges so page-header badges show the total record count in each screen's base scope while listing-card badges show the currently filtered result count.
- Fix affected admin listings including Block Types, Pages, Shared Slots, Blocks, Contact Messages, Icons, Users, Sites, Page Layouts, Slot Types, Backups, and Export / Import histories without changing existing filter, pagination, or query-string behavior.

## 1.31.16

- Update `Admin -> System -> Block Types` so install-specific edit actions open a query-driven WebBlocks admin modal from the index, keep the existing edit route as a fallback, and preserve filtered or paginated list return context on save.
- Rename the Block Type edit heading and modal title to `Edit Block Type: {Block Type Name}` so the selected record stays visible in page-level and modal-level admin context.

## 1.31.15

- Apply the install-level `Admin listing rows per page` setting consistently across remaining paginated CMS admin listings, including `Media`, Page Layouts, backup history, icon catalog, contact messages, and site export/import histories.
- Keep admin pagination centralized through the shared helper so invalid or missing stored values still fall back safely to `15`, while existing admin filter, sort, direction, and query-string preservation continues to work.

## 1.31.14

- Add `Sort by` and `Direction` controls to `Admin -> Media` using the shared compact listing toolbar pattern, while keeping `Search`, `Kind`, `Usage`, folder pills, view mode, pagination, preview links, and edit return URLs in the same query-preserved Media list context.
- Keep Media sorting safe and deterministic with a whitelist of practical fields, default `updated_at desc` behavior, folder sorting through the related folder name, and `usage` sorting mapped to a real aggregate count across protected CMS media references.

## 1.31.13

- Fix contact form notification handling so saved submissions can resolve a usable recipient more reliably in local DDEV installs, including a safe fallback to `MAIL_FROM_ADDRESS` when neither the block recipient override nor `CONTACT_RECIPIENT_EMAIL` is configured.
- Move contact notification delivery into a dedicated service, keep public success semantics tied to message storage, sanitize and persist concise admin-visible failure details, and surface failure context in both the Contact Messages list and detail screen.

## 1.31.12

- Fix the public `showcase-list` renderer so clickable showcase images emit the required WebBlocks UI `data-wb-gallery-target` and register a matching gallery viewer modal under the shared `#wb-overlay-root` instead of falling through to raw image URL navigation.
- Keep existing `href` image links as the no-JavaScript fallback while adding regression coverage that both `showcase-list` and the existing `gallery` block keep a valid gallery trigger-to-overlay target relationship.

## 1.31.11

- Fix the CMS public search modal shell so it no longer renders the shared `.wb-overlay-layer--dialog` in a hidden state that WebBlocks UI `v2.7.1` reuses for trusted HTML modals and gallery viewers, which previously left CMS-rendered overlays invisible even though the runtime locked the body and found the target.
- Align CMS public overlay output with the working WebBlocks UI DOM contract by keeping the shared dialog layer visible, limiting hidden state to the search backdrop and search modal itself, and adding regression coverage that trusted modal and gallery targets under `#wb-overlay-root` are not blocked by a hidden parent layer.

## 1.31.10

- Fix trusted HTML public rendering so detached `wb-modal`, gallery viewer, drawer, dropdown, and popover targets are hoisted into the single shared `#wb-overlay-root` instead of being emitted as loose body-end markup, restoring WebBlocks UI modal and gallery trigger reachability on CMS-rendered public pages.
- Confirm CMS public layout continues to own pinned WebBlocks UI `v2.7.1` asset loading, the canonical shared overlay root, and regression coverage for trusted modal plus gallery trigger-to-target contracts.

## 1.31.9

- Extend `Admin -> Pages -> Edit Page -> Page Management` with a dedicated `Layout Slots` tab so the `Page Layout Slots` comparison and `Add Missing Layout Slots` action live with other page-management concerns instead of inside the separate `Slots` card.
- Remove the duplicated `Add CSS asset` and `Add JS asset` buttons from the `Assets` empty state so those actions stay owned by the `Page Assets` card header.

## 1.31.8

- Refine `Admin -> Pages -> Edit Page` by replacing the crowded top `Page Overview` plus `Page Settings` arrangement with one `Page Management` card that separates `Overview`, `Settings`, and `Assets` into calmer tab-owned cards.
- Keep workflow/status actions in `Overview`, keep `Save Changes` and `Cancel` only in the `Settings` form card, and keep page asset add/edit/delete actions owned by the `Assets` card and modal flow.

## 1.31.7

- Consolidate `Admin -> System -> Settings` into one `Settings` form card with a two-column layout, one shared footer action row, and the existing editable system settings saved together in a single submit.
- Remove the hidden duplicated setting mirror inputs that previously kept the separate General, Project, and Cookie forms from clearing each other, and move Application version plus Environment into the same card as read-only information rows.

## 1.31.6

- Fix the System Settings `General` card form structure so its action row renders as the real card footer at the visual bottom of the card, matching the standard admin card footer pattern.

## 1.31.5

- Add an install-level `Admin listing rows per page` system setting under `Admin -> System -> Settings`, apply its validated default row count across core admin listings that previously used the shared hard-coded `15`, and keep missing or invalid stored values safely falling back to `15` without affecting public pagination.
- Fix the System Settings `General` card action placement so `Save Changes` and `Cancel` render in the owning card footer using the standard admin form footer pattern.

## 1.31.4

- Refine the Edit Media admin screen so Preview and Usage render as read-only context cards, Media Information becomes the full-width edit form, File Details opens from the Preview header, and delete actions stay on the Media list instead of the edit form.
- Clarify Media index count badges so the page heading shows total media in scope while the Media Library card shows the current filtered result count.

## 1.31.3

- Fix Media Usage filter after Asset-to-Media rename so used/unused filtering no longer queries legacy/non-existent root media columns.

## 1.31.2

- Fix the Asset-to-Media rename migration so nullable media foreign key column renames do not emit invalid MariaDB SQL such as `DEFAULT 'NULL'`.

## 1.31.1

- Fix the Asset-to-Media rename migration so `SET NULL` media foreign keys are made nullable before constraints are rebuilt, allowing `v1.31.0` installs with `block_media` references to migrate successfully.

## 1.31.0

- Complete the Phase 2 `Asset` to `Media` rename across active runtime code, requests, admin forms, tests, revisions, and site transfer packaging so canonical CMS internals now use `Media`, `media_id`, `block_media`, and `media.json` while preserving legacy payload, request-key, and archive-file compatibility.
- Keep backward compatibility for older exports, revision snapshots, and submitted admin payloads by normalizing legacy `asset_*` keys and alias archive entries during import, restore, clone, and promotion flows instead of preserving `Asset` as the active internal model.

## 1.30.6

- Polish the Media admin UX so user-facing copy consistently uses `Media`, media titles and edit actions open the merged `Edit Media: {title}` screen, preview stays a modal action, and the legacy `/admin/media/{id}` detail route now redirects safely to edit.
- Move `Copy public URL` into the Edit Media `File Details` card, preserve usage summaries and Open links on the merged edit screen, and replace browser confirm deletion with the standard admin `Danger Zone` plus modal confirmation pattern while keeping internal Asset tables, models, ids, and storage paths unchanged in this patch release.

## 1.30.5

- Harden installation safety so fresh installs and in-app package updates disable `origin` push with `git remote set-url --push origin DISABLED` when the working copy points at the canonical WebBlocks CMS upstream, while keeping fetch-based update consumption intact.
- Clarify that CMS installations are update consumers only, that releases must be created from the real maintenance checkout, and that existing installation clones such as `project-fklavye` should disable push on `origin` explicitly.

## 1.30.3

- Fix the Edit Site footer so `Cancel` returns to the Sites index instead of leaking to the Pages screen.
- Restore resolved site-level public override assets so `public/site/{site_handle}/css/site.css` and `public/site/{site_handle}/js/site.js` render from the current public site while CMS core assets remain under `public/cms/`.

## 1.30.2

- Refine Page Layout and Page Layout Slot admin polish with standard layout-aware slot page titles, clearer body-class guidance, and an expandable trusted HTML disclosure that reads as an actual admin accordion instead of a readonly notice.
- Clarify that Page Layout Slot wrapper classes are space-separated tokens and that sticky wrappers such as `wb-sticky` usually still need site CSS offset and stacking rules, for example in `public/site/{site_handle}/css/site.css`.

## 1.30.1

- Polish the Page Layout Slot create and edit admin UX by grouping slot identity, wrapper markup, advanced trusted layout HTML, and status or ordering into clearer sections while moving trusted HTML into a dedicated advanced panel with explicit safety guidance.
- Refine the Edit Page Layout slot listing with more compact wrapper-focused rows, clearer Required and Active status visibility, and helper copy that explains how Body Class plus slot ids and classes support layout-specific CSS without restoring deprecated shell or raw JSON fields.
- Document that Page Layout body classes target the public body, Page Layout Slots own wrapper element, id, and classes, and advanced trusted layout HTML remains limited to wrapper-adjacent structure rather than scripts.

## 1.30.0

- Add Page Layout Slot compare-and-apply workflow on Edit Page, including a reusable layout-slot diff service, compact `Page Layout Slots` admin summary, and an explicit `Add Missing Layout Slots` action that creates only missing Page Slots from the selected Page Layout.
- Keep layout sync safe by preserving extra Page Slots, page-owned blocks, Shared Slot assignments, Disabled slot states, normal save behavior, and existing `public_shell` compatibility while extending new page creation to seed active managed Page Layout Slots by default.
- Extend page revision snapshots to keep slot source metadata so Shared Slot-backed and Disabled slot state remains recoverable, and document the new compare/apply workflow and ownership boundaries for Page Layouts, Page Slots, and Shared Slots.

## 1.29.1

- Rename Shared Slot admin wording from `Public Shell` to `Page Layout`, while keeping the stored `shared_slots.public_shell` field and exact-handle compatibility behavior unchanged for backward compatibility.

## 1.29.0

- Replace the remaining Page Layout `shell_type` admin surface with managed `body_class` and relational `page_layout_slots`, while keeping `pages.settings.public_shell` and deprecated legacy layout columns backward compatible.
- Add Page Layout Slot management, seeded system slot definitions for `Default Layout` and `Docs Layout`, and runtime wrapper resolution from managed layout slots so public rendering, Shared Slots, export/import, and clone flows keep working safely.
- Document the managed Page Layout Slot model, body-class behavior, and portability boundary where page-level layout handles transfer but install-level Page Layout definitions remain local to each install in V1.

## 1.28.0

- Add Managed Page Layouts V1 with an install-level `page_layouts` catalog, seeded system layouts for `Default Layout` and `Docs Layout`, a new `Admin -> System -> Page Layouts` management screen, and data-driven Page Settings layout selection while keeping `pages.public_shell` storage backward compatible.
- Preserve public rendering and Shared Slot safety by resolving stored page layout handles to `default` or `docs` shell behavior at runtime, keeping unknown handles safe, and maintaining conservative exact-handle Shared Slot compatibility for V1 custom layouts.
- Document the new Page Layout ownership model and portability boundary, including the fact that page-level `public_shell` handles still transfer through export/import and clone while install-level Page Layout definitions remain local to each install in V1.

## 1.27.9

- Removed the CMS-specific navbar sticky class and restored clean ownership of sticky and page-level behavior to the public header or page layout wrapper path while keeping WebBlocks UI `wb-navbar` as the navbar behavior source.
- Renamed the admin-facing `Public Shell` label to `Page Layout` while keeping existing stored shell handles such as `default` and `docs` backward compatible.
- Confirmed the mobile navbar toggle continues to use the shipped WebBlocks UI `wb-icon-menu` icon.
## 1.27.8

- Fix reusable public `Navbar` header rendering so sticky navbars stay pinned when rendered inside the default header slot wrapper, and switch the mobile navbar toggle from raw span bars to the shipped WebBlocks UI `wb-icon-menu` icon while preserving existing `data-wb-*` and aria behavior.

## 1.27.7

- Pin WebBlocks UI CDN assets and the default icon manifest sync source to `v2.7.1` so CMS installs pick up the icon catalog and generated glyph alignment fix without falling back to unstable asset URLs.

## 1.27.6

- Fix public `Navbar Navigation` mobile behavior by rendering an accessible burger toggle plus collapsed menu through the existing WebBlocks UI dropdown contract, while preserving primitive `Navbar` composition, keeping default header slots free of forced `wb-stack` wrappers, and restoring header-to-main shell spacing.

## 1.27.5

- Make default public header slots layout-neutral so they no longer force a `wb-stack` wrapper around header content such as `Navbar`, and move the header-to-main spacing responsibility into the public shell wrapper instead of the Navbar block.

## 1.27.4

- Extend the reusable `Cluster` layout primitive with editor-controlled width, justify, align, wrap, and gap settings so headers such as `Navbar -> Container -> Cluster` can compose correct left and right distribution without navbar-specific CSS or built-in wrappers.

## 1.27.3

- Add explicit `Container` flow control so editors can choose layout-neutral `wb-container` markup for composed layouts such as `Navbar -> Container -> Cluster`, while preserving legacy stacked container rendering unless a neutral flow is selected.
- Allow `Navbar Brand` to be configured as logo-only when a logo image is present, and add an accessible label fallback path for logo-only brand rendering.

## 1.27.2

- Fix navbar child validation and admin block picker eligibility so `Navbar Brand` and `Navbar Navigation` are allowed anywhere inside a `Navbar` descendant tree, while still being rejected outside Navbar entirely.

## 1.27.1

- Refactor the persisted `sticky-navbar` block into a primitive `Navbar` container that renders only `nav.wb-navbar` plus child blocks, add composable `Navbar Brand` and `Navbar Navigation` child blocks, remove the CMS-specific `wb-cms-sticky-navbar-*` markup and settings surface, and keep only a shared `Position` option so navbar styling remains WebBlocks UI-first.

## 1.27.0

- Add a reusable system-owned `Sticky Navbar` block type for public page and Shared Slot headers.

## 1.26.2

- Add a dedicated `Usage` filter to the `Admin -> System -> Block Types` listing so admins can compare used versus unused block type rows separately from the existing Support metadata filter.

## 1.26.1

- Synchronize the core database-backed block type catalog during System Updates so existing installs create missing shipped block types and refresh core metadata without overwriting install-specific custom block types.

## 1.26.0

- Add a first-class `Admin -> Pages -> Import Page` modal workflow that imports one `webblocks.cms.page.v1` JSON payload into a selected site as a new draft page, including page translations, slots, page-owned block trees, supported block translations, and safe local page asset metadata.
- Add documented single-page import schema guidance and a sample `webblocks.cms.page.v1` payload so admins can prepare importable page JSON outside project-specific commands.
- Merge the Edit Page top site, domain, and editorial workflow summary into one `Page Overview` card while preserving the existing workflow actions and status information.
- Compact the `Admin -> Pages` listing rows so title, locale metadata, public paths, last-edited metadata, and actions stay easier to scan without changing filters, sorting, pagination, or row actions.

## 1.25.3

- Move CMS-owned public assets from `public/assets/webblocks-cms/`, `public/brand/`, and legacy handle-less `public/site/css/*` paths into `public/cms/`, leaving `public/site/{site_handle}/...` for real install/site/page overrides only.
- Reduce `project/` to a minimal install-local boundary with generic documentation only, and remove leftover scaffold providers, routes, config, and tests from the repository.

## 1.25.2

- Add a compact `Last edited` column to the Pages admin listing with the page `updated_at` timestamp and recorded editor name, and expose matching Pages index sorting by last edit time.
- Remove temporary WebBlocks UI project migration, repair, and payload workflow source from the repository so the CMS keeps only reusable core behavior.
- Fix public gallery and overlay rendering so first-class Gallery blocks and trusted HTML blocks share the canonical page-owned `#wb-overlay-root`, preserve detached overlay targets, and avoid duplicate overlay roots.
- Remove the extra CMS `header-actions.js` public asset because shipped WebBlocks UI `data-wb-*` mode, preset, accent, and dropdown behavior already covers the Header Actions controls.

## 1.25.1

- Fix site export/import so navigation item icons stored on `navigation_items.icon` are included in export payloads and restored during import, preserving sidebar icon rendering after transfer.

## 1.25.0

- Add Site Promotion V1 as a package-based workflow for dry-run-first promotion of site-owned content into an existing target site, including target preselection from Sites and safety-backup plus rebuild integration.
- Add a per-site Sites `Manage` dropdown that replaces crowded row action icons, keeps `Export` available as a modal action, and exposes `Promote to this site` as a target-preselected workflow entry.
- Add a direct Sites row `Export` modal workflow and refine `Export / Import` into one combined operational screen that shows export and import history together and moves `Run Export` and `Run Import` actions into listing card headers.
- Standardize CMS admin form and modal footer actions to one shared WebBlocks UI pattern so card footers and modal footers both keep primary and cancel actions left-aligned, while destructive actions render last in a separate end-aligned danger group when present.
- Refine the Users admin listing by removing the Created column and keeping row actions on one line with the shared nowrap action pattern.
- Add a project-specific PHP indentation guard to enforce the 2-space indentation standard alongside Pint.
- Add a codified AI and development standards foundation with `AGENTS.md`, a repository `.editorconfig`, `pint.json`, and Composer formatting scripts so future work can follow one shared project contract.
- Document a risk-based validation workflow in `DEVELOPMENT.md` so routine feature work uses focused fail-fast coverage first and reserves one full `ddev artisan test` run for the final release gate.

## 1.24.0

- Add slot-editor `Delete All Blocks` actions for page-owned slots and Shared Slots, with confirmation, scoped recursive deletion, preserved other slots, and revision capture through the existing block-deletion audit flow.
- Preserve Pages index site, search, status, sort, direction, and pagination state through Edit Page, slot editing, translation editing, and save flows by carrying a safe admin-only `return_url` plus session-backed Pages index state.
- Replace the broken slot block drag-handle icon dependency with an accessible fallback handle so sortable slot rows still show a clear grip affordance when the missing icon class is unavailable.

## 1.23.1

- Normalize public named JavaScript asset loading so WebBlocks UI, CMS public JS, and page-scoped public JS render from the document `<head>` with `defer` instead of appending normal public scripts at the end of `<body>`.
- Standardize site handle normalization to canonical lowercase hyphenated handles, preserving separators such as dots and underscores as hyphens instead of collapsing them away.
- Standardize `public/site` override conventions around `public/site/{site_handle}/...`, including `css/site.css` and `pages/{page_slug}/page.{css,js}` site and page asset paths.
- Fix tests that generated `public/site/webblocks-ui/` artifacts so test-created public site files are tracked and cleaned up without touching preexisting local transfer content.

## 1.23.0

- Add relational site-scoped `site_variables`, a tabbed `Edit Site` screen (`Site`, `Locales`, `Branding`, `SEO Defaults`, `Variables`), and controlled public-only `{{ site.variable_key }}` token replacement that preserves raw admin content and keeps variable values plain text in HTML-capable contexts.
- Extend site clone and site export/import to include `site_variables`, and allow assigned `site_admin` users to manage Site settings and variables for their sites while assigned `editor` users can view the screen read-only.

## 1.22.1

- Refine the Edit Page `Page Assets` tab into a compact table with header actions plus Add/Edit/Delete modals, so long `/site/...` paths and JS options no longer require large inline asset forms.

## 1.22.0

- Add relational page-scoped `page_assets` support for local `/site/...` CSS and JS files, with a tabbed `Edit Page -> Page Settings` card, super-admin-only asset management, public head and body-end rendering, and portability through revisions, duplicate, move, and site export or import.

## 1.21.0

- Refine the admin Navigation screen so site and menu selectors use the shared compact filter bar, add actions stay in the navigation card header, docs-group help is quieter, and autosave state no longer shows a confusing default `Idle` label.

## 1.20.1

- Standardize admin listing action placement by moving create/upload actions from page headers into listing card headers across core admin index screens.
- Move Pages and Shared Slots site selection into the compact filter toolbar with Search-first ordering.
- Convert the Site Domains add flow to a modal launched from the Assigned Domains card header.
- Add real search, type, and status filters to the Backups admin screen.

## 1.20.0

- Pin runtime WebBlocks UI CDN assets to `v2.7.0` for CSS, icons CSS, and JS so CMS layouts no longer depend on unstable `@master` assets.
- Default `icons:sync-webblocks-ui` to the matching WebBlocks UI `v2.7.0` icon manifest and keep `--manifest` overrides for local files or alternate URLs.
- Resolve fallback icon rendering for newly shipped WebBlocks UI icons by loading the released `v2.7.0` icon bundle instead of the moving `@master` CDN path.
- Add install-level icon catalog management with `System -> Icons`, WebBlocks UI manifest sync from the default CDN or `--manifest=` override, and filtered navigation-context icon pickers for Navigation and Sidebar Navigation editing.
- Fix sidebar-nav item and group admin validation so new block create flows validate icon catalog selections without crashing, and add focused coverage for icon sync, admin visibility, edit-modal reopen behavior, and public `wb-icon-{slug}` rendering.
- Fix navigation item icon persistence for add/edit modal workflows and restore public docs sidebar group toggle interaction.
- Fix project-imported docs sidebar navigation so legacy flat `Patterns / Overview` payloads normalize into real collapsible `Patterns` groups at runtime.
- Fix public sidebar navigation group clicks so CMS no longer double-toggles the shipped WebBlocks UI nav-group contract and instead syncs child-container `hidden` state from the authoritative open or close events.
- Clarify Domains admin and README copy so CMS Domains are described as host-to-site mapping only, while DNS, SSL, and server routing stay outside CMS.
- Improve the site Domains admin screen so `Add Domain` and `Assigned Domains` render as full-width stacked cards, and move admin navigation so `Sites` is a primary sidebar item while `Domains` is the first child under `System` with a generic landing flow.
- Refine the site Domains assigned-row workflow by replacing crowded inline forms with compact action icons plus modal-based manage and remove flows.
- Improve Navigation admin UX with standard WebBlocks modals for add/edit flows, clearer `Add Group` and `Parent Group` behavior for docs menus, group-only nesting validation, and public docs sidebar rendering that opens parent groups when a child page is active.

## 1.19.0

- Add the CMS domain alias foundation with dedicated `site_domains` records, primary-domain canonical URL handling, alias-aware public host resolution, and conservative unknown-host behavior for multisite installs.
- Add admin site-domain management, token-gated `/admin-api/*` domain endpoints, export/import domain metadata handling with conflict skips, and clone behavior that avoids copying live domains by default.
- Add focused test coverage for domain resolution, admin management, internal API auth, portability flows, and alias-aware public URL metadata.
## 1.18.0

- Replace slot-block browser confirm deletion with a WebBlocks admin modal that keeps single-block deletion as the safe default and adds an explicit recursive `Delete block and children` option with child and descendant counts.
- Add server-side scoped recursive block deletion for page-owned and Shared Slot-backed slot editors, including boolean request validation, deepest-first deletes in one transaction, preserved existing single-delete behavior, and clearer flash messaging.
## 1.17.1

- Fix the Sites index Pages count so hidden Shared Slot source pages are excluded from ordinary site page totals.
- Fix site export/import so page Public Shell settings such as Docs are preserved, preventing compatible Shared Slots from being falsely marked incompatible after import.

## 1.17.0

- Improve the Edit Slot Block Picker with server-rendered `Common`, `Layout`, `Content`, `Navigation`, `Advanced`, and `All` tabs, cross-catalog search results, and advanced-tab visibility that still keeps `HTML (Trusted)` super-admin-only.
- Publish `Table`, `TOC`, and `Quote` in the block picker and remove the legacy `Heading` block type in favor of `Header`.
- Expose the existing `HTML` block in the admin Block Picker as a clearly labeled `HTML (Trusted)` advanced block for super admins when static trusted markup is needed.
- Add a super-admin global Blocks index link under `Pages` and give the Blocks listing compact `Search`, `Site`, `Page`, `Block Type`, `Status`, and `Locale` filters.
- Align the Edit Slot Block Types modal filters with the shared compact admin listing filter layout.
- Fix Code block editing from the Edit Slot Blocks list after block catalog and global Blocks index updates.
- Fix public TOC rendering so it collects explicit canonical `Header` anchors from the same page tree, including nested layout blocks, and update the project-layer Foundation payload with explicit subsection anchors.
- Guard the project-layer WebBlocks UI docs setup against clearing existing docs Shared Slot assignments, and add a repair command to restore docs shared-slot wiring while cleaning proven local TOC debug artifacts.
- Add a project-layer `docs-layout` payload/import key for `https://webblocksui.com/docs/layout.html`, with idempotent default-site import, docs navigation reconciliation, and preserved docs Shared Slot assignments.
- Add a project-layer `docs-primitives` payload/import key for `https://webblocksui.com/docs/primitives.html`, with idempotent default-site import and a first-class CMS block mapping for the Primitives docs page.
- Reconcile the docs sidebar navigation idempotently during Primitives import, including the imported `Primitives` page link and the fuller WebBlocks UI docs sidebar order.
- Preserve existing docs header and sidebar Shared Slot assignments during Primitives import and re-import while keeping the page main slot page-owned.
- Add a project-layer `docs-icons` payload/import key for `https://webblocksui.com/docs/icons.html`, with idempotent default-site import and a first-class CMS block mapping for the Icons docs page.
- Reconcile the docs sidebar navigation idempotently during Icons import so one `Icons` item points to the imported CMS page without duplicating other docs entries.
- Preserve existing docs header and sidebar Shared Slot assignments during Icons import and re-import while keeping the page main slot page-owned.
- Improve the project-layer Icons docs import so the CMS page renders a visual shipped-icon grid instead of a text-only icon class list.

## 1.16.0

### Admin Identity

- Separate fixed WebBlocks CMS admin product identity from editable public site branding and metadata.
- Move `Settings` navigation under `System` while keeping `Maintenance` focused on operational tools.
- Add admin-only `Project Name` and `Project Tagline` settings under System Settings.
- Update admin topbar and admin browser titles to use Project Identity while keeping the fixed WebBlocks CMS product identity in the sidebar and version footer.

### Site Metadata / SEO

- Add site-level Branding and SEO Defaults fields, including public favicon and site-scoped head metadata fallbacks.

### Page SEO

- Add locale-aware page SEO overrides on `page_translations`, with public metadata resolving page overrides before site defaults.
- Include page SEO translation metadata in page revisions, duplication, move workflows, and site portability paths where page translations are transferred.

### Public UX

- Change public page title fallback to `Site Label · Page Label` and keep Project Identity out of public metadata.
- Update public search modal copy to name the resolved site being searched when site context is available.

## 1.15.0

### Search

- Add Search V1 as a core CMS feature with a database-backed `public_search_index`, public `/search` and localized search routes, and site plus locale scoped published-only results.
- Add compatible Shared Slot content extraction for Search V1 while excluding hidden Shared Slot source pages, disabled slots, and incompatible Shared Slot assignments from public search results.
- Add a first-class `Search Form` block with translation-backed label, placeholder, and button text plus public WebBlocks UI-aligned rendering.
- Add a super-admin System > Search status screen and non-destructive `ddev artisan search:rebuild` command for rebuilding derived search rows.
- Fix `search:rebuild` so missing search-table migrations fail clearly instead of reporting a misleading successful `Indexed rows: 0 / Skipped pages/locales: 0`, and improve rebuild reporting to count skipped locales meaningfully.
- Add a public search modal opened from the `Header Actions` search trigger while keeping `/search` as the fallback and direct-link route.
- Add public locale-aware `/search.json` endpoints for modal results powered by `public_search_index` with current site and locale scoping.

### Admin UI

- Simplify Page Details modal by folding structure counts into the Page card.
- Refine Page Details modal metadata into grouped cards for readability.
- Update the Pages index Page Details modal to show actor metadata when available while keeping old, deleted-user, imported, and console-created records safe with `Not recorded` fallback.
- Pages index Page Details now uses the standard modal pattern instead of the old drawer.
- Removed the ambiguous Edit Blocks action from Page Details.

### Audit / Revisions

- Add nullable page audit attribution fields for who created, last edited, published, archived, or submitted a page for review.
- Add compact actor, source, and event metadata to page revisions and Shared Slot revisions, and surface it in admin revision history screens.
- Add nullable created/updated audit attribution to Shared Slots.

### Safety / Operations

- Keep destructive database command guards unchanged and document that Search V1 rebuilds derived data without requiring database resets.
- Add a destructive database command safety guard that blocks `migrate:fresh`, `migrate:reset`, `migrate:refresh`, and `db:wipe` outside the testing environment unless `WEBBLOCKS_ALLOW_DESTRUCTIVE_DB_COMMANDS=true` is set.

### Project Layer

- Retarget the project-layer WebBlocks UI Architecture and Foundation imports to the CMS default site by default, with explicit `{ "target": "default_site" }` payload metadata and default-site preview URLs.
- Allow the project-layer Architecture and Foundation imports to recreate those docs pages idempotently on the default site after a local database restore without creating duplicate block trees or duplicate docs navigation items.

### Tests / Internal

- Stabilize `ddev artisan test --filter=Project` by registering the project-layer WebBlocks UI setup and importer services deterministically instead of relying on incidental test order.

## 1.14.0

- Guard existing page updates so the normal Edit Page form cannot move a page to another site; the Site field is now read-only for existing pages and forged site changes return a validation error instead of reaching a database integrity failure.
- Add a dedicated admin `Move to another site` workflow for pages with transaction-safe site reassignment, translated path conflict checks, locale compatibility validation, Shared Slot remapping, and preserved page revisions/content state.
- Add a dedicated admin `Duplicate page` workflow that creates draft page copies within the same site or another accessible site, preserves page-owned content state, validates translated path conflicts, and remaps compatible Shared Slots for cross-site duplicates.
- Improve the `Duplicate page` workflow for Shared Slot-backed pages by showing a target-site Shared Slot compatibility summary, keeping the safe blocking default for missing or incompatible target Shared Slots, and adding an explicit `Disable incompatible Shared Slot-backed slots on the duplicated page` option that writes only the duplicated page's affected slots as disabled instead of persisting invalid cross-site Shared Slot references.

## 1.13.0

### Admin UI

- Add site-scoped Shared Slots admin management with listing, create, edit, delete guarding, and a dedicated Shared Slot block editor that reuses the existing block services.
- Add Page Edit slot source controls so each page slot can switch between `Page Content`, `Shared Slot`, and `Disabled` with same-site compatibility validation, preserved page-owned blocks, and a compact slot-scoped `Manage Source` modal.
- Guard Shared Slot admin routes before migrations so the sidebar icon stays valid and Shared Slot screens fail with controlled admin responses instead of raw SQL errors when schema is not ready.

### Public Rendering

- Add site-scoped Shared Slot public rendering inside the consuming page slot wrapper while preserving page-owned slot rendering, disabled empty output, and backward-compatible handling for legacy null source types.
- Enforce conservative public Shared Slot guards so cross-site references, inactive Shared Slots, incompatible public shells, and mismatched slot names render no shared content.
- Fix Shared Slot render context so reusable blocks use the consuming public page and locale instead of leaking hidden source-page labels into context-sensitive blocks such as Breadcrumb.

### Export / Import

- Add Shared Slot portability so site export/import and site clone include Shared Slot metadata, hidden internal source-page block trees, translations, media references, and handle-based page slot remapping without leaking hidden source pages as ordinary pages.

### Revisions

- Add Shared Slot revision history with a dedicated `shared_slot_revisions` store, automatic capture for metadata and block-tree changes, and in-place restore that preserves Shared Slot ids, page-slot references, translations, and asset references without using `page_revisions`.
- Fix Shared Slot revision migration compatibility for MySQL/MariaDB by assigning explicit short foreign key constraint names in `shared_slot_revisions`.

### Internal / Tests

- Add hidden source pages for internal Shared Slot block ownership so the existing block editor, translation, and asset flows can be reused without exposing those pages as normal site content.
- Fix Shared Slot block editor redirects so block create, update, and delete flows stay in the current Shared Slot editor instead of falling back to `/admin/blocks` when editing through hidden source pages.
- Keep the Edit Page Shared Slot source modal on one slot-scoped trigger path so each `Manage Source` action opens exactly one modal without duplicate handlers.

## 1.12.0

### Admin UI

- Standardized shared compact listing filters across the `Block Types`, `Pages`, `Media`, `Contact Messages`, and `Users` index screens so search stays prominent, compact filters stay grouped, and apply or reset actions remain aligned on one toolbar row when space allows.
- Aligned dense admin pagination with the shared WebBlocks UI contract, including compact same-line `from-to/total` summaries and query-preserving page links on these core listing screens.

### Public Rendering

- Removed generic public block wrappers from root-owning layout and content-shell blocks while preserving shell and slot ownership and keeping public block metadata on each renderer root.
- Simplified `Code` block public rendering to escaped `<pre><code>` output without the old card chrome or visible language label, while preserving sanitized non-visual language metadata.

### Release / Project Boundary

- Clarified the CMS core versus `project/` boundary in core documentation and kept website-specific sync and import workflows out of CMS core.
- Excluded install-specific project-layer content from published release packages.

### Internal / Tests

- Added regression coverage that prevents website-specific docs sync commands or references from leaking into CMS core.

## 1.11.0

- Replace Rich Text's Markdown-like admin editing model with a dependency-free safe HTML editor that stores restricted safe HTML fragments instead of marker-based text.
- Keep Rich Text public rendering on the sanitized WebBlocks UI `wb-rich-text wb-rich-text-readable` primitive while preserving the approved safe HTML fragment model without raw unrestricted HTML, public JavaScript, or a third-party editor dependency.
- Fix the safe HTML Rich Text editor toolbar so contenteditable selections are preserved across toolbar clicks and formatting actions apply reliably through saved range tracking, lazy editor initialization, submit-time sync, and browser output normalization for `<strong>` and `<em>`.

## 1.10.0

- Align Rich Text public rendering with the WebBlocks UI `wb-rich-text` primitive while preserving sanitized Markdown-like body copy behavior.
- Fix Rich Text editor toolbar actions so repeated clicks toggle existing Markdown-like formatting instead of duplicating wrappers, links, or list prefixes.
- Improve the Rich Text admin editor by presenting safe formatting controls in a compact WebBlocks UI-aligned toolbar.

## 1.9.0

- Fix Rich Text block visibility on existing installations so the block remains available in the picker.
- Improve the Block Picker with catalog-style rows, column headers, and combined category filtering, search, and sorting.
- Add a safe Markdown-like Rich Text editor toolbar for body copy with bold, italic, code, links, and simple lists.
- Render Rich Text content through allowlisted safe formatting for paragraphs, emphasis, inline code, links, and simple lists without raw HTML.
- Preserve Card description inline-only rich text behavior while expanding Rich Text block editing capabilities.

## 1.8.0

- Improve the Edit Page admin screen by moving Slots into a dedicated card separate from page settings.
- Refine the Edit Page and Add Page admin forms by removing the redundant Site Context field and restoring a compact Add Slot dropdown.
- Split slot create, delete, and reorder persistence from the page settings update form.
- Resolve public slot wrappers from page `Public Shell` plus slot name, remove editor-facing slot wrapper controls and the obsolete slot settings route, and strip legacy saved `wrapper_element` and `wrapper_preset` values from slot writes, imports, revisions, clones, and existing records.
- Fix update checks so the update client compares against the persisted installed version when present, and align the system settings coverage with the current admin information panel wording.
- Fix the Edit Slot Blocks admin table on narrow screens by keeping block type, children, status, and actions readable while allowing horizontal scroll instead of letter-by-letter wrapping.
- Fix the admin mobile sidebar close behavior by aligning its backdrop placement with the standard WebBlocks UI sidebar pattern so outside clicks dismiss the sidebar in narrow view.
- Compact the Edit Slot block tree so visible rows use short plain-text summaries instead of long content previews, while preserving nested structure editing and block actions.
- Simplify the Edit Slot block tree back to compact rows only by removing expandable detail previews and keeping full content editing in the block editor UI.
- Further compact the Edit Slot Blocks table to one visual row per block by removing secondary metadata/summary lines and adding a dedicated children-count column.
- Fix block catalog reseeding so product block types like `Rich Text` are promoted back to their published metadata on existing installs, and fix the admin `Code` block editor locale flag crash.
- Add a first-class `Rich Text` CMS block with a small dependency-free admin editor, translation-backed storage, server-side HTML sanitization, and safe public rendering.
- Clean up repository structure.
- Move internal audit documents outside repository.
- Refactor README and documentation structure.
- Improve docs entry point (`docs/index.md`).

## 1.7.0

- Add Site Promotion V1 under `Admin -> Sites -> Promote` with package inspection, required dry run, additive-update and mirror strategies, safety backup creation before apply, preserved runtime-data rules, and target search rebuild after promotion.
- Header Actions now renders the missing theme preset controls in the public theme dropdown so the CMS output matches the static WebBlocks UI preset and accent contract.
- Fix backup restore completion flow so successful full-system restores return to the backups index instead of a stale backup detail URL after the database is overwritten.
- Simplify the Backups screen actions by removing duplicate upload, cancel, and System Updates controls, and clarify failed stale backup messaging.
- Fix backup archive lifecycle so deleting backups removes stored archive files and restoring existing backups does not duplicate the source archive while preserving mandatory safety backups.
- Fix backup delete file removal by aligning backup archive deletion with the working export delete storage behavior.
- Fix restore safety backup lifecycle so successful restores do not leave backup records stuck in running status.
- Simplify backup and export storage by removing nested YYYY/MM/DD directories and using flat archive paths.
- Fix backup archive storage so created and uploaded backups use the backups disk instead of export-related storage paths.
- Simplify backup and export archive paths to flat filenames while keeping backup uploads separate from site transfer uploads.
- Enable `Include media/assets` by default on Create Export.
- Remove the random prefix from site export archive filenames so exports use clean timestamp-based names.

## 1.6.0

- Improve admin dashboard shortcuts and add System Updates quick action.
- Fix the admin Media sidebar icon and move visitor reporting below the primary dashboard overview cards.
- Add first-class breadcrumb block support with a dedicated admin editor and semantic public breadcrumb rendering for header or context bar usage.
- Replace dashboard-first public layout rendering with a semantic docs shell using Holy Grail DOM order.
- Introduce the `docs` public shell preset and normalize legacy `dashboard` values to the docs shell.
- Ensure public layout structure is controlled by page and slot shell settings instead of block markup.
- Add first-class Header Actions block support for compact header utility controls such as color mode and accent actions.
- Fix docs header alignment so `Docs Navbar` uses a full-width WebBlocks UI topbar row instead of spacer divs.
- Fix Header Actions icon rendering by switching to the real WebBlocks UI `<i class="wb-icon ...">` contract.
- Fix Header Actions behavior so mode icon state stays in sync with `data-wb-mode-cycle` and the accent dropdown stays anchored through the standard WebBlocks UI dropdown hooks.
- Improve public light/dark/auto mode compatibility by replacing site-level hardcoded public colors with WebBlocks UI token-driven styling.
- Add first-class docs sidebar blocks for aside brand, navigation, nav groups, nav items, and footer content while keeping the outer docs sidebar wrapper owned by page and slot shell presets.
- Align the docs public shell with the real WebBlocks UI dashboard/sidebar DOM contract, including canonical shell landmark order, `docsSidebar` targeting, and `wb-dashboard-main` output.
- Add shared logo media support to `sidebar-brand` and align its public HTML with the real WebBlocks UI sidebar brand contract.
- Align the Media index filters and list layout with the standard admin listing pattern.
- Add navigation-backed docs sidebar rendering with optional navigation item icons while preserving manual sidebar block fallback.
- Move WebBlocks UI docs navigation and Getting Started import commands out of core and into the project layer.
- Fix Docs Navbar alignment by removing constrained container classes from the full-width flex row.
- Fix Docs Navbar visual width by applying full-width layout to the navbar element itself, not only the inner flex row.
- Fix docs shell layout structure so the navbar and main content share the right-side content column instead of becoming competing flex items.
- Normalize new site export package storage to `storage/app/exports/YYYY/MM/DD/` without changing legacy export record compatibility or backup storage behavior.

## 1.5.0

### Backup & Restore

- Fix invalid SQL dump handling and prevent command output from being treated as SQL.
- Add SQL dump validation before restore and during archive inspection.
- Allow cleanup of stuck running backups with explicit confirmation.
- Improve backup delete behavior and guard conditions.
- Fix safety backup foreign key issue during restore.
- Improve restore error messaging and avoid duplicate validation alerts.

### Site Management

- Fix site deletion failing when page revisions exist.
- Include page_revisions in deletion scope.

### Admin UI

- Standardize all admin table Actions columns:
  - consistent left-aligned action groups
  - unified markup using wb-action-group
  - consistent DELETE form usage for destructive actions
- Improve backups list layout:
  - remove unnecessary columns (Type, Duration)
  - align actions with header
- Improve restore history table actions

### Internal

- Add SqlDumpContentValidator for backup safety
- Improve backup and restore service layering
- Strengthen test coverage for backup/restore and admin UI consistency

## 1.4.7

### Changed

- Extracted the large inline admin JavaScript block from the admin layout into named admin assets under `public/cms/js/admin/`.
- Organized Edit Slot and related admin behavior into page-safe modules covering core admin state reset, password fields, asset picking, inline block building, structured builder items, slot building, slot block expanded-state syncing, and page-builder modal handling.
- Updated admin layout loading so WebBlocks UI is followed by versioned named admin JavaScript assets instead of injecting a monolithic inline script block into page HTML.
- Updated admin-facing tests to validate named asset loading and current pages index behavior rather than the previous inline-script markup assumptions.

### Documentation

- Documented the admin JavaScript asset convention in `README.md` so new admin behavior is added through named assets instead of large Blade inline scripts.

### Verification

- Verified with `ddev artisan test` passing: 431 tests.

## 1.4.4

### Changed

- Clarified that CMS core keeps generic site export/import only, while website or demo content should ship as native CMS export/import snapshots.

### Removed

- Removed the hard-coded UI docs pilot website content generator command from CMS core.
- Removed the remaining core registration path for website-specific UI docs rebuild tooling so those commands no longer ship in the core command list.

## 1.4.3

### Added

- Added a non-production missing-renderer diagnostic view so missing public block renderers fail clearly during development instead of silently delegating to unrelated renderers.

### Changed

- Enforced the public renderer convention that a block slug resolves to the matching Blade renderer filename under `resources/views/pages/partials/blocks/{slug}.blade.php`.
- Kept `link-list` and `link-list-item` as first-class core block types with slug-matched public and admin renderer files.
- Aligned public `link-list`, `link-list-item`, and TOC link-list output to the canonical WebBlocks UI link-list DOM contract using `wb-link-list`, anchor-level `wb-link-list-item`, direct `wb-link-list-main`, and direct `wb-link-list-desc` elements.

### Fixed

- Prevented `link-list-item` blocks from being created under unrelated parent blocks by enforcing `link-list` as the canonical managed container parent.
- Preserved legacy `slider` public output under the slug-to-renderer rule by adding a dedicated `slider` renderer instead of relying on fallback delegation.

## 1.4.2

### Added

- First-class `feature-grid` block support with dedicated admin editing, translation-backed container copy, and stable public rendering.
- First-class `feature-item` block support as the managed child block for `feature-grid`, with translated title and content plus an optional shared URL.
- First-class `cta` block support with dedicated admin editing, translation-backed marketing copy, and managed child CTA buttons.
- Dedicated admin and public renderer partials for `feature-grid`, `feature-item`, and `cta`.

### Changed

- Registered `feature-grid`, `feature-item`, and `cta` in the translation registry so their editorial copy is stored authoritatively in block translation rows.
- Extended builder-managed child handling so `feature-grid` owns structured `feature-item` children and `cta` manages up to two child `button` CTAs.
- `feature-grid` now keeps backward compatibility for existing legacy `column_item` children while treating `feature-item` as the canonical child block slug.

### Fixed

- Preserved shared CTA fields during locale-specific `cta` edits so translated updates cannot overwrite the canonical shared `variant` or shared button URL data.

## 1.4.1

### Fixed

- Preserved numeric zero values in `column_item` public rendering so stats and similar blocks do not treat `0`, `"0"`, or `0.0` as empty fallback content.

## 1.4.0

### Added

- Project Layer V1 with support for update-safe instance-specific providers, routes, config, views, and scaffold generation under `project/`.
- New `ddev artisan project:init` scaffold command for creating the initial `project/` structure without overwriting existing files.

### Changed

- Composer autoload now includes the `Project\\` namespace for install-local project classes.
- CMS documentation now defines the boundary between reusable core code and update-safe project-specific code.

### Fixed

- System update package installation now preserves `project/` alongside `.env` and `storage/` so CMS updates do not overwrite existing project-layer files.

## 1.3.0

### Added

- First-class `link-list` and `link-list-item` blocks with translated container copy plus editable item metadata for title, meta, description, and URL.
- Reusable inline admin item management for link-list children aligned with the existing structured builder patterns.

### Changed

- Replaced the legacy editorial link block with first-class `link-list` and `link-list-item` blocks.
- Aligned CMS editorial link rendering directly to the WebBlocks UI link-list pattern.
- Updated the admin editor, docs pilot rebuild content, seeded showcase content, and public renderer to use the new link-list model.
- UI docs pilot content continues to write pages and blocks through `BlockPayloadWriter` with translation-backed block storage where supported.
- CMS documentation continues to clarify the product boundary between reusable core features and project-specific migration scripts.

### Fixed

- Removed pilot-page drift by keeping the docs migration command idempotent and block-tree based instead of appending content on reruns.
- Prevented columns from doubling as semantic link-list content by keeping `column_item` dedicated to columns-only rendering.

## 1.2.0

### Added

- First-class CMS support for `hero`, `code`, and the editorial link block later replaced by `link-list`, with dedicated admin editors and stable public renderers aligned to WebBlocks UI primitives.

### Changed

- Improved translation support for docs and marketing blocks so `hero`, `code`, and the editorial link block later replaced by `link-list` consistently store translated copy in block translation rows.
- The earlier editorial link block moved toward child-based link structures before being fully replaced by `link-list` and `link-list-item`.

### Fixed

- Improved shared-settings handling in `BlockRequest` so shared hero and code configuration stays stable during translated edits.

## 1.1.1

### Added

- Hero block tests covering renderer behavior, translation handling, multisite context, and managed admin CTA persistence.

### Changed

- Hero block strengthened into a first-class editorial block with a dedicated admin form, clearer translation ownership, managed CTA fields, and WebBlocks UI-aligned renderer structure.
- CMS documentation now clarifies the product boundary between reusable core features and project-specific migration scripts.

### Fixed

- Hero CTA rendering now skips empty buttons, keeps actions inline, and avoids leaking local environment-specific values into content handling.
- Removed the site-specific legacy Fklavye sandbox importer from CMS core and dropped its project-only test coverage from the product repository.
- Generic site export/import behavior remains unchanged and covered by the existing release test paths.

## 1.1.0

### Added

- First-class List block with structured editor and semantic rendering.
- First-class Table block with header-row support.
- First-class editorial link block using the WebBlocks link-list pattern, later replaced by `link-list`.
- First-class Accordion block using semantic `<details>` disclosure.
- Semantic Video and Audio block renderers.
- File download block alignment with WebBlocks button primitives.
- Minimal Map block with safe external link behavior.

### Changed

- Hero block fully aligned with the WebBlocks promo pattern.
- Button rendering normalized across all supported public contexts.
- Columns and Column Item rendering unified with explicit grid, card, stat, and link-list variants.
- Code block promoted to a safe semantic `<pre><code>` renderer.
- TOC block promoted to minimal navigation using heading anchors.
- FAQ and FAQ-list consolidated into a coherent disclosure system.
- Marketing blocks (`stats`, `metric-card`, `feature-grid`, `testimonial`) consolidated into core primitives.
- Public layout modes stabilized around `stack`, `sidebar`, and `content-ready` composition.
- Preserved compatibility for existing fallback-style list and table settings data while moving editorial workflows to dedicated structured inputs.

### Fixed

- Removed usage of non-existent UI classes such as `wb-prose` and `wb-cluster-3`.
- Eliminated duplicate card and grid rendering logic across aligned public blocks.
- Prevented unsafe HTML output in multiple public block renderers.
- Ensured empty wrappers are not rendered.

### Deprecated / Deferred

- Tabs block deferred until WebBlocks UI provides a real tabs pattern.
- Slider block deferred; use Gallery instead of introducing a custom carousel system.
- Feature Grid and Metric Card remain transitional and are merged into Columns-oriented primitives.
- FAQ-list remains transitional; use Accordion for new grouped disclosure content.
- Commerce and generic form-builder blocks remain on the fallback/custom path.

## 1.0.6

### Added

- Hero block promoted to a first-class WebBlocks UI-aligned block.
- Button variant normalization and structured CTA rendering through child Button blocks.
- Columns and card grid alignment with shipped WebBlocks UI primitives.
- Code block first-class renderer with safe escaped `<pre><code>` output.
- Minimal TOC block rendering from explicit heading anchors.
- Public layout modes for stack, sidebar, and content-ready composition.

### Changed

- Public block rendering now consistently uses shipped WebBlocks UI primitives across the Phase 3-aligned block set.
- Section block now supports promo semantics safely.
- Button rendering is normalized across supported public contexts.
- Columns and column items now render through explicit parent-driven variants.

### Fixed

- Removed usage of non-existent UI classes such as `wb-prose` and `wb-cluster-3` from public rendering paths.
- Removed forced card wrappers in layout and columns-related rendering where blocks should control their own framing.
- Prevented empty or invalid HTML output in multiple public block renderers.

### Notes

- FAQ remains non-accordion in this release.
- TOC is intentionally minimal and does not auto-generate anchors.
- Feature grid remains fallback-oriented.


## 1.0.5

- Inline release helper scripts into the GitHub Actions release workflow.
- Remove the obsolete local `scripts/` directory.
- Keep CMS product identity and version centralized in `App\Support\WebBlocks`.

### Stability & Integrity
- Data model unified around translation tables for page identity and translatable block content.
- Legacy page title and slug storage removed from active page identity handling.
- Multisite, locale, navigation, and block translation integrity hardened without changing public routing behavior.
- Request-level validation improved so invalid translation, block locale, and cross-site navigation writes fail before DB exceptions where practical.
- Runtime URL generation and public route resolution verified to stay aligned across pages, navigation, and admin previews.
- Revision restore, clone, export/import, and legacy import reconstruction paths hardened while keeping compatibility normalization isolated.

### Internal
- Legacy compatibility paths isolated to reconstruction, import, migration, and backfill workflows.
- Contact form submit and success copy moved out of block settings and treated as translation-owned content.
- Extensive integrity, regression, and edge-case coverage added across multisite, multilingual, validation, URL, and reconstruction flows.
- Refine the fresh CMS welcome screen with a stronger WebBlocks UI-native product introduction and clearer first actions.
- Add development and release workflow documentation, clarify the dev installed-version synchronization policy, and document the local development update boundary in README.

## 1.0.4

- Fix public slider inline JavaScript syntax issue.
- Move public consent synchronization JavaScript into a CMS core static asset.
- Move public slider behavior into a CMS core static asset.
- Move public footer fallback CSS into a CMS core stylesheet.
- Document CMS core public asset boundaries and keep install-level overrides separate.

## 1.0.3

- Make the page translation site integrity migration fully retry-safe by skipping already-created indexes and constraints during partial upgrade recovery.

## 1.0.2

- Fix MariaDB upgrade failure in the page translation site integrity migration by avoiding removal of indexes required by foreign key constraints.

## 1.0.1

- Reorder the admin sidebar so System appears before Maintenance.

## 1.0.0

First stable release of WebBlocks CMS.

- Introduces a complete block-based CMS with multisite support
- Adds role-based access control (`super_admin`, `site_admin`, `editor`)
- Adds Editorial Workflow V1 (`draft`, `in_review`, `published`, `archived`)
- Adds Page Revisions and Restore V1
- Adds Install Wizard V1 for browser-based setup
- Includes media management, navigation, and page builder
- Includes Export / Import, Backup / Restore, and Updates system

## 0.4.0

- Add Users Phase 1 with admin-managed user system including create, edit, delete, active/inactive state, and last login tracking.
- Add Users Phase 1.5 with search, role/status filters, and improved admin UX.
- Add Users Phase 2 with role-based user model using `super_admin`, `site_admin`, and `editor`.
- Add install-level users with site-scoped access via `site_user` assignments.
- Add server-side enforcement of site access across major admin areas.
- Add `super_admin`-only access to system-level features including users, updates, backups, and settings.
- Maintain backward compatibility by keeping `is_admin` as a temporary bridge while transitioning to role-based authorization.

## 0.3.3

- Add Visitor Reports Phase 2 on top of the stable `0.3.x` line so installed CMS sites can receive the update through the normal updater.
- Extend public visitor tracking with sanitized nullable `utm_source`, `utm_medium`, and `utm_campaign` capture, plus optional `CMS_VISITOR_UTM_ENABLED` control.
- Expand `/admin/reports/visitors` with Top Campaigns, Source Breakdown, and Medium Breakdown cards that continue to respect date range, site, and locale filters.
- Add a compact Visitor Summary widget to `/admin` with the last 7 days of page views, unique visitors, and top page context.
- Document the Phase 2 tracking model, campaign reporting behavior, privacy notes, limits, and dashboard summary updates in the Markdown docs.

## 0.3.2

- Promote Visitor Reports V1 release to the 0.3.x line so it becomes visible as the latest stable update.
- No functional changes compared to 0.2.1.

## 0.2.1

- Add Visitor Reports V1 with a compact admin screen at `/admin/reports/visitors`.
- Implement lightweight public visitor tracking backed by the new `visitor_events` table with multisite-aware and locale-aware reporting queries.
- Keep tracking privacy-safe by storing `ip_hash` instead of raw IP addresses and documenting the feature, config, and V1 limits in the README.

## 0.3.1

- Fix release workflow script invocation so release note generation and archive builds run reliably in GitHub Actions even when executable bits are not preserved.
- Include post-merge stabilization after the integrated multisite and site-management release flow.
- Retain the combined multisite, site clone/import, site delete, settings, and sidebar improvements introduced across the 0.3.x line.

## 0.3.0

- Merge the multisite and multilingual foundation into the main line as the base for site-aware admin and public flows.
- Add first-party site clone and export/import workflows for controlled duplication and package-based transfer between installs.
- Add site deletion safeguards, a minimal system settings screen, and reorganized System and Maintenance navigation in the admin sidebar.
- Improve controlled system settings persistence and clarify admin UX across site-management flows.

## 0.2.0

- Ship the first real multisite and multilingual core release with legacy single-site upgrade migrations for existing `0.1.8` installs.
- Preserve default public routing by creating a primary `default` site, seeding `en` as the default locale, backfilling legacy pages and translatable block content, and keeping default-locale URLs prefixless.
- Publish the release through the stable update channel with an explicit `minimum_client_version` of `0.1.8` so installed legacy sites can detect and apply the upgrade through the normal updater.
- Add first-party Export / Import V1 as a portable site package workflow for migration, duplication, and transfer between installs.
- Keep Export / Import explicitly separate from Backup / Restore with dedicated admin screens, package storage, package validation, audit tables, and artisan commands.
- Support site package export/import for site records, locale assignments, pages, page translations, page slots, blocks, block translations, navigation, and optional media/assets with safe archive validation and ID remapping on import.
