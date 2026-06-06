# Package Transition Admin Shell And Assets Audit

## Scope

This documentation-only audit reviews the remaining admin shell, shared admin partials, admin assets, and branding ownership boundary after the Site/Locale and operational admin runtime batches, including the later Slot Types and System Settings follow-up.

Inspected areas:

- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/navigation.blade.php`
- `resources/views/components/**`
- `resources/views/admin/partials/**`
- shared root admin partials and components used by package-owned admin screens
- `public/cms/css/admin.css`
- `public/cms/js/admin/**`
- `public/cms/js/admin-sortable-list.js`
- `public/cms/brand/**`
- package public assets under `packages/webblocks-cms/public/cms/**`
- package views under `packages/webblocks-cms/resources/views/**`
- `packages/webblocks-cms/src/WebBlocksCmsServiceProvider.php`
- package publish tags and `webblocks:package-status` reporting related to views and assets

This audit intentionally does not move files.

## Executive Summary

The admin runtime transition is now broad enough that package-owned admin screens depend heavily on root-owned admin asset and brand resources, even though the admin layout itself is now package-owned.

Package transition consolidation is complete for all safely movable CMS-owned source, but admin runtime asset URLs still intentionally remain on the root compatibility path.

- Package-owned admin views now extend package `webblocks-cms::layouts.admin`.
- Package-owned admin views now use package-owned selected shared partials for page headers, flash messages, listing filters, pagination, page actions, audit actor output, and form actions.
- Package-owned admin views now also cover the remaining safe live operational/admin-system follow-up for `Slot Types` and `System Settings`.
- Root files for those selected shared admin partials/components remain thin compatibility wrappers.
- Root `resources/views/layouts/admin.blade.php` has been removed so local maintenance no longer masks package-consumer admin layout namespace mistakes.
- The package-owned admin layout still directly loads active root `public/cms/css/admin.css`, many root `public/cms/js/admin/*` files, `public/cms/js/admin-sortable-list.js`, and root brand images under `public/cms/brand`.
- The package now also carries package-owned source copies of the admin CSS and JS files under `packages/webblocks-cms/public/cms/**`, while root `public/cms/**` remains the active runtime compatibility path.
- `webblocks:package-status` now reports selected shared admin partial/component package authority, the broader admin runtime view inventory, and package public asset readiness that now includes admin CSS and JS source files. Runtime asset authority still has not moved.
- The remaining boundaries after this consolidation are not more safe view moves; they are the root `public/cms` runtime asset path, install/auth/profile runtime, the app-owned `User` model, root migration authority, and future starter split design.

Current outcome: **the admin layout and selected shared admin partials now move through package view authority, and admin CSS or JS source files now also live in the package, while runtime asset authority remains root-owned.**

Starter split outcome: **not ready yet, because admin runtime still depends on root asset URLs and broader root install/auth/update boundaries.**

## Classification Legend

- `compatibility_wrapper`: root file exists only to preserve old imports, view paths, route references, or downstream compatibility.
- `active_root_authority`: runtime still actively depends on the root file as the source of behavior.
- `install_owned`: should remain in the Laravel app root long term.
- `package_candidate`: product-owned and likely movable in a focused package batch once dependencies are accounted for.
- `defer_until_asset_strategy`: product-owned, but should not move until asset publishing, syncing, update semantics, and runtime URLs are defined.
- `defer_until_starter_or_auth_boundary`: tied to starter-project, auth, profile, guest, or app shell ownership and should not move with CMS admin runtime.
- `unclear_needs_review`: cannot be safely classified without deeper dependency tracing.

## Area Summary

| Area | Classification | Current active authority | Package counterpart | Root role | Dependencies and blockers | Recommended next action |
| --- | --- | --- | --- | --- | --- | --- |
| `resources/views/layouts/admin.blade.php` | `removed_wrapper` | Package | `packages/webblocks-cms/resources/views/layouts/admin.blade.php` | No root layout alias remains | The package-owned layout still loads root admin CSS/JS, root brand logo, WebBlocks UI URLs, auth/profile routes, sidebar route list, user menu, and overlay root | Keep package/admin/plugin views on `webblocks-cms::layouts.admin`; do not recreate a root alias |
| `resources/views/layouts/app.blade.php` | `defer_until_starter_or_auth_boundary` | Root | none | Root app layout | Used by app/auth-adjacent surfaces; depends on root app config and WebBlocks UI asset helpers | Keep root-owned until starter/app shell boundary is redesigned |
| `resources/views/layouts/guest.blade.php` | `defer_until_starter_or_auth_boundary` | Root | none | Root guest/auth shell | Loads root guest CSS and contains inline password toggle behavior | Keep root-owned with auth/install guest flows |
| `resources/views/layouts/navigation.blade.php` | `defer_until_starter_or_auth_boundary` | Root | none | Root Breeze-style navigation shell | Uses `Auth::user()`, profile/logout routes, root components, Alpine-style attributes | Keep root-owned with auth/profile/User boundary |
| `resources/views/admin/partials/page-header.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/page-header.blade.php` | Root wrapper preserves old include path | Used across moved package views and remaining root-owned admin views | Keep root wrapper; package-owned views should use `webblocks-cms::admin.partials.page-header` |
| `resources/views/admin/partials/listing-filters.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/listing-filters.blade.php` | Root wrapper preserves old include path | Used by package admin lists and root-owned Users/Backups/Transfers lists | Keep root wrapper; package-owned views should use `webblocks-cms::admin.partials.listing-filters` |
| `resources/views/admin/partials/pagination.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/pagination.blade.php` | Root wrapper preserves old include path | Depends on Laravel paginator only; used across package and root admin lists | Keep root wrapper; preserve compact and query-string behavior |
| `resources/views/admin/partials/flash.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/flash.blade.php` | Root wrapper preserves old include path | Contains messages for update, backup, restore, site delete, locale, user lifecycle but only renders existing session/error state | Keep root wrapper; package-owned views should use `webblocks-cms::admin.partials.flash` |
| `resources/views/admin/partials/audit-actor.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/audit-actor.blade.php` | Root wrapper preserves old include path | Used by package-owned page/shared-slot audit views; displays user identity when relation exists | Keep root wrapper; preserve `Not recorded` fallback |
| `resources/views/admin/partials/page-actions.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/admin/partials/page-actions.blade.php` | Root wrapper preserves old include path | Tightly tied to package-owned Pages public URL/details modal patterns | Keep root wrapper; package-owned views should use `webblocks-cms::admin.partials.page-actions` |
| `resources/views/components/admin/form-actions.blade.php` | `compatibility_wrapper` | Package | `packages/webblocks-cms/resources/views/components/admin/form-actions.blade.php` | Root wrapper preserves old `<x-admin.form-actions>` component | Used heavily by package-owned forms and remaining root-owned admin forms | Package-owned views should use `<x-webblocks-cms::admin.form-actions>` |
| Generic root Blade components | `defer_until_starter_or_auth_boundary` | Root | none | App/auth/profile UI components | `x-application-logo`, auth cards, input components, dropdown/nav links and modal components are still shared with root auth/profile/app shell | Keep root-owned until starter/auth/profile boundary is reviewed |
| `public/cms/css/admin.css` | `compatibility_wrapper`, `defer_until_asset_strategy` | Root runtime path, package source copy | `packages/webblocks-cms/public/cms/css/admin.css` | Root runtime copy remains active for current URLs | Loaded by package-owned admin layout from root `public/cms` path | Keep root runtime file and URL unchanged until publish or sync replacement semantics are defined |
| `public/cms/js/admin/**` | `compatibility_wrapper`, `defer_until_asset_strategy` | Root runtime path, package source copy | `packages/webblocks-cms/public/cms/js/admin/**` | Root runtime copies remain active for current URLs | Loaded by package-owned admin layout and package-owned views for modals, builder, media, page assets, rich text, gallery, and slot block trees | Keep root runtime files and URLs unchanged until admin asset strategy is defined |
| `public/cms/js/admin-sortable-list.js` | `compatibility_wrapper`, `defer_until_asset_strategy` | Root runtime path, package source copy | `packages/webblocks-cms/public/cms/js/admin-sortable-list.js` | Root runtime copy remains active for current URL | Non-nested root path loaded by admin layout | Include in the later publish or sync replacement strategy |
| `public/cms/css/guest.css` and auth password behavior | `defer_until_starter_or_auth_boundary` | Root | none | Guest/auth styling and behavior | Guest layout and auth flow remain root-owned | Keep root-owned with auth/install guest flow |
| `public/cms/brand/**` | `install_owned`, possible package default later | Root | none | Active install/admin brand files | Head meta and admin shell load root brand assets directly; some files may be install-specific such as `fklavye-site.css` | Keep active root authority; later define package defaults plus install override semantics |
| Package public assets | `compatibility_wrapper` support for public slice and admin asset source copies, not active runtime authority | Package source plus root active runtime copies | `packages/webblocks-cms/public/cms/css/public.css`, `css/admin.css`, `js/public/*`, `js/admin/**`, `js/admin-sortable-list.js`, boundary marker | Package has publishable public and admin asset source files, while active runtime URLs still use root compatibility paths | Keep root runtime asset URLs unchanged until publish or sync replacement semantics are explicit |
| Package admin views | Package-owned runtime views | Package | many root wrappers | Active package admin screens now extend the package layout and selected shared partial/component calls use package namespace, while root wrappers remain for compatibility | Continue leaving shell/assets root-owned |
| `WebBlocksCmsServiceProvider` publish tags | `active_root_authority` for current strategy | Package provider | none | Publishes package CMS public assets into the active `public/cms` runtime compatibility path; constants track public assets and admin CSS or JS source files plus moved admin runtime views | Keep root runtime asset URLs stable while publish/install/update refresh package-owned CMS assets into that path |
| `webblocks:package-status` | Current package diagnostics | Package command | none | Reports moved admin runtime views/wrappers, selected shared admin partial/component authority, and public asset readiness, not admin shell/assets | Update diagnostics again only when shell or asset authority actually moves |

## Specific Answers

### Can `resources/views/layouts/admin.blade.php` Move To Package Now?

Yes. That move is now complete.

The layout remains more than a view wrapper. The package-owned admin layout currently still owns:

- sidebar route structure for package-owned and root-owned admin slices
- auth user menu links to profile and logout
- the WebBlocks UI CSS/JS includes
- active root `public/cms/css/admin.css`
- active root admin JS includes for core admin behavior, media, page builder, slot sources, gallery, rich text, page assets, and sortable lists
- root brand logo loading from `public/cms/brand/logo-mark.svg`
- the shared `#wb-overlay-root` used by modal flows across package-owned and root-owned screens

This move is intentionally limited to Blade authority. Root runtime asset URLs, brand files, and auth/profile integration points remain unchanged and still load from the root app.

### Which Admin Partials Are Product-Owned Package Candidates?

The strongest package candidates that already moved in the selected shared partial batch are:

- `resources/views/admin/partials/page-header.blade.php`
- `resources/views/admin/partials/listing-filters.blade.php`
- `resources/views/admin/partials/pagination.blade.php`
- `resources/views/admin/partials/audit-actor.blade.php`
- `resources/views/components/admin/form-actions.blade.php`
- `resources/views/admin/partials/flash.blade.php`, which remains safe as a package-owned shared partial because it only renders existing session and validation state even when some keys come from still-root-owned operational flows
- `resources/views/admin/partials/page-actions.blade.php`, which remains safe as a package-owned page-builder partial because it is tightly scoped to package-owned Pages UI behavior rather than shell chrome

### Which Admin Partials Are Still App, Install, Auth, Or Profile Owned?

The following should stay root-owned for now:

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/navigation.blade.php`
- auth views under root `resources/views/auth/**`
- profile views under root `resources/views/profile/**`
- root generic components that are primarily Breeze/auth/app shell components, including application logo, auth cards, nav links, responsive nav links, dropdowns, input components, and root modal components

Some generic components may later become package UI primitives, but they should not be moved as part of the admin shell transition without a starter/auth boundary decision.

### Which Admin CSS/JS Files Are Product-Owned But Blocked By Asset Strategy?

Product-owned but blocked by explicit asset strategy:

- `public/cms/css/admin.css`
- `public/cms/js/admin/core.js`
- `public/cms/js/admin/asset-picker.js`
- `public/cms/js/admin/builder-items.js`
- `public/cms/js/admin/gallery-items.js`
- `public/cms/js/admin/inline-block-builder.js`
- `public/cms/js/admin/media-copy.js`
- `public/cms/js/admin/page-assets.js`
- `public/cms/js/admin/page-builder-modals.js`
- `public/cms/js/admin/page-slot-source-modals.js`
- `public/cms/js/admin/rich-text-editor.js`
- `public/cms/js/admin/slot-block-delete-modal.js`
- `public/cms/js/admin/slot-block-tree.js`
- `public/cms/js/admin-sortable-list.js`

These files now also exist as package-owned source files under `packages/webblocks-cms/public/cms/...`, but active runtime URLs are still root `public/cms/...` paths. The package admin layout loads only pinned WebBlocks UI JavaScript and shared `core.js` globally; all feature-specific admin JavaScript is pushed through the `admin-scripts` stack by the views or partials that render the corresponding UI. Replacing runtime authority still requires a clear rule for package source files, root compatibility files, publish tags, update syncing, cache busting, and downstream override behavior.

### Which Brand Assets Should Remain Install-Owned Or Become Package Defaults?

Active root brand assets should remain root authority for now:

- `public/cms/brand/apple-touch-icon.png`
- `public/cms/brand/favicon.svg`
- `public/cms/brand/favicon-16x16.png`
- `public/cms/brand/favicon-32x32.png`
- `public/cms/brand/logo-mark-dark.svg`
- `public/cms/brand/logo-mark-on-accent.svg`
- `public/cms/brand/logo-mark.svg`

The package now ships the same compact CMS product brand set as root `public/cms/brand`. Older generated `logo-32.png`, `logo-64.png`, `logo-180.png`, `logo-monochrome.svg`, and unused `og-image.png` files are not part of the canonical package-safe set.

### Would Moving The Admin Shell Require Moving Auth/Profile/Install Layouts?

No. The admin shell can eventually move without moving auth, profile, install, guest, or app shell layouts.

However, the current admin shell links to profile/logout routes and uses the current authenticated user. A package admin shell would need to treat those as root-owned integration points rather than pulling auth/profile/User ownership into the package.

### What Route/View/Controller Slices Use The Package Admin Shell?

Every admin screen that extends `webblocks-cms::layouts.admin` uses the package admin shell. The historical root `layouts.admin` alias is intentionally unavailable.

Package-owned slices affected:

- Dashboard
- Pages
- Blocks
- Media
- Shared Slots
- Navigation
- Block Types
- Page Layouts
- Icons
- Sites
- Site Domains
- Site Variables
- Locales
- Contact Messages admin
- Visitor Reports
- System Search
- package runtime-status admin screen

Root-owned slices also affected through the shared layout:

- Users
- Profile
- remaining update and backup system screens
- System Update
- System Backups
- Backup/Restore details and upload screens
- Site Export/Import
- Site Promotion
- any remaining root admin screens such as legacy layout/page/slot type views

The shell move would therefore need smoke coverage across representative package-owned and root-owned admin screens, even if their controllers do not move.

### What Tests Guard The Admin Shell Boundary?

Current focused tests should continue to cover:

- admin dashboard route rendering through the expected package/root shell boundary
- representative package-owned admin screens rendering with the shell, including Pages, Media, Sites, Locales, Dashboard, Contact Messages, Visitor Reports, and System Search
- representative root-owned admin screens rendering with the shell, including Users, Profile, System Settings, Backups, and Export/Import
- route names and middleware on package-owned admin routes still resolving unchanged
- sidebar navigation visibility and `access-system` gating
- profile/logout links remaining root-owned and valid
- absence of the root `layouts.admin` compatibility wrapper
- package namespaced admin layout view existence
- moved shared partial wrappers resolving both root include names and package namespaced names
- admin asset URLs and cache-busting behavior
- brand image and head-meta asset references
- modal overlay root presence and one representative modal flow
- `webblocks:package-status` reporting the new admin shell/partial boundary if diagnostics are updated

## Completed Selected Shared Partial Batch

Completed in the selected shared partial batch:

- `webblocks-cms::admin.partials.page-header`
- `webblocks-cms::admin.partials.flash`
- `webblocks-cms::admin.partials.listing-filters`
- `webblocks-cms::admin.partials.page-actions`
- `webblocks-cms::admin.partials.pagination`
- `webblocks-cms::admin.partials.audit-actor`
- `webblocks-cms::components.admin.form-actions`
- root wrappers for the old `admin.partials.*` include paths
- root wrapper for the old `<x-admin.form-actions>` component
- package-owned views updated to prefer `webblocks-cms::admin.partials.*` and `<x-webblocks-cms::admin.form-actions>`
- package-status reporting for the selected shared admin partial/component boundary

## Recommended Next Implementation Batch

Recommended next batch: either focused remaining model/support compatibility cleanup for already package-owned runtime slices, or an admin asset and brand strategy pass now that the admin layout is package-owned.

Do not move admin `public/cms` runtime asset authority in the next batch. The next asset batch should define:

- package source location for admin CSS/JS
- active runtime URL policy
- root compatibility copy policy
- publish/sync/update behavior
- downstream override behavior
- brand default versus install override behavior
- package-status reporting for the new asset boundary

Explicitly excluded from the next immediate batch:

- migrations
- System Update
- System Backup
- backup/restore
- site export/import
- site promotion
- installer flow
- auth/profile/User
- root config ownership changes
- Composer update flow changes
- root `public/cms` runtime asset authority
- release/version/tag work
