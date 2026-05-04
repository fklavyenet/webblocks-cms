# Changelog

## [Unreleased]

- Improve the Edit Page admin screen by moving Slots into a dedicated card separate from page settings.
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
- Add a project-layer `webblocks:sync-ui-docs-getting-started` command that idempotently syncs the existing Getting Started page main slot from the WebBlocks UI docs source.
- Clean up repository structure.
- Move internal audit documents outside repository.
- Refactor README and documentation structure.
- Improve docs entry point (`docs/index.md`).

## 1.7.0

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

- Extracted the large inline admin JavaScript block from the admin layout into named admin assets under `public/assets/webblocks-cms/js/admin/`.
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

- Removed the hard-coded `webblocks:sync-ui-docs-pilot` website content generator from CMS core.
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
