# Package Architecture Transition

## Why The Current Root-Managed Model Is Problematic

WebBlocks CMS currently ships as a root-managed Laravel application where CMS core files live directly in the installation root alongside user-owned application files. That model makes upgrades fragile because CMS product code and install-specific project code share the same top-level filesystem.

When CMS updates are applied by replacing or copying root-managed files, removed upstream core files are not guaranteed to disappear from downstream installs. A stale removed core file can remain in the install root, still be autoloaded or rendered by Laravel, and silently override newer shipped behavior. This is exactly the class of problem recently confirmed by a stale root Blade file in a downstream install.

The current architecture also keeps CMS updates tightly coupled to Git state and root-wide file synchronization. That makes it harder to reason about ownership boundaries, harder to preserve install-specific customization safely, and harder to make updates predictable across existing installs.

## Target Direction

The target architecture is a Composer-managed Laravel package:

- Package name: `fklavyenet/webblocks-cms`
- Installed path: `vendor/fklavyenet/webblocks-cms`

In the target model, CMS core code is installed and updated as a normal Composer package instead of being copied into the user-owned Laravel project root.

This means:

- CMS PHP source belongs under package `src/`
- Laravel package resources stay in package-level `config/`, `database/`, `resources/`, `routes/`, `public/`, and `stubs/`
- `src/` is not where every package file should go
- the user-owned Laravel root should own `app/`, `config/`, `database/`, `public/site/`, `resources/`, `routes/`, `storage/`, and `composer.json`

`project/` may remain temporarily as a compatibility layer during the transition, but it should not remain the long-term required customization model for install-specific behavior.

The expected starter-project direction is a separate Laravel starter such as `fklavyenet/webblocks-cms-starter`, where the user-owned project root composes the CMS package instead of embedding all CMS core files directly.

## Target Update Architecture

The long-term update flow should be Composer/package-managed and Git-agnostic.

The target update architecture is:

- no root-wide CMS core file copying
- CMS core installed and updated through Composer packages
- controlled runtime upgrade steps after package update, including migrations
- catalog synchronization where needed
- `block-types:sync-core` execution where needed
- cache clear where needed
- controlled asset publish or sync only when actually required

This keeps install-owned root files separate from package-owned CMS core files and prevents removed CMS core files from lingering indefinitely in downstream installs simply because they once existed in the root.

## Ownership Boundaries

Target package-owned CMS paths:

- `packages/webblocks-cms/src/` during the in-repo transition phase
- later `vendor/fklavyenet/webblocks-cms/src/` in installed environments
- package `config/`
- package `database/`
- package `resources/`
- package `routes/`
- package `public/`
- package `stubs/`

Target user-owned project-root paths:

- `app/`
- `config/`
- `database/`
- `public/site/`
- `resources/`
- `routes/`
- `storage/`
- `composer.json`

This split keeps Laravel application ownership with the install while CMS product ownership moves into the package.

## Migration Phases

### Phase 0: Document And Scaffold

Introduce the package architecture plan and create an in-repo package skeleton without moving runtime code yet. Root app behavior remains unchanged.

### Phase 1: Minimal Package Bootstrap

Add the package service provider and local path Composer wiring so the package exists as a real installable unit inside the repository. Keep boot logic intentionally minimal and avoid changing runtime behavior.

### Phase 2A: Bootstrap Contract

Refine the package service provider so it defines the bootstrap contract for future package-owned resources without making those resources authoritative yet.

In this phase, the provider may safely prepare guarded loading and publish registration for package `config/`, `routes/`, `resources/views/`, `database/migrations/`, `public/`, and `stubs/`, but only when real package files exist.

Current root runtime behavior still remains unchanged because the package skeleton only contains placeholders. Until runtime files are actually moved into the package in later phases, the root Laravel application remains the authoritative source for active CMS routes, views, config, migrations, and public assets.

The first package-owned default config path has now started with package `config/webblocks-updates.php`. During the transition, that package file provides CMS-owned defaults while the existing root `config/webblocks-updates.php` remains in place as the install-level override and backward-compatible application config file.

### Config Classification

Current root `config/` files fall into two transition groups.

CMS product default candidates:

- `cms.php`
- `contact.php`
- `demo_media.php`
- `webblocks-updates.php`

Laravel app-owned or install-owned config that should stay root-owned:

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

The current low-risk transition rule is to move CMS-owned default config only when it preserves stable product behavior and clear install override semantics. The initial package-owned default set now includes `cms.php`, `contact.php`, `demo_media.php`, and `webblocks-updates.php`. The root config files remain in place during the transition as install-level overrides and backward-compatible application config entry points.

### Phase 2: Move Clearly Package-Owned Source

Begin moving CMS-owned PHP source into package `src/` in small reviewable slices, updating namespaces and service provider bootstrapping only as each moved area is ready.

Package console bootstrap is now also proven through the read-only diagnostic command `webblocks:package-status`. That command is package-owned, registered only in console contexts, and reports package bootstrap presence without changing files, database state, cache, config, or update state.

The first PHP source move should follow equally conservative criteria: CMS-owned, small, easy to reason about, no database or Eloquent dependency, no controller or request dependency, and narrow reference updates. The first moved class is `SearchTextNormalizer`, a pure search-text helper now owned by package `src/Support/Search/`.

Current Search support boundary:

- package-owned now: `SearchTextNormalizer`, `PublicSearchRebuildResult`
- later, because they depend on DB/models/runtime indexing or schema: `PublicSearchIndexer`, `PublicSearchQuery`, `PublicSearchSchema`, `SearchablePageResolver`, `BlockSearchTextExtractorRegistry`, `ReindexesPublicSearch`
- no Search support class currently needs to remain root-owned for project-specific reasons, but the remaining classes still require dependency-by-dependency review before moving

Current non-Search Support audit boundary:

- no additional non-Search Support helper was moved in this audit step because none of the reviewed candidates met the current low-risk package-owned criteria
- `MediaKindResolver` is small and deterministic, but it currently depends on `App\Models\Media` constants and is referenced from a controller path, so it is not yet independent enough for this phase
- `DatabaseExecutionStrategyResolver` remains root-owned for now because it directly affects database dump or restore execution strategy, DDEV detection, environment inspection, and backup or restore runtime safety
- `SiteHandle` remains root-owned for now because it is used by models, requests, and site transfer or clone flows, so moving it now would cross routing, portability, and persistence-adjacent boundaries too early
- `SiteDomainNormalizer` remains root-owned for now because it is still used by models, requests, route resolution, and migrations, confirming the earlier risk assessment

Contact support boundary:

- package-owned now: `ContactMessageNotificationResult`
- root-owned for now: `ContactMessageNotifier`, because it still owns mail transport calls, contact model interaction, and config-based recipient resolution
- `ContactMessageNotificationResult` was safe to move because it is a tiny immutable result object with no model, DB, request, config, mail, migration, or serialized payload coupling and only a narrow notifier reference update

Block type support boundary:

- package-owned now: `BlockTypeContract`
- root-owned for now: `BlockTypeContractRegistry`, because it still depends on block models, catalog sync definitions, translation registry behavior, resource-path inspection, and the root-owned audit or admin flows that consume those resolved contracts
- `BlockTypeContract` was safe to move because it is a small contract DTO with constructor-only state plus deterministic array serialization and no model, DB, request, config, command-side-effect, migration, or serialized payload coupling

Page layout markup boundary:

- root-owned for now: `LayoutMarkup`
- `LayoutMarkup` was not moved in this step because, although it is stateless and small, it is used directly by page-layout form requests, page layout manager logic, public slot wrapper resolution, and an admin Blade form; moving it now would cross request-validation and public-rendering boundaries inside the broader Pages or PublicRendering area that is still intentionally root-owned

Formatting support boundary:

- package-owned now: `InlineRichTextRenderer`
- root-owned for now: `SafeRichTextRenderer`, because it still owns the richer HTML sanitization contract, allowed-tag behavior, DOM parsing rules, and public rich-text rendering semantics
- `InlineRichTextRenderer` was safe to move because it is a small deterministic formatter with no model, DB, request, config, migration, or serialized payload coupling and only a narrow Blade plus unit-test reference update

Support source migration map:

- Search: candidate after dependency isolation. Pure helper or value-object pieces are already package-owned, but indexing, schema, page resolution, and block extraction still depend on models, DB tables, and runtime indexing flow.
- Formatting: candidate after dependency isolation. `InlineRichTextRenderer` is now package-owned as the low-risk formatting helper, while `SafeRichTextRenderer` remains root-owned because it defines the higher-risk sanitization behavior.
- BlockTypes: candidate after dependency isolation. `BlockTypeContract` is a small value object, but the namespace is anchored by `BlockTypeContractRegistry`, admin routes, and a root console audit command.
- BlockTypes: candidate after dependency isolation. `BlockTypeContract` is now package-owned as a narrow value-object move, but the namespace is still anchored by `BlockTypeContractRegistry`, admin routes, and a root console audit command.
- Media and Assets: do not move yet. These classes are tied to `Media` model constants, media usage queries, legacy asset compatibility, controller upload flows, and file-write behavior.
- Pages and PublicRendering: root-owned until a dedicated migration phase. These classes control route resolution, layout selection, page assets, slot wrappers, public presenters, page duplication or import, and public rendering behavior with model and request coupling throughout.
- Pages and PublicRendering: root-owned until a dedicated migration phase. `LayoutMarkup` was reviewed as a possible exception, but it remains root-owned because it still sits directly on page-layout requests, slot-wrapper resolution, and admin-view rendering even though its own logic is stateless.
- Blocks: root-owned until a dedicated migration phase. This area owns block payload writes, translation persistence, block deletion, catalog sync, trusted HTML extraction, and request-scoped public registries that sit directly on block persistence and renderer contracts.
- SharedSlots and Revisions: root-owned until a dedicated migration phase. These classes depend on block trees, revision tables, schema checks, translation rows, and restore or snapshot semantics.
- Sites, Sites\\ExportImport, and SitePromotion: root-owned until a dedicated migration phase. These areas are tightly coupled to models, routing, portability, archives, serialized transfer payloads, clone or delete flows, promotion safety backups, and public-site resolution.
- System and System\\Updates: root-owned until a dedicated migration phase. These classes own settings persistence, installed-version state, backup or restore, SQL validation, update download or extract flow, and database execution strategy.
- Install and ProjectLayer: do not move yet. These classes touch installer flow, `.env` writes, route or provider loading, install-state checks, and project-root customization boundaries.
- Navigation, Locales, Users, Visitors, Contact, and Icons: candidate after dependency isolation. Each group contains some smaller helpers or result objects, but current implementations still rely on models, auth, mail, schema inspection, HTTP fetches, or settings-backed runtime behavior.
- Navigation, Locales, Users, Visitors, Contact, and Icons: candidate after dependency isolation. `ContactMessageNotificationResult` is now package-owned as a narrow value-object move, while the remaining groups still rely on models, auth, mail, schema inspection, HTTP fetches, or settings-backed runtime behavior.
- Admin, Audit, and Database: candidate after dependency isolation. `AdminPagination`, `CurrentActorResolver`, and `DestructiveDatabaseCommandGuard` are small, but each still hangs off root settings, auth, or application safety hooks.
- WebBlocks: do not move yet. It represents product identity and version constants and is explicitly excluded from this transition step.

Phase 2 source checkpoint note:

- the initial low-risk helper and value-object moves completed successfully through `v1.31.60`
- `fklavye.ddev` was updated successfully after `v1.31.60`, confirming the checkpoint remains compatible with the current local package-wired development environment
- opportunistic low-risk PHP source moves are now intentionally paused
- do not continue moving runtime-heavy classes without a dedicated focused phase plan and dependency audit

Current blockers for higher-risk groups:

- direct `App\Models\...` or Eloquent query coupling across Search, Pages, Blocks, Sites, Navigation, Locales, Icons, Visitors, and System
- request, route, controller, or view coupling in Admin, Pages, PublicRendering, Formatting, and some BlockTypes helpers
- schema, migration-shape, or table-existence checks in Search, SharedSlots, Revisions, Visitors, Install, and System
- config, env, HTTP, mail, filesystem, process, backup, update, and DDEV runtime coupling in Contact, Icons, Install, System, and Updates
- serialized archive or transfer payload coupling in Sites\\ExportImport, SitePromotion, Page import, and legacy asset compatibility helpers

### Phase 3: Move Package Resources

Move clearly package-owned config, routes, views, migrations, seeders, public assets, and stubs into package-level Laravel resource folders. Introduce package load and publish behavior incrementally instead of all at once.

## Next Phase: Package Resource Boundary

The next transition focus after `v1.31.60` is package resource ownership, not more opportunistic helper moves.

### v1.31.62 Package Resource Boundary Pilot

The `v1.31.62` checkpoint turns the package resource boundary into a more explicit and testable pilot without transferring active runtime ownership yet.

- package `routes/`, `resources/views/`, `database/migrations/`, `public/`, and `stubs/` now exist as explicit reserved package boundary directories with marker files that document future ownership intent
- package config defaults remain CMS-owned under package `config/`
- matching root config files remain the install-level override layer and backward-compatible application config entry points
- the package service provider keeps package publishing explicit and package-tagged, but publishing remains inert unless a developer intentionally runs `vendor:publish`
- the package view namespace `webblocks-cms` is now registered as a safe package boundary pilot without changing current root admin or public view resolution
- package routes, package views, package migrations, package public assets, and package stubs are still not active authoritative runtime ownership in this phase
- `webblocks:package-status` now reports reserved-versus-populated package resource readiness in a strictly read-only way

This pilot does not move active root routes, root views, root migrations, root public assets, controllers, requests, models, services, or System Update behavior.

### Package Config Defaults Vs Root Install Overrides

- Package `config/` should continue to define CMS-owned default values.
- Root `config/` remains the install-owned override layer during the transition.
- A package config file should not become authoritative for runtime behavior until the override story and bootstrap wiring are explicit and stable.

### Package Migration Loading And Publishing Strategy

- Package migrations should stay non-authoritative until real package-owned migrations exist and their ownership is intentionally moved.
- When migration ownership begins, prefer package loading for CMS-owned migration files and explicit publish guidance only where install-local customization is truly needed.
- Do not mix migration-boundary work with unrelated runtime refactors.

### Package Route Ownership Strategy

- Package routes should remain placeholders until a focused route-boundary phase decides which CMS routes are package-owned.
- Active root route behavior remains authoritative during the transition.
- Route moves must be grouped by runtime concern and verified against middleware, bindings, and admin or public behavior.

### Package View And Resource Ownership Strategy

- Package `resources/views` should eventually own reusable CMS product views.
- Root `resources/views` remains authoritative until a view is intentionally moved and the package loader becomes the intended source for that view.
- View moves should avoid mixing admin runtime changes with packaging-only work unless explicitly audited together.
- The current pilot safely registers the `webblocks-cms` view namespace as a reserved boundary so future package-owned views can be introduced deliberately without changing current root view resolution first.

### Package Public Asset Publish Or Sync Strategy

- Package `public/` should eventually own CMS-owned publishable assets.
- Transition work should distinguish CMS-owned package assets from install-owned `public/site/...` overrides.
- Asset publishing or sync should happen only when real package assets exist and the update flow clearly defines when publishing is required.
- Current publish intent remains package-tagged and explicit. No package public assets are authoritative in `v1.31.62`, and no publish step runs unless a developer intentionally invokes `vendor:publish`.

### Package Stubs Strategy

- Package `stubs/` should be reserved for reusable generated-file templates that belong to CMS product behavior.
- Install-specific or project-specific scaffolding should not move into CMS package stubs by default.

### Package Publish Tag Intent

- `webblocks-cms-config` is reserved for publishing package-owned CMS default config files into the install root when a developer intentionally needs that workflow.
- `webblocks-cms-assets` is reserved for future package public asset publishing once real package-owned public assets exist.
- `webblocks-cms-stubs` is reserved for future package-owned stubs once reusable generated-file templates are intentionally introduced.
- These tags do not change runtime behavior on their own and remain inert until `vendor:publish` is explicitly run.

### Composer-Managed Update Flow And Post-Update Commands

- The long-term target remains Composer-managed package updates followed by controlled runtime steps.
- Expected post-update steps may later include migrations, block type sync, cache clear, or asset publish or sync, but only when those package-owned resources become real and intentionally wired.
- This documentation checkpoint does not change current System Update behavior.

### Future Starter Project Split Direction

- The long-term direction remains a separate starter project that depends on `fklavyenet/webblocks-cms` as a package.
- The current in-repo package exists to establish boundaries and ownership before that split is attempted.
- The starter split should happen only after package-owned runtime resources and update flow responsibilities are clearer.

### Next Step After Reserved Boundaries

- Move one resource type at a time from reserved boundary to active package ownership.
- Start only when the exact runtime-loading rule, install override story, and publish/update behavior are clear for that resource type.
- Prefer focused phase plans such as package views, package migrations, or package public assets rather than mixing multiple runtime resource types into one checkpoint.
- Keep runtime-heavy source moves behind dedicated dependency audits instead of folding them into resource-boundary pilot work.

### Phase 4: Package-Managed Update Flow

Shift update behavior toward Composer-managed CMS package updates plus controlled post-update steps such as migrations, catalog sync, `block-types:sync-core`, cache clear, and asset publish or sync when needed.

### Phase 5: Starter Project Split

Introduce the separate starter-project direction, such as `fklavyenet/webblocks-cms-starter`, so new installs start from a user-owned Laravel root that depends on the CMS package instead of cloning the CMS core repository into the project root.

### Existing-Install Migration Guidance

Existing installs will need a conservative migration path:

- keep current installs working while the package transition is incomplete
- avoid large one-step moves that mix runtime refactors with packaging changes
- preserve install-specific root files as user-owned project state
- stop relying on root-wide CMS core file replacement as the update mechanism
- introduce clear guidance for removing obsolete root-managed CMS core files once their package-owned replacements exist

The transition should prioritize low-risk incremental movement over a single rewrite.

## Current Status

This repository change is only the first low-risk transition step:

- it adds architecture documentation
- it creates the in-repo package skeleton at `packages/webblocks-cms/`
- it adds a minimal package `composer.json`
- it adds a `WebBlocks\Cms\WebBlocksCmsServiceProvider`
- it wires the root project to path-require the package locally

The provider now defines the package bootstrap contract for future package resources, but those package resources are not yet authoritative because the current CMS runtime files still live in the root application.

The `v1.31.62` pilot makes those package resource boundaries more concrete by adding explicit reserved boundary marker files under package `routes/`, `resources/views/`, `database/migrations/`, `public/`, and `stubs/`. These directories now exist as documented package-owned targets for later phases, but their contents still remain non-authoritative placeholders in this checkpoint.

Package-owned default config has now started for `webblocks-updates`, while the root config file still remains authoritative as the install override during the transition.

Package-owned default config has now also started for `contact`, while the root config file still remains authoritative as the install override during the transition.

Package-owned default config has now also started for `demo_media`, while the root config file still remains authoritative as the install override during the transition.

Package-owned default config has now also started for `cms`, while the root config file still remains authoritative as the install override during the transition.

Package-owned console bootstrap is now also proven through the read-only `webblocks:package-status` diagnostic command.

The package view namespace `webblocks-cms` is now also registered safely as a package-boundary pilot, while active root view resolution remains authoritative because no active CMS runtime views have been moved into the package.

The first package-owned PHP source move is now complete for `SearchTextNormalizer`, moved from `app/Support/Search/` to package `src/Support/Search/` with behavior kept unchanged.

The Search support boundary remains intentionally narrow in this phase: `SearchTextNormalizer` and the small result value object `PublicSearchRebuildResult` are now package-owned, while search indexing and query orchestration remain root-owned until their DB and runtime dependencies are migrated deliberately.

The first non-Search Support audit is also now documented: `MediaKindResolver`, `DatabaseExecutionStrategyResolver`, `SiteHandle`, and `SiteDomainNormalizer` were reviewed, and no additional class was moved because each still crosses at least one current early-phase risk boundary.

The first Contact support source move is now also complete for `ContactMessageNotificationResult`, moved from `app/Support/Contact/` to package `src/Support/Contact/` with behavior kept unchanged while the notifier service and contact runtime flow remain root-owned.

The first BlockTypes support source move is now also complete for `BlockTypeContract`, moved from `app/Support/BlockTypes/` to package `src/Support/BlockTypes/` with behavior kept unchanged while the registry, admin contract modal path, and audit command remain root-owned.

`LayoutMarkup` has also now been audited as a possible narrow Pages helper move and remains root-owned for now because its current references still cross page-layout request validation, admin form rendering, and public slot-wrapper behavior.

The first Formatting support source move is now also complete for `InlineRichTextRenderer`, moved from `app/Support/Formatting/` to package `src/Support/Formatting/` with behavior kept unchanged while `SafeRichTextRenderer` and its sanitization contract remain root-owned.

The initial low-risk helper and value-object source checkpoint is now considered successful and complete for this phase. `fklavye.ddev` also updated successfully after `v1.31.60`, confirming that the current package wiring works in the maintained development environment.

Further opportunistic low-risk PHP source moves are now paused. Future runtime-heavy source moves require a dedicated focused phase plan and dependency audit instead of more small opportunistic migrations.

It does not yet move existing CMS runtime code, change System Update behavior, create a starter project, or change the current active runtime ownership boundaries.
