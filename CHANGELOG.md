# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## 1.48.7

- **`php artisan webblocks:starter-content` fills an empty home page with the starter content, replacing a `db:seed --class=...` instruction that could not be typed into a hosting panel.** 1.48.6 added the starter page but only runs it during install, by design — System Update must never write content into a live site — so an install made before it keeps its empty home page and needs one manual run. The documented seeder invocation failed twice on a real panel's Artisan screen: first on the production confirmation, which a non-interactive runner cannot answer (`Command cancelled.`), then, with `--force`, on the class name itself, because the panel stripped the backslashes and Laravel went looking for `Database\Seeders\WebBlocksCmsDatabaseSeedersStarterContentSeeder`.
- The command takes no class name and nothing else that can be mangled by a runner's quoting. `--site=handle` picks the site on a multisite install; without it the primary site is used. It reports how many blocks it wrote, or why it wrote none.
- It deliberately skips Laravel's production confirmation, because the prompt cannot be answered by the runners it exists for and the guarantee it would protect is already structural: starter content is only ever written into a page that has no blocks at all. On a site whose home page already has content the command is a no-op that says so, it never touches any other page, and running it twice is safe.
- The seeder is unchanged and still what `db:seed` runs on a fresh install. `docs/installation.md` keeps it as the alternative, now with the `--force` a production install needs.

## 1.48.6

- **A fresh install now lands on a real home page instead of an empty one.** The installer always created the page and its layout slots but never any content, so `/` rendered an empty shell and the first page a new admin opened in the editor was blank; `php artisan db:seed` was worse, creating no page at all and letting `/` fall through to the host application's Laravel welcome view. The shipped starter page is a hero, a three-item feature grid, and a closing call to action — ordinary blocks with ordinary translations, written through the same `BlockPayloadWriter` the block editor and the Internal Content API use, so what lands in the database is what an editor would have built by hand and can be rewritten, reordered, or deleted like any other content.
- Starter content is written **only into a page that has no blocks at all**. It cannot overwrite existing content, it is a no-op on every run after the first, and System Update never adds or restores it. An install created before this release keeps its empty home page; to fill it once, run `php artisan db:seed --class="WebBlocks\Cms\Database\Seeders\StarterContentSeeder"` on an install whose home page is still empty.
- Nothing about the starter page can fail an install. A missing, unreadable, or malformed blueprint is reported and skipped, and a block type the catalog does not have skips that block and its children instead of aborting.
- The page content lives in `database/content/starter/home.json` under the `webblocks.cms.starter-content.v1` schema, using the same nested-children block vocabulary `docs/ai-page-building-guide.md` teaches. A `home.{locale}.json` beside it wins for an install whose default locale matches, and `WEBBLOCKS_CMS_STARTER_CONTENT_PATH` points the lookup at a product's own blueprints. `WEBBLOCKS_CMS_STARTER_CONTENT=false`, or `php artisan webblocks:install --skip-starter-content` for one run, installs the empty published home page instead.
- The home page's provisioning moved out of the install command into `DefaultHomepageProvisioner`, so `db:seed` and `webblocks:install` produce the same page. It resolves that page by its default-locale path of `/` rather than by "first published default page of this site" as the install command did — identical on a fresh install, but this code is now reachable from a seeder an operator can run on a live site, where the loose match would have adopted an unrelated published page and rewritten its slug and path to the home page's.

## 1.48.5

- **The Slide block's Overlay setting is now applied; it was stored but never rendered.** Slide shares the admin Background Media panel with section, hero, cta, card and content_header, and its `background_overlay` was saved like theirs — but the public slide template only read the image URL and Background Position. Position worked and Overlay did nothing, on the one block type whose whole job is text over a photograph. Slides cannot use the `wb-background-media` primitive the others use: their image is a real `<img class="wb-slide-media">`, and the darkening comes from `.wb-slide::after` painting `var(--wb-slider-overlay)`. Since the `wb-slider-overlay-*` classes only define that custom property, one on the slide now overrides the slider's overlay for that slide alone — no new CSS, and nothing needed from webblocks-ui.
- The field offers four levels where the slider pattern defines three, so `medium` resolves to `wb-slider-overlay-strong`. Moving off soft is a request for more cover; rounding down would leave the change invisible, which is the defect being fixed.
- **Slide's Overlay gains an "Inherit from slider" option, and it is the default.** An absent setting emits no class, which is what leaves the slider in charge — so a slide saved without touching Overlay must not write one. The shared partial defaulted the field to Soft, which would have quietly overridden a slider set to Strong on the next save of any slide. Inherit makes that state explicit, and slides now store an explicit `soft` (dropped elsewhere as the render default, but a real choice here, where the absent key means "inherit"). Section, hero, cta, card and content_header keep the previous four-option, soft-default behaviour exactly.

## 1.48.4

- **Saving one Shared Slot block no longer reindexes every page using that slot, once per written row.** 1.43.2 identified this cost and fixed it for the site import, which is a bulk writer that knows what to rebuild afterwards. Its commit already recorded the case left open: "a header block save reindexes all 22 published pages that use the slot." An editor save turns out to be a bulk writer too — it is never one row, but a block row plus up to four translation families plus whatever child blocks a builder field syncs, and each of those save hooks asks for the same full sweep. On a site header, used by every published page, a single "Save Block" therefore rebuilt the whole set several times over and took seconds on a site with almost no content in it.
- `PublicSearchIndexer::coalescing()` sits beside `deferring()` and needs nothing from the caller afterwards. The save hooks still name their targets — they are the ones that know which pages a write affects — but while a scope is open those targets queue instead of running, and the outermost exit rebuilds each page exactly once. A page queued for one locale and then for another is promoted to a full rebuild; a Shared Slot expands to its pages at flush time, after the slot assignments have finished being rewritten. `deferring()` still outranks it, so an import's rows do not queue up behind the rebuild it already does itself.
- `CoalesceSearchIndexing` opens one scope per non-cacheable request on the admin and Internal Content API route groups, and flushes in `terminate()` — after the response has been sent. Nothing in the controllers changed, so every write path gets this, including the ones that were worst off: a Shared Slot revision restore deletes and recreates the entire block tree.
- A write that throws discards its queue rather than flushing it: the rows never landed, so there is nothing to reindex. Verified against a three-page site sharing one slot — the same block save costs 6 index writes live and 3 coalesced, producing a byte-identical index.

## 1.48.3

- **Fixes a Chrome accessibility audit warning ("Incorrect use of `<label for=FORM_ELEMENT>`") on every asset picker paired with an external `<label>`.** The label's `for` pointed at the picker's hidden input, which Chrome correctly refuses to accept as a label target since it's never visible or focusable. The picker's trigger button ("Choose/Replace ...") now carries `id="{inputId}_open"` in all three layout branches, and the three affected callers (Sites form: favicon, social image; page translations form: og image) point at that instead.

## 1.48.2

- **Switching tabs on the Edit Site and Edit Page forms no longer discards unsaved edits.** Tab buttons were `<a href="?tab=...">` links: clicking one fired a full page navigation, so any edit made in the currently-loaded page — on the tab being left or any other tab — was gone once the new page loaded, even though the single "Save Changes" button implied one shared save across every tab. Tabs now switch client-side via the shipped `wb-tabs` widget (`data-wb-tab`/`data-wb-tabs`), so nothing reloads and nothing is lost.
- **The Sites form's disabled Delete button now explains why.** `SiteDeleteResult` already computes the blocking reason (primary site, last remaining site, linked contact messages); the button now carries it as a `title` instead of just sitting disabled with no explanation.
- **Bumps the pinned UI runtime to `v2.17.0`.** wb-tabs gains an opt-in `data-wb-tabs-field="<selector>"` attribute: the widget itself now writes the active tab's id into a declared form field on every change, closing the gap that pushed the Sites and Pages edit forms to each hand-roll their own `wb:tabs:change` listener just to keep a hidden "last active tab" input in sync (`page-assets.js`, and a short-lived `site-settings-tabs.js`) — both are deleted, replaced by the declarative attribute. `Site::normalizeAdminFormTab()` centralizes unwrapping the synced panel id back into `Site::ADMIN_FORM_TABS`'s bare keys, read by both `SiteController` and the form Blade.

## 1.48.1

- **Renaming a site's handle no longer strands its `site.css`/`site.js` override files under the old handle's directory.** `SiteAssetResolver` always resolves these by the site's *current* `handle` column and returns `null` on a miss, so a handle change left the public layout silently omitting the `<link>`/`<script>` tags with nothing anywhere telling the operator why — the files were still on disk, just under `public/site/{old-handle}/` instead of `public/site/{new-handle}/`. `SiteController::update()` now relocates the directory to the new handle (merging into any already-created `css`/`js` scaffold) before `ensureAssetDirectories()` runs.
- **The Sites list's "See details" modal rendered raw translation keys (`admin.site_details`, `admin.handle_label`, etc.) instead of text.** The modal partial inherited the Sites index's `$adminText`, which resolves keys under the bare `admin.*` namespace, but the modal's strings live under `admin.site_form.*` — every label fell through the translator's missing-key fallback. The partial now scopes its own `$adminText` to `site_form.`, matching every other Sites admin view that renders these strings.

## 1.48.0

- **The header slot's `wb-public-site-header` class is retired in favor of the fixed `wb-slot-header` class introduced in 1.47.0.** `public/cms/css/public.css`'s two rules — the header's bottom margin, and the extra top padding `main` gets when it immediately follows the header — now target `.wb-slot-header` and `.wb-slot-header + .wb-slot-main` instead of `.wb-public-site-header` and the mixed class/attribute selector that mismatch required. Since `wb-slot-header`/`wb-slot-main` render on every layout (Default, Article, and Docs alike), this spacing now also applies to the Docs shell's navbar header, which `wb-public-site-header` never reached before — a deliberate, previously-inconsistent gap being closed, not an incidental side effect. `header`'s `css_classes` catalog default is removed from Default and Article layouts (matching `footer`/`sidebar`, which never had one) since the class it used to carry is now the code-owned fixed class; an operator's own custom `css_classes` value for header is completely unaffected; it's still read and appended after `wb-slot-header` exactly as before. Also removes `.wb-public-footer .wb-footer-cookie-settings-link`, a rule left orphaned by 1.46.13's dead-code removal with no matching markup left anywhere.

## 1.47.0

- **`wbcms_page_layout_slots.html_classes` is renamed to `css_classes`, matching the admin-facing "CSS Classes" field it has always been.** Purely a naming fix — the admin form, its validation, and every code path that reads or writes this field behave exactly as before, just under a name that says what it's for instead of what HTML attribute it becomes. Existing installs pick this up via a package update migration (`database/migrations/updates`); already-installed sites are unaffected until their next System Update, at which point the rename runs once, guarded and reversible.
- **Every public slot (`header`, `main`, `sidebar`, `footer`, and any future custom slot type) now always renders a fixed `wb-slot-{name}` class, ahead of whatever `css_classes` holds.** This class lives in `SlotWrapperResolver`, not the database, so `catalog-repair --all` — which force-syncs `css_classes` to the package's catalog default on every run, silently discarding whatever an operator had set — can never remove it. An operator's own `css_classes` value is never replaced by this, only ever appended after the fixed class. Rationale: a site whose custom CSS depended on a slot's `css_classes` value (set once by hand, then silently reset by a later System Update) had no way to give itself a stable hook that survives every future sync; `wb-slot-{name}` is that hook, going forward, for every install.

## 1.46.13

- **Removes ~270 lines of unreachable "chrome" fallback markup from the header, footer, main, and sidebar slot partials.** Each of the four carried a legacy branch (`$slot['chrome']` populated, or `$renderShell` true) from an auto-generated site-chrome system that predates the current admin-managed Page Layout Slots (`html_classes`, block-driven rendering) introduced 2026-05-13. Nothing has populated `chrome` or passed `renderShell: true` since, so the branches — including a whole "site introduction banner" section, dropdown primary/mobile navigation, and a rich branded header — never rendered on any page; confirmed against every test, doc, and the plugin system's own extension contracts, none of which reference either mechanism. Each partial is now just its live, block-driven rendering path. `tests/fixtures/known-unstyled-classes.txt` drops the 22 class names that existed only in that dead code.

## 1.46.12

- **Restores a bare `wb-public-main` on the `main` slot's `html_classes`, without any width class.** 1.46.11 reverted 1.46.9's mistaken `wb-container wb-container-lg` by clearing `main`'s `html_classes` entirely on `Default Layout` and `Article Layout`, but `docs/block-ui-renderer-contract.md`'s compliance matrix and `docs/inventory.md` both document `wb-public-main` as an "acceptable" primitive for the main slot and the public layout shell, independent of the `Container` block's own `wb-container` width tokens — the same documented contract that already covers `wb-public-body` and the `wb-public-block` wrapper. `main`'s `html_classes` is `wb-public-main` again on both layouts; `SlotWrapperResolver`'s legacy fallback mapping matches. Width remains exclusively the `Container` block's job, unchanged from 1.46.11.

## 1.46.11

- **Reverts the `main` slot `html_classes` change shipped in 1.46.9.** That release added `wb-public-main wb-container wb-container-lg` to `main` on `Default Layout` and `Article Layout`, on the assumption that `main` was missing an intended width constraint. Further review found that was the wrong fix: `wb-container-lg` is the `Container` block's own width primitive (`Block::containerWidthClass()`), which the CMS's own documentation (`docs/getting-started.md`) says is deliberately opt-in per block — forcing it onto `main` itself would silently cap any `Container` block placed inside at `lg` width, defeating its `Width: Full` option. `html_classes` is also a fully admin-editable per-slot field (Admin -> Page Layouts), whose own in-product help text lists `wb-sidebar`, `wb-dashboard-main`, and `wb-stack` as the intended kind of value — structural/shell markers, not content-width tokens — and `CatalogRepairer` force-syncs the catalog default onto every installed site's row on each update, so shipping the wrong value here would have silently overwritten any operator's own customization, not just seeded fresh installs. `main`'s `html_classes` is back to unset on both layouts, matching its state before 1.46.9; the `SlotWrapperResolver` legacy fallback mapping is reverted to match. Sites that already picked up the 1.46.9 value via `catalog-repair` will clear it the same way, next time that command runs.

## 1.46.10

- **A System Update that failed after replacing package files (for example a broken `composer` step, or any later post-install failure) had no way to undo the file swap.** The existing automatic recovery only restores the database and uploads from the pre-update backup, never the package code itself, so a failed run could leave `vendor/fklavyenet/webblocks-cms` on the new version while migrations, cache clears, and catalog repair never ran — a silent code/schema mismatch. Rather than growing that backup to snapshot all of `vendor/` (mostly unrelated, unchanged dependencies, fully reproducible from `composer.lock`), `UpdateInstaller` now keeps the pre-update package directory it already sets aside during the swap (previously deleted immediately after a successful `rename()`) until the whole update flow verifies successfully. Any failure between the file swap and that verification now rolls the package back to its exact pre-update contents; a successful run clears the kept-around backup once it's no longer needed.

## 1.46.9

- **The public `main` slot never received its width-constraining container class on `Default Layout` and `Article Layout`, so page content rendered edge-to-edge instead of matching the header/footer width.** `PageLayoutCatalog`'s `main` managed-slot definition had no `html_classes` at all for either layout (every other slot — header, sidebar, docs' own `main` — has one), and the legacy fallback mapping in `SlotWrapperResolver` had the identical gap for pages without a managed-slot row. Both now carry `wb-public-main wb-container wb-container-lg`, matching the class pairing the (dead) hardcoded shell markup in `pages/partials/slots/main.blade.php` always intended. Existing sites pick this up the next time `php artisan webblocks:catalog-repair --all` runs (automatically on a successful in-app update, or manually once beforehand).
- **A System Update could fail invisibly at the dependency-install step and leave the site in a half-updated state: package files already replaced, but migrations, cache clears, and catalog repair never run.** `UpdateInstaller::installDependencies()` invoked a bare `composer` command, unlike every `php artisan` call in the same flow, which already resolves an absolute PHP binary to survive php-fpm's stripped subprocess `PATH`. Under php-fpm, that bare `composer` shim's own `#!/usr/bin/env php` shebang can't resolve `php` and fails with `env: php: No such file or directory` — after `applyPackage()` has already swapped in the new version's files. `UpdateCommandRunner` now runs Composer as `php <resolved-composer-entry-point>` instead of executing its shim directly, sidestepping the shebang lookup entirely; the entry point is found via an optional `WEBBLOCKS_UPDATES_COMPOSER_BINARY` config override, `PATH` lookup, or a few common install locations, with a clear error if none resolve.

## 1.46.8

- **Most admin screens resolved their UI copy through Laravel's global `__()` helper, which always renders in the single install-wide `app.locale` — an operator with their own `admin_locale` preference set (or the system admin locale) still saw every admin screen in the install's default language.** Only a handful of files (the block-edit modal, the admin shell/sidebar) had been migrated to the per-user `AdminLocaleResolver` + `CmsTranslator::admin()` path. Migrated the remaining ~36 admin Blade files — block types, pages, navigation, domains, and plugins screens and their partials — to resolve the authenticated admin's own locale instead, including a few "half-migrated" files that mixed both paths in the same template. Verified with `webblocks:admin-translation-audit --strict` against the existing baseline and the full test suite; no admin translation domain files still call `__('webblocks-cms::admin.*')` directly.

## 1.46.7

- **The shared `admin.partials.listing-filters` component (used by Pages, Media, Comments, and Navigation) had no date field type**, only a search box and dropdowns — a plugin wanting a date filter had nothing to reuse and no path but a one-off filter UI of its own. Adds `dates`, the same shape as `selects` (id/name/label/value, optional `submitOnChange`), fully optional so every existing caller renders unchanged.
- **Clarified that `PluginMenuItem::group()` is an exact-match label, not a picklist.** Two unrelated plugins independently reaching for the same generic-sounding but undocumented group name (`Content`) silently shared one sidebar heading — working as designed (identical strings merge), but nothing said so. Added a docblock explaining the shared-bucket-vs-dedicated-section behavior, and a `docs/plugin-system.md` example showing a large plugin surface claiming its own section by passing its own name.

## 1.46.6

- **The Video block's external-link fallback was unreachable: any URL that wasn't a recognized YouTube/Vimeo embed rendered a broken native `<video>` tag instead of the documented "Open video" link.** `$videoSource` was computed as `$assetUrl ?: ($embedUrl ? null : $safeUrl)`, so a plain webpage or any other unsupported host fell all the way through to `$safeUrl` and produced a `<video><source>` pointing at something that isn't a video file and never plays. `$videoSource` is now only ever `$assetUrl`: an uploaded Media Library file is the sole source for the native `<video>` tag, recognized YouTube/Vimeo URLs still render their iframe embed, and everything else now reaches the existing link fallback, matching the renderer's own documented contract.

## 1.46.5

- **The Internal Content API could read a site's resolved timezone in rendered content but never write it.** `PATCH /webadmin/api/sites/{site}/timezone` closes that, under `site-settings.write`: it accepts a standard IANA identifier such as `Europe/Berlin`, validated against the same set `Sites -> Edit Site` offers, and an empty value clears it back to the install-wide system timezone — the same convention the admin edit form uses, so the two surfaces cannot disagree about what blank means. A site's timezone is what anything resolving local wall-clock time for that site — a plugin's booking availability windows, for one — is interpreted against.

## 1.46.4

- **A new `Article Layout` gives a TOC block a sticky rail beside the article instead of stacking it inline.** The reference "On this page" panel sits in a two-column CSS grid next to the article body, not above it. TOC's own slot-scoped scan (1.46.2) means it has to keep living inside `main` to see `main`'s own headings, so the split happens at render time around the unmoved block: when `main` has a top-level `toc` block, `Article Layout` pulls that one block into `wb-settings-nav.wb-docs-rail`, wraps the rest of `main` in `wb-settings-body`, and wraps both in `wb-settings-shell wb-docs-layout` — every class already shipped in the pinned `webblocks-ui.css`, no new CSS. A page on this layout with no `toc` block, or a `toc` nested under something other than a direct child of `main`, renders identically to `Default Layout`; the split is entirely opt-in and non-breaking.
- **The Internal Content API could set a page's Page Layout only at creation, never on an existing page.** `PATCH /webadmin/api/pages/{page}/layout` closes that: it writes `public_shell` under `content.apply`, validates against the same active-layout allowlist the admin edit form uses, normalizes the legacy `dashboard` alias to `docs`, and — matching that same admin contract — does not mutate Page Slots on its own; call `sync-layout-slots` separately if the new layout defines slots the page does not have yet.

## 1.46.3

- **TOC rendered as `wb-link-list`, a plain link row with a hardcoded English "Jump to section" / "Jump to subsection" line that never went through any translator, whatever the site's locale.** It now renders `wb-section-nav`: a self-contained WebBlocks UI primitive with its own border, background, and padding — confirmed directly against the pinned CDN stylesheet, no dependency on the Settings Shell docs pattern it is normally seen inside.
- The `wb-docs-rail` / `wb-settings-nav` modifier classes are deliberately not added. Both belong to that two-column docs shell: they pin the element into a CSS grid position and cap it to viewport height with its own internal scrollbar, which would clip a long TOC sitting inline in a normal content flow instead of beside it.
- `wb-section-nav` is also what the shipped `WBSectionNav` module in `webblocks-ui.js` already keys off — the exact bundle the public layout already loads. It self-initializes on any `.wb-section-nav` it finds and live-updates `.is-active` / `aria-current="location"` on scroll, purely by matching a link's `href="#id"` against `document.getElementById(id)`. Using the right class name is the entire change: no JavaScript is owned by this package, and the hardcoded English chrome is gone with it — the primitive has no description line to begin with.

## 1.46.2

- **TOC scanned the whole page instead of the slot it was placed in.** A TOC in `sidebar` happily listed headings that actually live in `main` — the block described the page, not the slot it was in, which is why a TOC placed in `sidebar` on a `default`-layout page rendered at the bottom: sidebar comes after main in that shell, and TOC never noticed its headings weren't part of that content. `publicTocHeadingBlocks()` now scopes to the TOC's own `slot_type_id`.
- **TOC links could come back in the wrong order once an article had more than one section.** Heading order was a flat sort by `(sort_order, id)`, but `sort_order` is scoped per `(page, slot, parent)` everywhere blocks get created — two headings under different `section` containers both start counting from 0. TOC now walks its slot's block tree in real document order: each parent's own children first, each level sorted by `(sort_order, id)`.
- `toc` is a system block type now, joining `comments`/`rating`/`breadcrumb`/`navigation-auto`/`header-actions` — blocks whose content is derived from context rather than freely authored. It stays exactly as placeable and deletable on a page as before; `is_system` only makes the block *type* read-only in `Admin -> Block Types`.
- A TOC placed inside a Shared Slot now renders empty rather than doing anything unexpected: a Shared Slot's block tree lives on a separate hidden source page, never on the consuming page's own blocks, so there is nothing in scope to scan. Not a supported combination either way.
- Eight documentation lines describing the old "same page" scan are corrected to "same slot," including `docs/inventory.md`, the API-served AI authoring contract.

## 1.46.1

- **The Internal Content API could bind a page slot to a Shared Slot and never release it.** `source_type` was writable only on the session-authenticated admin route, so a token client could create a reference that nothing in its own API could remove: the slot stayed bound and the Shared Slot stayed undeletable until someone opened every consuming page by hand. `PUT /webadmin/api/pages/{page}/slots/{slot}/source` writes all three source types — `page`, `shared_slot`, `disabled` — so the field has one endpoint rather than a write path per value.
- `content.apply` covers it; `source_type=shared_slot` additionally requires `shared-slots.write` and delegates to the existing assign endpoint, so the compatibility rules, the human-only block guard, and the capability gate stay in one place instead of being restated.
- Detaching clears `shared_slot_id` and leaves page-owned blocks untouched: `page` renders them again and `disabled` keeps the slot wrapper with nothing inside. Discovery and the OpenAPI schema advertise the endpoint, and the assign endpoint gained the `x-required-capability` it had been missing.

## 1.46.0

- **CMS core no longer knows the name of any plugin.** Two first-party plugins were wired into core by handle: `PluginRouteRegistrar` registered nine WebBlocks UI Manager admin routes itself instead of loading the plugin's route file, `PluginRouteFallbackController` carried a method per plugin naming its controller classes and restating each route's permission check, and `routes/public.php` hardcoded the whole WebBlocks Commerce storefront. Every one of them named a class in the plugin's package, so a plugin that renamed its own namespace — as both have now done — turned its own pages into 404s with nothing in core to say why.
- The plugin route fallback is generic. It still exists for the two cases that need it, a cached route table and a provider left over from the version an update replaced, but it now rehydrates the plugin's own routes and runs whichever one matches, under that route's own middleware. An authorization rule is enforced where it is declared instead of being copied into core, and the fallback serves every plugin rather than the two it had been taught.
- **`routes.webhooks`: a plugin can own a third-party callback.** A payment gateway calling back after a customer pays carries no session, so it cannot carry a CSRF token, and it is not a bearer-token client either — it fits neither `routes.public` nor `routes.api`. Previously the only way to a working callback was for core to hardcode the endpoint and add its path to a global CSRF exemption list. A plugin now declares the file, and the registrar drops the check from that group alone: same prefix, same throttle, same `install.required`, CSRF and nothing else relaxed.
- The exemption is attributable. It is applied by removing the middleware from one route group rather than by adding paths to a list, so it covers the routes the plugin declared and cannot widen to a path that merely resembles them.
- Verifying the caller stays with the plugin. A webhook is a notification, not proof of payment, and core is not in a position to check a signature it has no key for.
- Removed the `commerce` reserved prefix from the redirect-manager catch-all protection and from the reserved page-slug segments. Reserving a first segment for a plugin that no longer has one there only stopped someone publishing a page at `/commerce`.
- **`plugins` is a reserved page-slug segment now, and was not before.** Every plugin public route mounts under `/plugins/{handle}`, and public pages are served by a dynamic `{slug}` route — so a page published at `/plugins/anything` and a plugin's own endpoint were two routes competing for one path, with the winner decided by registration order. The segment that was reserved was the one belonging to a single plugin's storefront; the one shared by every plugin was not.
- **A Shared Slot tells you which pages it serves.** The delete confirmation reports that fifteen page slots still reference the slot and stops there, which makes the block visible but not actionable — you know the delete is refused and have no way to reach the fifteen pages standing in the way. The list grows a Usage column with a page count, and the Actions column an icon opening a modal that lists the consuming pages: title linked to Edit Page, path, which slot it fills, and page status. The action is inert at zero rather than opening an empty modal, and the blocked delete warning now points at it.
- The slot's own hidden source page is filtered out of that list — it has no slot source an operator can change — and the page slots are eager loaded onto the paginated collection, since every row renders its own modal and this would otherwise be a query per row.

## 1.45.7

- **Deleting a Shared Slot asked through the browser's own dialog.** "Delete this Shared Slot?" — no name, no handle, and no hint that the server refuses the delete while a page slot still references it, which you found out by pressing OK and landing on a validation error. Ten destructive actions now open the CMS confirmation modal and name the record they are about to act on: Shared Slot delete and revision restore, block delete from the list and from the page outline, locale delete, navigation item delete, page revision restore, backup restore, and restore-history delete.
- The Shared Slot delete modal reports how many page slots still reference the slot and disables its own submit when there are any, so the block the controller already enforces is visible before the click rather than after it.
- The backup restore acknowledgement moved into the modal. It is the checkbox the server actually validates, so it belongs in the form that posts rather than sitting on the page behind a `confirm()` that duplicated the same question.
- `form-actions` dropped its `deleteConfirm` prop. Nothing in the package passed it, and leaving it in place would keep a supported route back to `window.confirm`.
- `DestructiveConfirmationModalTest` sweeps every Blade view for a `confirm(` call — that is what caught the two Shared Slot revision screens after the delete itself was already done — and asserts each converted screen registers the modal id its trigger targets, since a trigger whose modal is never pushed is a dead button.

## 1.45.6

- **The capability badges on CMS API Tokens were server-rendered and then never updated.** Ticking every box in a group left its badge reading `0/5`, and the `8/28 selected` total beside the Capabilities heading stayed at whatever the page loaded with — so the only way to know what a token was about to get was to open all six accordions and count. `api-token-capabilities.js` recomputes each group badge and the total on every change, in the Create Token card and in each Edit API Token modal, through one delegated listener so modals in the overlay root are covered too.
- Create Token now starts with every grantable capability ticked instead of just the eight in Page building. Building a token meant opening each accordion and ticking its boxes one at a time; unticking what a token must not have is the shorter path. Publishing / destructive actions and System safety start ticked as well — they carry their "grant only when explicitly needed" copy, and that is now a prompt to untick rather than to tick.

## 1.45.5

- **A plugin could declare a block type and still have no way to place it.** Block pickers read the `wbcms_block_types` catalog, and `PluginBlockCatalog` only ever filtered that list — it hid a plugin's blocks while the plugin was disabled, but nothing anywhere wrote the row in the first place. A plugin could ship a block, both its views, and its render path, and the block simply never appeared in any picker. `PluginBlockTypeCatalogSyncer` writes the rows now, and `PluginRuntimeRefresher` runs it, so install, enable, disable, setup, and update all end with the catalog matching what the installed plugins declare.
- Rows are written for every installed plugin, enabled or not. Placement is already gated by the catalog filter, so a disabled plugin's block still stays out of pickers — and a block already placed on a page keeps a type row to resolve through instead of losing it the moment its plugin is switched off.
- A re-sync corrects what the plugin owns (`name`, `description`, `source_type`, `is_system`, `is_container`) and leaves `category`, `sort_order`, and `status` as the operator left them. Repairing a catalog should not silently republish a block someone set to draft to hide, or drag it back out of the tab they moved it to.
- `webblocks:catalog-repair --plugin-block-types`, included in `--all`, repairs installs that predate this — which is every install with a plugin block on it today. Updates already run `--all`, so the rows appear without an operator doing anything.
- The syncer refuses to write over a shipped core slug. A namespaced plugin handle cannot collide with one by accident, but "cannot happen" is a poor reason to let a malformed plugin rewrite the Hero block.

## 1.45.4

- **Every export failed validation.** The page picker always submits one empty `page_ids[]`, so that ticking nothing arrives as an explicit empty selection rather than as no selection at all — which means the whole site. That marker is not an id, and it hit `page_ids.*|integer`: "The page_ids.0 field must be an integer", on every export, whatever was ticked. The marker is filtered before validation now, and an empty selection still reaches the exporter as an empty selection.
- The tests around the picker read source strings and never submitted the form, which is exactly why they stayed green. `SiteExportRequestTest` validates the payload the form actually sends, including the marker, a real id, and rubbish that must still be rejected.

## 1.45.3

- **Every checkbox in the admin had been unstyled.** The views wrote `wb-checkbox` in 17 files; the UI's primitive is `wb-check`, one of `wb-check` / `wb-radio` / `wb-switch`, and `wb-checkbox` matches no rule anywhere. A class name that matches nothing fails silently — the markup renders and the page looks nearly right — so it took a table of seventy of them collapsing into wrapped text for anyone to notice. Renamed, and the CMS is no longer inconsistent with itself: it already used `wb-check` correctly in two places, and Herne Panel has used it in 17 all along.
- `UiClassContractTest` now fails on any `wb-` class in an admin view that no stylesheet defines. The admin loads the UI from a CDN, so it compares against `tests/fixtures/webblocks-ui-classes.txt`, a snapshot of the pinned runtime's class names; moving `Herne::UI_VERSION`'s counterpart `WebBlocks::UI_VERSION` without regenerating the snapshot fails too, so the check can never silently drift from the stylesheet the admin actually loads.
- The 56 class names still matching nothing are frozen in `tests/fixtures/known-unstyled-classes.txt` as a baseline that may only shrink — a name that becomes defined, or stops being used, fails the test rather than lingering. Some are probably JS hooks rather than style hooks; each needs its own look, which is not this release.

## 1.45.2

- The export page picker gives its list the room. The selected count moved up beside the heading, the standing paragraph under the table is gone — the archived-pages rule is visible in the table as unticked rows — and the media hint is a field hint rather than a paragraph. Eight rows are in view instead of five.

## 1.45.1

- **The export page picker was unreadable.** It stacked `wb-checkbox` labels, and that class has no styles anywhere in the product — seventy of them collapsed into wrapped inline text that ran over the fields below. The picker is a `wb-table` now: a row per page, a column each for the tick, the title, the status badge and the path, in a scrolling card. `wb-scroll-y` and `wb-badge-sm`, also used and also undefined, are gone with it.
- Both export screens show the same picker. Export / Import had it and Sites did not, which is the kind of difference nobody notices until an export from one of them quietly contains something the other would have excluded. The page list moved into `ExportablePages` so there is one source for both.

## 1.45.0

- **A site transfer carried the site's content and almost none of the site.** The export wrote seven fields for the site row — id, name, handle, domain, is_primary and timestamps — so five of the nine Edit Site tabs never travelled. An imported site arrived with no brand palette, no theme preset, no SEO defaults, no head code, no contact address and no branding, then rendered in the product default theme while the admin showed a complete import. All of those fields are exported now, and the importer applies them.
- Favicon and social image are media ids in the source install, and the site row is written before its media exists, so they are rebound in their own `site_branding` phase once the asset map is populated.
- **The export shipped `site.css` and `site.js` and nothing else under the site's directory.** A stylesheet declaring `@font-face` therefore arrived without a single font file. The whole of `public/site/{handle}` travels now, bounded by `webblocks-cms.export.site_asset_max_bytes` (50 MB by default) so an oversized directory stops the export with a message instead of producing a package nobody can upload.
- Copied stylesheets are rebased onto the importing site's handle. Site assets reference each other by absolute public path, so a site imported under a new handle previously had every font present on disk and 404 in the browser — indistinguishable from not shipping them. Only `.css` and `.js` are rewritten, and only when the handle actually changed.
- The two-filename allowlist behind that restriction existed in **three** places — the export builder, the archive builder and the importer. Generalising two of them was not enough; a test now asserts none of the three restricts site assets by filename.
- **A failed site-asset write no longer reports success.** `mkdir()` and `file_put_contents()` had their results discarded and the file was counted as copied either way, so a site could import with none of its assets on disk and nothing anywhere saying so. Both are checked, and an entry whose path the importer cannot resolve raises instead of being skipped.
- **The export screen lets you choose which pages go into the package.** Archived pages start unticked, with All / Published only / None shortcuts. On a site built through staged updates the discarded drafts are the bulk of the package: on this project's own site, 49 of 74 pages carried 73% of the blocks and translations, and excluding them took the import from 28.8s to 11.3s. Omitting the selection entirely still exports the whole site, so the CLI and the API are unchanged.

## 1.44.0

- **A site import now runs as resumable steps with a progress modal, instead of one transaction inside one request.** The old shape had no way to report progress even in principle: all fifteen phases ran inside a single `DB::transaction`, so nothing was visible to another connection until it committed and the import record read `validated` from start to finish. Working and hung looked identical, and behind Nginx a long import ended as a bare 504 with the transaction rolled back and the copied media left orphaned.
- Run import opens a modal that drives the import a step at a time and reports the phase it is on with real row counts — "Importing blocks, 12480 / 28607 (43%)". It uses the `wb-progress-bar` primitive from WebBlocks UI, so it adds no CSS of its own.
- Every step commits. Closing the tab pauses the import where it is rather than destroying it: the import record carries its own cursor (`resume_phase`, `resume_offset`, `resume_state`) and the screen offers **Resume import** or **Discard the partial site**. `site:import --resume={id}` does the same from the CLI.
- `SiteImportPlan` holds the phase order, and two positions in it are load-bearing. `domains` runs **last**: a site is only reachable through a `SiteDomain` row and `Site` has no published flag, so an interrupted import is never addressable on its real hostname. `search_index` runs after all content and before domains, as the one pass that builds the index now that writes defer it.
- The fifteen phase methods are unchanged; the step runner calls them with a sliced payload. Two passes had to be split out because they are whole-map work that a slice must not repeat: linking block parents (`wireBlockParents`), and normalising canonical translation storage. The second one was a real defect found in testing — run per slice, it gives every block still awaiting its translation a placeholder canonical row, and the next slice then collides with that placeholder on the `(block_id, locale_id)` unique index.
- Discarding a partial import deletes its site through `SiteDeleteService` — the one audited deletion path, blockers included — plus the media rows and copied files, which are install-scoped and would otherwise be collected by nothing. The package stays and can be imported again.
- Verified against this project's own 22-page site (7726 blocks, 4526 text translations): 28.8s uninterrupted, and an import killed at `blocks` offset 4000 and resumed in a fresh process produced an identical result — same block, translation, navigation and search-index counts, with all 22 index rows matching the hand-built site byte for byte.

## 1.43.2

- **Importing a site was quadratic, and the cost was the search index.** Every block, translation and slot save reindexes the whole page it belongs to — correct for an editor changing one block, ruinous for a bulk writer. Importing this project's own site (7726 blocks and 4526 text translations over 72 pages) therefore walked each page's full block tree once per row it wrote, and took **7m54s of pure CPU**. Behind a web request that is a 504 with a rolled-back transaction and orphaned media files; the import never had a chance to finish.
- `PublicSearchIndexer::deferring()` runs a bulk write with the reactive path switched off. It is a nesting-safe counter released in a `finally`, so a failed import resumes indexing. Only `refreshPage()` and `refreshSharedSlot()` — the entry points the ten model save hooks call — honour it. `rebuild()` and `rebuildPage()` never do: they are what the bulk writer calls when it is finished, and gating them would leave the imported pages out of the index permanently.
- The site import now defers its transaction and rebuilds the index **once after the commit**, so the work reads committed rows and stays outside the transaction. Same import: **28s**, 16.6x faster, with an index that is byte-identical to the incrementally built one (22 rows, same 215592 characters of content, zero rows differing in either direction).
- The gate lives in the indexer rather than in the models, so no save hook changed and any future bulk writer gets the same escape.

## 1.43.1

- Give each site its own timezone. `System Settings` held one timezone for the whole install, which is wrong for a multisite install whose sites run in different regions and blocks anything time-bound from being correct. Sites now carry a nullable `timezone` column with a picker on the Edit Site form; blank keeps following the install.
- Read it through `Site::resolvedTimezone()`, which returns the site value or falls back to the system setting. The raw `timezone` attribute stays null when unset, so "follow the install" remains distinguishable from an explicit choice that happens to match the install default — a distinction that matters when the install timezone later changes.
- Ship the column in all three migration paths: the alter migration for source-maintained installs, the `updates/` ensure migration for System Updates consumers, and the fresh-install schema.

## 1.43.0

- Let a plugin own a visitor-facing surface. `PluginDefinition::publicRoutes()` (manifest key `routes.public`) mounts a plugin's public route file under `/plugins/{handle}`, with names under `webblocks.plugins.{plugin_handle}.public.*`. The prefix is one reserved first segment shared by all plugins, so a plugin endpoint cannot shadow a page slug — public pages are served by dynamic `{slug}` routes, and an unprefixed plugin route would compete with real content. Until now the only way to ship a public plugin endpoint was to hardcode it in core `routes/public.php`, which is how the commerce bridge got there.
- Apply the public middleware stack in the registrar rather than trusting each plugin to assemble it: `web`, `install.required`, and a `plugin-public-routes` throttle default of 60/minute per IP and plugin, configurable through `webblocks-plugins.public_routes.rate_limit_per_minute`. A plugin can add a stricter per-route throttle and both apply. CSRF stays on — these serve browser forms, not the bearer-token clients `routes.api` serves.
- Honor the `admin_view` and `public_view` a plugin block type declares. Both were already parsed off the manifest and then ignored, so a plugin block could only render by mirroring the core view directory layout and guessing the filename that matches its catalog slug. `Block::publicRenderView()` and `Block::adminFormView()` now consult the plugin block registry first; a declared view that does not resolve falls back to the old convention instead of throwing mid-render.
- Memoize the enabled plugin block lookup in `PluginBlockCatalog`. `PluginRegistry::enabled()` deep-clones every definition it returns, which is affordable on an admin screen and not on a per-block render path. `PluginRuntimeRefresher` already forgets this singleton, so the memo cannot outlive a plugin install, enable, or update.
- Document the appointments plugin design in `docs/appointments-plugin-plan.md`. Booking ships as a plugin, not core: scheduling is a business domain, and the plugin boundary already forbids domain capabilities in core. The two extension points above are the first two phases of that plan.

## 1.42.8

- Fold the Update history accordion into the System Updates card. It used to render outside `section.wb-card` as an unframed strip orphaned below the card; it is now the last element of the card body, so the screen reads as one card in order: preflight → state → release notes → Update history. Run-log `wb-modal`s stay outside the card so overlays keep their own stacking context.
- Move the failing-preflight callout to the top of the card body, matching the order the shared `webblocks-publisher-client` view already used — the two System Updates surfaces in the fleet no longer disagree about where the pre-run warning goes.
- Stop rendering the history accordion when no runs are recorded. A fresh install used to show an empty `Update history (0)` accordion whose only content was "No update runs have been recorded yet."; the accordion is now omitted entirely and the `updates.no_update_runs` string is retired from all three locales.

## 1.42.7

- Move the pinned WebBlocks UI runtime from `v2.16.2` to `v2.16.3`, where `WBUpdateIndicator` reports a failed status fetch — `console.warn` naming the endpoint, plus `data-wb-update-indicator-state="error"` on the element — instead of swallowing it in an empty `catch`. A 404, a redirect to a login page (which arrives as 200 HTML and throws on parse) and a genuine "no update available" used to be indistinguishable: the navbar badge simply never appeared.

## 1.42.6

- Fix the navbar "update available" badge outliving the update it advertised. The badge is cached for an hour, and while the update controller already cleared it on a successful run, a request served between the apply and the worker recycling still runs the pre-update code: it re-checks, still reports itself as the old version, and re-caches the finished update for another hour. `AdminUpdateIndicator` now drops and recomputes a cached `update_available` whose version is not newer than the installed one, using the same lenient normalization as the update check (`v1.2.3` == `1.2.3`). This is the port of the guard shipped in `webblocks-publisher-client` 1.0.4 — the CMS runs its own engine and does not consume that package, so it needed the fix separately.

## 1.42.5

- Fix the Appearance tab, which 1.42.4 shipped broken: the font-picker setup used a block `@php`, Blade left the opening directive in the compiled view as text, and the tab rendered with `$fontOptions` and `$installedFontCount` undefined. The assignments use the inline `@php(...)` form now.
- Add `SiteFormCompilesTest`, which compiles the site form, the theme tab and the admin layout and fails if any directive survives compilation. The structure tests read the Blade as text, so nothing in the suite had ever compiled it.

## 1.42.4

- Render the brand colour fields as a fixed swatch beside a hex field. They carried `wb-input`, which stretched the native colour well to full width and made it read as a rule above the box rather than a colour control.
- Turn the typeface fields into pickers. `InstalledFonts` reads the `@font-face` families out of the site CSS asset and offers those alongside the system stacks that need no download; a hand-written stack stays available behind a Custom option. A site that loads no webfonts now says so and points at Assets instead of expecting the operator to type a family from memory.
- Move Assets before Appearance in the Edit Site tab strip, matching the order the two are used in: declare the faces, then choose them.
- Make the theme preview follow the preset select. The admin layout did not load `cms/css/public.css`, so the `[data-wb-public-theme-preview]` blocks that colour the preview never applied and changing the preset showed nothing. The layout loads it now — every rule in it is scoped to `[data-wb-public-theme]`, `[data-wb-public-theme-preview]` or `.wb-public-site-header`, none of which exist in admin chrome — and the preview island, its badge and its body-hook line update on change.

## 1.42.3

- Fix the Edit Site tab strip: 1.42.2 left the brand palette panel without its closing `</div>`, so every panel to its right nested inside it and never appeared, and `SiteController` kept a second literal tab list that never learned the new key, so the tab itself fell back to Site. Both are gone.
- Merge the brand palette and the theme preset into one `Appearance` tab, in the order the layers apply: preset first, palette below it overriding the roles it covers. Two separate tabs hid that relationship — a preset change looked like it did nothing when the palette was quietly overriding it. Branding keeps the site's name, tagline, favicon and social image.
- Make `Site::ADMIN_FORM_TABS` the single source for the strip; the controller whitelists against it and the form renders from it, so a new tab can no longer render a panel the controller refuses to select.
- Extend `SiteFormStructureTest` with the two guards that would have caught the regression: every panel must close its own markup, and the controller must not carry a second literal tab list.

## 1.42.2

- Give the brand palette its own `Brand palette` tab in `Sites -> Edit Site`, next to Branding. It shipped as a second card inside the Branding tab, where operators looked for it in the tab strip and did not find it. Branding keeps the site's name, tagline, favicon and social image; the palette tab owns the four brand colours and two font stacks. The tab is labelled in full rather than "Brand" so the two cannot be confused.

## 1.42.1

- Keep the brand palette card inside the branding tab of `Sites -> Edit Site`. It shipped in a second `wb-tabs-panel` carrying the branding tab key, leaving ten panels for nine tab buttons; the tab strip owns one panel per key, so a duplicate is a structural defect even where the browser renders both. Installs that still show the old form after updating are serving compiled Blade views from cache — clear them with `php artisan view:clear`.
- Ship the fifteen brand palette strings in Turkish and German; 1.42.0 added them in English only, so non-English admins fell back to English labels.
- Add `SiteFormStructureTest`: tab buttons and panels must line up one to one, the palette fields must live inside the branding panel, and every shipped locale must carry the palette strings.

## 1.42.0

- Add the site brand palette: `Sites -> Edit Site -> Branding` now takes four brand colours (accent, secondary accent, page background, text) and two font stacks (heading, body), and derives the rest of the public theme from them — hover/active states, soft tints, borders, muted text, surface layers, a readable foreground for every filled surface, and the complete dark-mode palette. Derivation is a pure function (`WebBlocks\Cms\Support\Theme\BrandPalette`) using sRGB mixing and WCAG relative luminance, so operators no longer hand-write `--wb-public-*` overrides into the site CSS asset or maintain a second palette for dark mode. Empty fields keep the selected public theme preset, so presets and partial palettes both keep working.
- Emit the resolved palette as one `<style id="wb-public-brand">` block in the public head, after `cms/css/public.css` and before the site CSS asset, so presets stay the base layer and hand-written site CSS can still override. The block also introduces `--wb-public-inverse-surface` / `--wb-public-inverse-text` for filled bands.
- Accept the six brand fields on `PATCH /webadmin/api/sites/{site}/branding` under the existing `site-settings.write` capability, and return a `brand_palette` object with the derived light/dark/font tokens plus the accent contrast ratio so operator tools can preview values without reimplementing the maths. Colours must be hex; font stacks are restricted to font names, quotes and commas so a stack cannot escape its declaration.
- Warn in the admin when the accent colour falls below a 4.5:1 contrast ratio against the page background instead of blocking the save, matching the existing site CSS mode-awareness warning model.

## 1.41.5

- Align the auth screens with the fleet's binding canonical string set (§5b pixel parity, 2026-07-26) in all three locales: subtitle becomes "Sign in to your :product account.", "Remember me" replaces "Remember this device", the forgot link gains its question mark, "Create an account" replaces "Create one", the forgot screen reads "Forgot your password?" and the reset screen "Choose a new password" with a dedicated "New password" label and a "Reset password" submit. Turkish strings also lose several long-standing i/ı typos. `guest.css` drops its wb-auth brand/mark sizing rules (geometry is owned by WebBlocks UI) and keeps only the temporary `wb-auth-brand-mark-on-surface` color rule until the UI ships that class.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## 1.41.4

- Align the auth screens with the fleet-standard §5b contract: password visibility on the login and reset screens now rides the WebBlocks UI runtime toggle (`data-wb-password-toggle`) instead of a hand-rolled inline script in the guest layout (script removed — the guest layout ships no JS of its own), the reset screen's two password fields gain the toggle, and failed inputs now carry the `wb-input-error` class alongside `aria-invalid`, so they actually render the error border. Markup only; no behavioral change to authentication.

## 1.41.3

- System update checks now include the runtime PHP and Laravel versions in the existing anonymous telemetry ping, so the Publisher's fleet analytics can chart the PHP distribution across installs. Runtime versions only — no domains, paths, or user data are sent, and telemetry can still be disabled with WEBBLOCKS_TELEMETRY=false.

## 1.41.2

- Fix the System Updates "What's new" panel repeating a release's description up to three times. A single-bullet release no longer re-renders its summary as a "Highlights" item, and the raw release-notes text is no longer shown in addition to the structured summary and groups. Rendering only; once installed it also cleans up how earlier releases display.

## 1.41.1

- Simplify the admin topbar user menu to an avatar-only trigger: the operator's name and email no longer render inline in the bar; clicking the avatar opens the same dropdown with profile (when available) and logout. Aligns the CMS operator admin with the fleet-standard topbar contract; no functional change.

## 1.41.0

- Make in-app System Updates a one-click flow: a single `Update to X` action now downloads, backs up, applies, migrates, and verifies the release in one run. The old two-phase prepare/continue/cancel flow and its separate pre-update backup download step are retired, along with the `system/updates/{continue,cancel,support-report}` admin endpoints and the super-admin support-report download.
- Take an automatic pre-update backup before every apply and automatically restore it when the apply fails. A failed-then-restored run is recorded with the new `restored` run status (`Failed, backup restored`); if the restore itself fails, the run stays `failed` with both error trails in the run log. Pre-update backups remain available on the Backups screen for manual download and restore.
- Redesign the System Updates screen to the fleet-standard v3 layout: a single status card, a folded "What's new" area with a per-version changelog accordion built from cumulative update-server changelog entries, one-click update with a backup note, and a non-dismissible interstitial that polls the update indicator until the updated app answers again.
- Add a server-backup advisory line next to the update action that links to the Backups screen, so operators are nudged to take a fresh full backup before a major update.
- Retire the source-maintained apply mode: `WEBBLOCKS_UPDATES_MIGRATION_STRATEGY` is ignored, in-app updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms`, and package update migrations under `database/migrations/updates` always run when present. Source-maintained maintenance checkouts update through git/Composer, not the in-app updater.
- Reduce preflight to the checks that matter and surface them on the screen: database connection, ZIP and sodium extensions, PHP/Composer/process execution, application-root and workspace write access, and free disk space. The update action is available only when every check passes; the old blocker state machine is gone.

## 1.40.27

- Clean the drift out of `admin.css` (and `guest.css`) the same way `public.css` was cleaned, against WebBlocks UI 2.16.x:
  - Delete the local `.wb-action-group`/`.wb-table-actions` copies (shipped since 2.15/2.16.2, including the new inline-form rule) and the local `.wb-btn.is-busy` spinner block — the busy state plus its `data-wb-busy` submit-lock behavior now ship in UI (`WBBusySubmit`); the admin binder delegates to it for dynamic rebinds.
  - Delete the `wb-navbar-breadcrumb`/`-wrap` glue and its markup classes — the shipped breadcrumb base already handles shrink/wrap, and UI 2.16.0 added the missing long-word breaking on `wb-breadcrumb-link`.
  - Reduce the `#wb-overlay-root` patch block to the deliberate CMS backdrop policy only; the duplicated shipped pointer-events rules are gone, and the drawer `display:none` deviation is replaced by UI 2.16.1's accessible closed-drawer hiding (restoring the slide animation in admin).
  - Drop the dead `--wb-primary-hover`/`--wb-accent-contrast` tokens from the brand remaps; the one consumer now reads the shipped `--wb-accent-on`.
  - Bump the pinned UI version to v2.16.2.

## 1.40.26

- Finish the `public.css` drift cleanup: the stylesheet now holds only the public theme palettes plus a handful of deliberate host-glue rules (~250 lines, down from ~650 before the gallery round).
  - Public navbar mobile menu moves onto the shipped `wb-navbar-drawer` contract (UI 2.15.0): the navigation block renders a shipped `wb-navbar-toggle` wired through the generic `data-wb-collapse` runtime, pushes its drawer through the new `PublicNavbarDrawerRegistry`, and the navbar container renders it directly after its own `</nav>`. The dropdown-based `wb-cms-navbar-mobile-*` layer and its media queries are deleted. Mobile menus now open as a full-width drawer under the navbar.
  - Icon tones consume the shipped `wb-icon-tone-*` axis: the theme token block feeds `--wb-icon-tone-*` from the public tone palette and the six local tone classes are deleted; rendered markup is unchanged.
  - Theme component overrides (`.wb-card`, `.wb-badge`, `.wb-btn-primary`, `.wb-navbar`/`.wb-sidebar`, `.wb-text-muted`) are deleted — the token remap already cascades the same values — after adding the missing `--wb-primary`/`--wb-primary-dark`/`--wb-primary-soft` remaps (these aliases resolve at `:root`, which is why the button override had been load-bearing). The body-link `accent-text` rule stays as a documented, deliberate contrast choice.
  - Small helpers land on shipped equivalents: honeypot wrappers use `wb-sr-only`, cluster gap "none" uses the new `wb-gap-0`, items "stretch" uses the new `wb-items-stretch`, the card header icon row uses `wb-icon-card`, and the card-footer cluster span plus the vestigial link-list icon rule move to (or are covered by) the shipped source. Dead `.wb-public-footer-fallback` removed.
  - New `NavbarDrawerRenderingTest` covers the drawer contract (toggle wiring, drawer after `</nav>`, menu content in both lists, group label rows). Bump the pinned UI version to v2.15.0.

## 1.40.25

- Migrate the Gallery block onto the shipped WebBlocks UI `wb-gallery` pattern (UI 2.14.0) and delete the local gallery CSS layer (~170 lines) from `public/cms/css/public.css`. The editor-facing options are unchanged — column count, gap, media ratio, masonry/collage variants, and below/overlay/on-hover captions now render through the shipped modifiers (`wb-gallery--cols-*`, `--gap-*`, `--aspect-*`, `--masonry`/`--collage`, `--captions-overlay`/`--captions-hover` with `--overlay-solid`/`--overlay-none`) instead of a parallel `wb-*` reimplementation. Overlay captions use the shipped `wb-gallery-caption` scrim with a nested `wb-gallery-meta`; both lightbox and direct-link items now share the styled `wb-gallery-trigger`. Bump the pinned UI version to v2.14.1.

## 1.40.24

- Finish the WebBlocks UI conformance follow-ups from the block-renderer review. Remove the dead `wb-link` class from the remaining public renderers (Contact Info, Card Grid, Showcase List, and the fallback renderer) — plain anchors are already styled by the UI foundation, so output is unchanged. Delete the now-unused `public/cms/js/public/header-actions.js` and its package asset manifest entries; the shipped WebBlocks UI theme behavior owns the mode toggle. Refresh the block documentation (`public-block-render-markup.md`, `block-ui-renderer-contract.md`, `inventory.md`, `public-assets.md`) to match the shipped UI 2.13.0 vocabulary and the current renderers: real `wb-stat-meta` and `wb-cluster` kicker classes instead of retired `wb-stat-detail` / `wb-cms-public-kicker`, base `wb-rich-text` instead of the retired readable modifier, the direct-child Callout alert anatomy, and the neutral `wb-btn wb-btn-ghost wb-btn-icon` Header Actions markup with host-localized mode labels.

## 1.40.23

- Consume WebBlocks UI 2.13.0 and migrate the navbar/topbar utility controls onto its shared, context-neutral vocabulary, removing the CMS's local reimplementation. The admin topbar's icon actions (system-update indicator, color mode, theme settings, language, and user menu) now use the shipped `wb-btn wb-btn-ghost wb-btn-icon` primitive inside a `wb-cluster` instead of the project-local `wb-navbar-iconbar` / `wb-navbar-icon-trigger` classes, and the update indicator's status dot uses the shipped `wb-btn-dot` — so those local classes are gone from `admin.css`. The public Header Actions block and the admin color-mode toggle now drive theming through the shipped `data-wb-mode-cycle` behavior with host-localized `data-wb-mode-label-{light,dark,auto}` labels (English, German, Turkish), which retires the duplicated mode/accent logic in `public/cms/js/public/header-actions.js` (now inert, pending a later file removal). The bundled UI pin moves from 2.11.0 to 2.13.0.

## 1.40.22

- Align public block renderers with shipped WebBlocks UI 2.11.0 primitives and remove dead CSS classes, with no visual regressions. Stat Card now uses the real `wb-stat-meta` slot instead of the non-existent `wb-stat-detail`, drops the dead `wb-link` class, and shows a translatable "Learn more" label (English, German, Turkish) instead of hardcoded English. Rating stops putting the `wb-rating` primitive on its `wb-card` shell — a primitive-boundary violation that forced a flex column onto the card — and removes the unused `wb-public-rating` and `wb-public-rating-title` classes; the star display and input keep working through their own custom-property defaults. Rich Text drops the retired `wb-rich-text-readable` modifier and keeps the base `wb-rich-text` readable typography. Columns removes the dead `wb-public-contact-columns` class and its detection logic. Column Item's stats variant no longer renders the same text as both the label and the value when a subtitle is not set. Callout and Testimonial now match the shipped alert and card anatomy: alert title and body are direct `wb-alert` children, and the testimonial renders `wb-card` on its `blockquote` with a muted attribution footer.

## 1.40.21

- Make Container a width-only, layout-neutral primitive by default. Unset, legacy, `none`, and unknown flow values no longer add `wb-stack`; editors and API clients must select `Flow: Stack` explicitly when the Container itself should own vertical child rhythm. Existing explicit stack choices remain unchanged, while Grid, Cluster, and Stack children can now compose inside old Containers without an inherited flex-column layout fighting them.

## 1.40.20

- Upgrade WebBlocks UI to 2.11.0 and render block background images through its native opt-in `wb-background-media` primitive. Hero, Section, Card, CTA, Content Header, and Slide keep their existing Media Library, position, and overlay settings, while WebBlocks UI now owns cover and overlay presentation. Remove the duplicate CMS background-media CSS; CMS remains responsible only for safe media URLs and allowlisted settings.

## 1.40.19

- Add a **Head Code** tab to Site Settings, so the custom head HTML added in 1.40.17 can be read and edited in the admin instead of only through the API. It shipped API-only, which left the markup on a site invisible to anyone working in the panel — a setting that renders on every public page should not be editable exclusively by a token. The tab carries the same field, the same ~64 KB cap, and the same blank-clears behaviour as `PATCH /webadmin/api/sites/{site}/head`, and it is gated by the existing site-settings permission, so the API and the panel stay two doors to one setting rather than two behaviours. The panel states plainly that the markup is inserted verbatim and can run scripts on every page, because that is the point of the field and also its risk. English, German, and Turkish strings included.

## 1.40.18

- Fix the 1.40.17 custom head HTML column never reaching existing installs. The `custom_head_html` column shipped in `database/migrations` and `database/migrations/fresh`, but a package consumer install runs neither: System Update only runs `database/migrations/updates`. So 1.40.17 delivered the endpoint and the renderer with no column behind them, and `PATCH /webadmin/api/sites/{site}/head` answered every request with its "not available until the latest site schema has been applied" guard — code without schema, which is the failure the three-directory split exists to prevent. Adds the missing idempotent ensure-migration under `database/migrations/updates`, so the column arrives on upgrade. A test now drops the column and drives that update migration directly, asserting the upgrade path adds it and that re-running is a no-op, because a fresh-schema test can only ever prove the clean-install half.

## 1.40.17

- Add per-site **custom head HTML**, so operator-authored markup can be injected into the public `<head>` of every page on a site. Until now there was no way to place a raw head tag — an ownership/verification `<meta>`, an SEO tag, or an analytics/tag-manager snippet — through the CMS: branding covered only favicon and social image, and site assets covered only CSS/JS files, neither of which reaches `<head>` as markup. A new `custom_head_html` column on the site is emitted verbatim just before `</head>`, after the site CSS/JS, and is written through `PATCH /webadmin/api/sites/{site}/head` with `custom_head_html` under the existing `site-settings.write` capability. Sending an empty value clears it. The markup is raw and unescaped by design (that is the point of a verification tag or a script snippet), so it is trusted operator input and must never be populated from untrusted or visitor sources; it is capped at ~64 KB. The API discovery catalog, OpenAPI paths, and AI guide advertise the endpoint as the single supported way to inject head markup, so a client is not pushed toward hand-written content blocks or site CSS/JS that cannot carry a `<head>` tag.

## 1.40.16

- Upgrade the bundled WebBlocks UI to 2.10.3, so titles in a card-framed Link List use the stronger card-heading typography while the existing `span` markup and standard Link List typography stay unchanged. Structured CMS Link Lists such as the **Try next** card now match the visual emphasis of the older hand-written card links without requiring site-specific CSS or HTML blocks.

## 1.40.15

- Let a Link List show landscape artwork instead of only a small square. The row thumbnail was a fixed 4rem square, so 4:3 artwork was cropped by `object-fit: cover` and wide rows looked sparse next to their copy. A new **Thumbnail Size** setting on the Link List block adds `wb-link-list--thumb-wide` (WebBlocks UI 2.10.2), which gives the leading column a share of the row width and renders the image at a 4:3 ratio, so it grows with the list instead of staying pinned to a fixed size. The default stays square, so existing lists keep their current look, and the setting composes with the Row Layout and List Frame styles added in 1.40.10. Rows that show an icon rather than a thumbnail are deliberately left on the narrow column, because a wide track would strand the icon in empty space.
- `settings.thumb_size` is writable through `PATCH /webadmin/api/blocks/{block}` from the start, taking `wide` or clearing to the square default, so it ships advertised-and-writable rather than repeating the contract drift 1.40.12 was written to prevent.

## 1.40.14

- Open the remaining block settings to the API, and derive the endpoint's gate from the value rules instead of a hand-written list. `PATCH /webadmin/api/blocks/{block}` now accepts 58 settings fields across the block catalog, including Alert and Sidebar Footer variants, Cluster, Container, Section and Card layout settings, Header alignment and anchor, Code language, navigation `menu_key` and active matching, Rating title and controls, and Comments form settings. Each field takes exactly the values the admin form allows, and anything else clears the setting rather than storing a value no renderer reads. `BlockSettingsPatchPolicy` is now the single owner of both which fields are writable and what values they accept, so the gate cannot drift from the sanitizer the way it did in 1.40.10.
- Keep four settings closed on purpose, for the same reason rather than by omission. `contact_form.recipient_email`, `send_email_notification`, and `store_submissions` decide where form submissions are delivered and whether they are retained, and `comments.show_author_name` decides whether commenter names appear publicly. Those are decisions about other people's data, not presentation, so they stay with the admin rather than an API token. `rating.scale` stays closed for a different reason: the admin form hard-codes it to 5, so opening it through the API alone would let a value be stored that the admin can neither produce nor show.

## 1.40.13

- Let the API change Hero layout and Grid layout settings on an existing block. `settings.layout` and `settings.title_tag` on `hero`, and `settings.layout_name`, `settings.columns`, `settings.gap`, `settings.alternate_media_text_sections`, and `settings.alternate_start` on `grid`, were declared by the published contract and refused by `PATCH /webadmin/api/blocks/{block}`. The split Hero layout added in 1.40.6 could be chosen when a hero was created and never afterwards. Each field takes the values the admin form already allows, and anything else clears the setting rather than storing a value no renderer reads. Turning Grid alternating off drops the alternating start with it, matching the admin.

## 1.40.12

- Let the API change an existing block's icon and badge. `PATCH /webadmin/api/blocks/{block}` refused `settings.icon_slug`, `settings.icon_tone`, and `settings.badge_tone` on all five icon-enabled block types, so an icon could be set when a block was created and never changed afterwards, even though the published contract advertised the fields. The endpoint now delegates to the icon normalizers `InternalContentApiOperations` has owned since 1.40.7 rather than growing a second set of icon rules, so an unknown slug is still refused and `icon_tone: default` still clears the tone.
- Record which block settings the API may write, and why the rest are refused. The PATCH allowlist and the published contract are separate hand-written lists that had drifted far apart: the contract declared 125 settings fields across 37 block types while the endpoint accepted a fraction of them, with no record of which gaps were deliberate. `BlockSettingsPatchPolicy` now names every refused field with a reason, separating the three `contact_form` delivery settings, which stay closed because an API token should not change where form submissions are sent or whether they are retained, from the fields that are only closed for want of a value rule. A contract sweep asserts every declared field is either patchable or recorded as closed, so a new setting can no longer ship advertised-but-unwritable the way the Link List styles did in 1.40.10.

## 1.40.11

- Fix the Link List styles being unwritable through the API. `PATCH /webadmin/api/blocks/{block}` keeps its own hand-written allowlist of settings fields, separate from the contract registry the published contract is built from, so the styles added in 1.40.10 were advertised by `content-contract` and then refused with `unsupported_block_settings_fields`. An API client was told to use fields the API rejected, and could only get the design by hand-writing a raw `html` block. The endpoint now accepts and sanitizes `settings.row_layout` and `settings.list_frame` for `link-list`; unknown values clear the style rather than store it, and fields the block type does not support are still refused.
- Fix two `docs/inventory.md` entries that had been wrong since the audit was taken at 1.40.2. It stated that no supported Card visual variant field existed, which stopped being true in 1.40.5 when the Card style select was added, and it predated both the Link List Item thumbnail and the Link List styles. The corrected entries are listed under a new Amendments section, so the audit baseline stays honest about what has and has not been re-checked.

## 1.40.10

- Add Row layout and List frame styles to the Link List block, so a structured link list can render as a compact card list instead of only as a directory index. Row layout `stacked` moves each row description under its title, beside any thumbnail or icon, replacing the wide description column. List frame `cards` gives each row its own card with spacing instead of one shared frame with separators. The two are independent and both default to the current look, so existing lists are unchanged. Previously this design could only be built by hand-writing a raw `html` block, which put the content outside the Media Library, translations, and the Internal Content API; the styles are settings, so the API can select them through `settings.row_layout` and `settings.list_frame`. Upgrades the bundled WebBlocks UI to v2.10.1, which adds the matching `wb-link-list--stacked` and `wb-link-list--cards` modifiers.

## 1.40.9

- Fix media pickers that silently discarded the chosen asset on save. `link-list-item` (the thumbnail added in 1.40.8), `cta`, and `content_header` all resolved `media_id` correctly and then re-added `asset_id => null` at the end of the admin request payload. `asset_id` is fillable and its setter writes `media_id`, so the trailing null was applied last and wiped the selection: the picker showed the image, the save reported success, and the block came back with no media. CTA and Content Header background images had been affected since before the thumbnail work; `hero`, `section`, `card`, and `image` were never affected. The three block branches no longer re-add `asset_id`, and a shared media assignment is now preserved rather than re-read from the locale form on a translated edit.
- Fix `link-list-item` media assignment through the Internal Content API, which still failed after 1.40.8 added the block type to the media rules. `InternalContentPlanService` kept its own hand-written copy of the direct media kind rules, so the plan path went on rejecting the thumbnail with "this block type does not support direct Media Library assignment". `InternalContentApiOperations` now owns the canonical rules and the plan service delegates to them, closing the same drift the icon list had in 1.40.7.

## 1.40.8

- Add an optional thumbnail to the Link List Item block, so a link row can lead with an image instead of an icon. The item editor gains a Media picker restricted to images, the thumbnail is stored on the canonical block `media_id` column, and the public renderer emits it as a `wb-link-list-thumb` image in the row's leading column. A thumbnail and an icon both claim that single column, so an uploaded thumbnail wins and the icon is skipped. The Internal Content API can assign the thumbnail through the existing `media_id` field, which previously rejected `link-list-item` outright.
- Fix link list rows that lead with an icon. The icon rendered into the row's main column and pushed the description onto its own line, because the renderer never emitted the leading-visual modifier. Rows with a thumbnail or an icon now carry `wb-link-list-item--media` and the icon carries `wb-link-list-icon`. Upgrades the bundled WebBlocks UI to v2.10.0, which adds the dedicated leading column.

## 1.40.7

- Fix public icon handling on the incremental block endpoints, which drifted from content apply. Adding a single block through `POST /webadmin/api/pages/{page}/slots/{slot}/blocks` or `POST /webadmin/api/shared-slots/{sharedSlot}/blocks` did not normalize or validate `settings.icon_slug` at all, so an unknown icon survived normalization and the public renderer silently skipped it, and `settings.icon_tone` was wrongly rejected on `feature-item` because the incremental block-type list omitted it. Icon normalization now has one owner: `InternalContentApiOperations` holds the canonical icon-enabled block type list plus the shared slug and tone normalizers, and the full content plan delegates to them instead of keeping a duplicate copy.

## 1.40.6

- Add a split Hero layout so a marketing intro can place its image beside the copy instead of behind it. Selecting the new Split layout renders the hero media as a `wb-promo-media` foreground image using the new WebBlocks UI `wb-promo--split` modifier, and skips the background image and overlay for that layout. The left and centered layouts keep using the same media as a background, so no new media field, relation, or migration was needed and the Internal Content API can select the layout through `settings.layout` with the existing `media_id`. Upgrades the bundled WebBlocks UI to v2.9.0.

## 1.40.5

- Fix Hero and CTA managed actions, which never rendered. The managed CTA buttons are created as `button_link` blocks, but the Hero renderer, the CTA renderer, the shared actions partial, and both admin editors filtered children for the unpublished `button` type, so every managed call to action was dropped before rendering and the admin CTA fields never prefilled from existing buttons. All five filters now accept `button` and `button_link`.
- Fix managed CTA storage shape. `button_link` resolves its href and target from block settings, but managed CTAs only wrote the legacy `button` columns, so an action that survived the filter still rendered without a URL. The shared `ManagedCtaSynchronizer` now writes `settings.url` and `settings.target` when the resolved button type is `button_link`, which also makes the Hero/CTA actions added through the Internal Content API in 1.40.4 render and stay editable.
- Add a visual style setting to the Card block. Cards now expose a Card style select (default, flat, muted, highlighted, accent) that renders the matching WebBlocks UI card variant class, mirroring how the Hero renderer already maps variants. The variant column was already accepted by validation and the API but was ignored by the card renderer and missing from the editor.

## 1.40.4

- Let the Internal Content API author Hero and CTA actions. `hero` and `cta` block payloads now accept optional `primary_cta` and `secondary_cta` objects (`{label, url}`, or `null` to clear), validated for a safe internal path or http(s) URL. They create the same managed `button_link` children the admin Page editor maintains, so an AI-built hero keeps its buttons editable in the normal block editor. Previously the API could not add a call to action to a Hero at all, because Hero only accepts managed `button` children and that type is not published in the catalog. The managed CTA logic moved out of the admin block controller into a shared `ManagedCtaSynchronizer` so the admin and every API create path share one behavior.
- Expose the Column Item subtitle field in the Columns editor. The Columns `stats` variant renders the child subtitle as the large stat value, but the editor never offered the field, so stat values silently fell back to the title.
- Document the `navigation-auto` block in the shared contract registry so it is discoverable through `GET /webadmin/api/block-types` and `GET /webadmin/api/content-contract`. It was a published catalog row with an admin form and public renderer but no documented contract.

## 1.40.3

- Ship `docs/inventory.md`, the AI-facing per-block design and authoring contract, and serve it to trusted tools through the new `GET /webadmin/api/inventory` endpoint as Markdown. API discovery links to it, recommends reading it first, and documents it in the AI guide and OpenAPI schema; the docs check now fails if the document goes missing.
- Make the `html` block human-only for the Internal Content API through one central product policy (`BlockTypeApiAuthoringPolicy`). Operators keep creating and editing Trusted HTML in the CMS admin and existing published blocks keep rendering, but no API mutation can create, update, replace, move, reorder, publish, or delete an HTML block, and no token capability overrides it. Rejections happen before any write, return HTTP 422 with the stable code `block_type_not_api_writable`, and leave no partial changes. The policy guards both block normalizers, existing-block PATCH, page and Shared Slot incremental create, reorder, subtree delete, Shared Slot clear-all, page and Shared Slot publish, draft slot replacement, staged update creation and promotion, Shared Slot assignment, and API page delete.
- Report `api_readable`, `api_writable`, and `authoring` for every block type in `GET /webadmin/api/block-types` and `GET /webadmin/api/content-contract` from the central policy, including the stable rejection code and restriction for `html`, and stop publishing writable examples for human-only blocks.

## 1.40.2

- Add Page Assets endpoints to the Internal Content API so trusted tools can list, attach, update, and detach a page's own `/site` CSS and JS files: `GET /webadmin/api/pages/{page}/assets`, `POST .../assets/{type}` (css or js), `PATCH .../assets/{pageAsset}`, and `DELETE .../assets/{pageAsset}`. Writes require the new opt-in `page-assets.write` capability. Paths reuse the existing page asset path validator, so only local `/site/...` paths with a matching `.css`/`.js` extension are accepted and external URLs, `javascript:`/`data:` paths, traversal, query strings, and fragments are rejected; the endpoint only attaches an existing file and never writes file contents. Every write captures a page revision.
- Document that content plans already support media by existing Media Library ID (`media_id`/`asset_id` plus Gallery `gallery_media_ids`/`gallery_items`, validated for existence and block-type kind compatibility), and mark the corresponding Phase 3 roadmap items delivered.

## 1.40.1

- Add an optional `create_restore_point` flag to `POST /webadmin/api/content/apply`. When set, the Internal Content API takes a full system backup (database plus uploads) restore point before applying the plan, so an operator can roll back from System -> Backups if an AI-generated apply goes wrong. It requires the new opt-in `backups.create` capability, validates the plan first so an invalid plan does not create a wasted backup, and aborts the apply with JSON 409 if the backup fails so content is never applied without the requested safety net. Successful responses include a `restore_point` summary, and the backup is recorded with a dedicated `content_apply` type. The API intentionally exposes only restore-point creation; restoring, downloading, and deleting backups stay in the operator admin UI.

## 1.40.0

- Add Shared Slot block topology endpoints to the Internal Content API (Phase 2B): `PATCH /webadmin/api/shared-slots/{sharedSlot}/blocks/reorder` reorders a sibling group (requires `shared-slots.write`), `DELETE .../blocks/{block}` removes one block subtree, and `DELETE .../blocks` clears every block for clear-and-replace (both deletes require `shared-slots.write` plus `content.blocks.delete`). Existing Shared Slot block content edits keep using `PATCH /blocks/{block}`. Every write rebuilds the slot's page assignments and captures a Shared Slot revision. Because Shared Slots have no draft-page concept, changes to already-published Shared Slot blocks affect every assigned page immediately, which is why deletion is gated behind the destructive `content.blocks.delete` capability.

## 1.39.0

- Redesign the public Rating block to use the new WebBlocks UI `wb-rating` star component: a read-only average shown as partially filled stars plus count, and a no-JS interactive star input that fills on hover up to the pointed star (each star still submits its own value, so the safe no-JavaScript flow is preserved). Upgrades the bundled WebBlocks UI to v2.8.0.
- Add an optional `Heading` setting to the Rating block so editors can show a title above the stars; leaving it empty keeps the previous behavior of composing a heading with a separate Header block.

## 1.38.1

- Fix the API token capabilities counter so it shows selected-of-total instead of selected-of-selected, and register the `content.blocks.delete` capability in the "Publishing and destructive actions" group so it is selectable in the token editor and counted in group and header totals.
- Collapse all API token capability groups by default on the Create Token form; previously the "Page building" group was always expanded and stretched the page.
- Add an Engagement overview landing page with Comments and Ratings summary cards (counts, pending review, average rating) and links to each list, and point the Engagement navigation item at it instead of opening Comments directly.
- Add search and rating-value filters to the Engagement Ratings page.

## 1.38.0

- Add draft-safe page block topology endpoints to the Internal Content API so trusted AI/operator tools can edit a draft page incrementally without sending a full content plan: `POST /webadmin/api/pages/{page}/slots/{slot}/blocks` adds a single block (with optional children), `PATCH .../blocks/reorder` renumbers a slot sibling group, and `DELETE .../blocks/{block}` removes a block subtree. Create and reorder require `content.apply`; deletion requires the new opt-in `content.blocks.delete` capability that is not part of the default page-building set. The endpoints operate only on draft pages and page-owned slots, reject Shared Slot-backed slots and Shared Slot source blocks, and capture a page revision on every write.

## 1.37.4

- Sync the shipped block type, slot type, page layout, and icon catalog automatically during System Updates by running `webblocks:catalog-repair --all` in the post-install flow, so a release can add catalog rows such as the engagement Rating and Comments block types without an operator running a manual command. The sync runs after cache clears, preserves custom catalog rows, and is best-effort so it cannot fail an otherwise successful update.

## 1.37.3

- Report site CSS and JavaScript assets as writable when `public/site` does not exist yet but CMS can create it through a writable parent directory, matching the first-write behavior of the asset API.
- Add the public session-cart bridge and SumUp webhook endpoint required by WebBlocks Commerce 0.8.0, while keeping every route inert unless the plugin is enabled.

## 1.37.2

- Restore OpenAPI schema generation for Plugin Catalog endpoints.

## 1.37.1

- Make the package-only Composer publishing wrapper load `WEBBLOCKS_PUBLISHER_TOKEN` from the project `.env` into its isolated Testbench process without sourcing or exposing unrelated environment values.
- Preserve signed update publishing in the package-only wrapper by passing `WEBBLOCKS_PUBLISHER_SIGNING_KEY` into Testbench and validating it during Publisher dry-runs.
- Expose bearer-authenticated Plugin Catalog list, detail, and checksum-verified install endpoints through the Internal Content API, using existing `plugins.read` and `plugins.install` capabilities and installing catalog packages disabled.
- Add one-click copy controls for the one-time CMS API token and local `.env` example, with localized accessible feedback and a legacy clipboard fallback.
- Repair package-native Engagement schema readiness during System Updates by idempotently renaming legacy unprefixed tables or creating the required `wbcms_comment_entries` and `wbcms_content_ratings` tables automatically.
- Harden image variants with accurate responsive widths, safe small-source cropping and codec fallback, focused cache invalidation, Gallery and social-image integration, and operational regeneration/pruning.
- Rate-limit admin sign-in and password-reset requests: failed logins lock per email+IP after a configurable threshold (cleared on success), with a per-IP backstop across the login, forgot-password, and reset-password endpoints. Tunable via `WEBBLOCKS_CMS_MAX_LOGIN_ATTEMPTS` and `WEBBLOCKS_CMS_LOGIN_DECAY_SECONDS`.
- Keep SVG out of the default media upload and remote-fetch allowlist; operators who trust every media-uploading account can opt back in with `WEBBLOCKS_CMS_ALLOW_SVG_UPLOADS=true`. Consolidate the accepted MIME allowlist into one place so uploads, the Internal Content API, and remote fetch stay in sync.
- Clarify plugin install wording in the README: catalog releases are checksum-verified and manual ZIPs are validated on upload and disabled by default.

## 1.37.0

- Make the public GitHub repository package-only: the repository root is now the `fklavyenet/webblocks-cms` Composer package, not a complete deployable Laravel application. New source installations should use Composer through Packagist.
- Keep existing Publisher/System Updates installations supported, including normal upgrades from the `1.36.1` compatibility release, without changing the CMS schema or pinned WebBlocks UI `v2.7.18` assets.
- Require historical clone-based installations to preserve their host `.env`, database, storage/uploads, plugins, project content, application files, and public overrides while following `UPGRADING.md`; the package repository must not replace host-owned state.

## 1.36.1

- Prepare existing installations for the future package-only repository layout with stricter update-archive safety checks and standalone Composer package readiness.
- Before the repository transition, install this compatibility release through the documented update path. A future repository checkout will no longer be a complete deployable Laravel application, so clone-based installations must not assume that a normal `git pull` across the cutover is safe.
- Preserve application-owned `.env`, database, storage, uploads, plugins, project content, and host files during any staged clone-to-Composer migration. Composer/package-native installs remain the supported source-consumption model, while Publisher/System Updates users should continue using the checksum/signature-verified update flow.

## 1.36.0

- Add CMS-managed image variants with responsive public image output, cached focal-point-aware crops, optimized media-picker thumbnails, and safe original-image fallbacks.
- Add focal-point editing and variant previews/regeneration to the Media edit screen in English, German, and Turkish.

## 1.35.5

- Render public Column Item icons beside their copy with the shipped WebBlocks UI `wb-icon-card` composition, reducing unnecessary card height without CMS-specific layout CSS.

## 1.35.4

- Pin WebBlocks UI to `v2.7.18`, add the canonical admin language switcher with immediate per-user locale updates, and migrate the authenticated topbar account control to `wb-user-menu`.

## 1.35.3

- Ship the first cryptographically signed WebBlocks CMS release now that the update publisher stores and serves the release signature, so installs that pin the maintainer public key verify update authenticity end to end.

## 1.35.2

- Enforce Ed25519 signature verification on System Updates by pinning the maintainer public key, so installs now reject any update package that is not signed by the release key in addition to the existing checksum check.
- Fix the System Plugins index table so the meta columns no longer break mid-word: the plugin name keeps its word boundaries, the version, source, and status stay on one line, and the health message wraps within its own column.

## 1.35.1

- Show the admin flash banner on the System Plugins index and detail pages so catalog updates, enable, disable, setup, and uninstall report their success or error outcome instead of completing silently.
- Confirm a plugin catalog update before it runs with a modal showing the installed and target version, and lock the confirm button with a spinner and progress label while the update is in flight so it cannot be double-submitted. Localized for English, German, and Turkish.

## 1.35.0

- Verify a detached Ed25519 signature and package checksum before applying a System Update, so tampered or corrupted update packages are rejected.
- Add the documented `cms_trans()` helper and plugin translation loading so first-party plugins can ship locale-aware `resources/lang` catalogs.
- Move visible WebBlocks Commerce and WebBlocks UI Manager admin surface copy onto plugin translation keys for English, German, and Turkish.
- Let the Internal Content API existing-block update endpoint write Image block `alt_text` and `caption` translation rows.
- Harden in-app System Updates so updater result and failure handling classes stay available after the package root is replaced and before Composer autoload metadata has fully settled.
- Add plugin API extensibility hooks so enabled plugins own their whole internal API surface: `PluginDefinition::apiRoutes()` mounts plugin route files under `/webadmin/api`, `apiDiscovery()` self-advertises endpoints in API discovery and OpenAPI, and `apiCapabilities()` contributes token capabilities and a token-UI permission group.
- Make Internal API token capabilities plugin-extensible: the grantable set, token permission groups, and OpenAPI/discovery now merge CMS core with capabilities contributed by enabled plugins, and commerce capabilities are no longer hardcoded in the CMS. Retire the CMS-core commerce API controller in favor of the plugin-owned one.
- Grow the WebBlocks Commerce plugin into an AI-first store: guarded order state machine with atomic inventory reservation and a stale-order expiry command, country-agnostic VAT snapshotted onto orders, a server-side multi-line cart with hosted checkout, multilingual product content sharing the CMS Site+Locale system, and a plugin-owned product/order/cart/translation API.

## 1.34.11

- Bumped CMS to `1.34.11`.
- Fix Engagement comment search so page matches use page translations instead of removed legacy page columns.
- Keep admin page-title lookups translation-aware on Engagement and Blocks listings, and align page slug accessors with the current translation.

## 1.34.10

- Bumped CMS to `1.34.10`.
- Add modal-confirmed bulk deletion to CMS Users so super admins can remove selected managed users faster.

## 1.34.9

- Bumped CMS to `1.34.9`.
- Scope `/webadmin/users` to CMS-managed users so host-only coexistence accounts stay out of CMS user management.

## 1.34.8

- Bumped CMS to `1.34.8`.
- Align CMS static icon classes with the pinned WebBlocks UI icon manifest and add a regression test for unknown `wb-icon-*` usage.

## 1.34.7

- Bumped CMS to `1.34.7`.
- Localize Locales, Page Layouts, and CMS API token capability screens through the authenticated admin locale.

## 1.34.6

- Bumped CMS to `1.34.6`.
- Localize Profile success flash messages through structured admin locale keys.

## 1.34.5

- Bumped CMS to `1.34.5`.
- Remove the legacy admin HTML localization bridge now that admin screens use structured native translation keys.
- Polish remaining Turkish and German admin locale diacritic artifacts found after the 1.34.4 locale pass.

## 1.34.4

- Bumped CMS to `1.34.4`.
- Polish Turkish and German admin locale copy for native diacritics, natural wording, and corrected fallback lookup keys.

## 1.34.3

- Bumped CMS to `1.34.3`.
- Preserve Turkish and German diacritics in admin locale copy instead of ASCII transliterations.

## 1.34.2

- Bumped CMS to `1.34.2`.
- Move Page details, duplicate, layout slot summary, and slot block delete modal copy onto structured admin locale keys.
- Move Navigation Items and Locales admin copy onto structured admin locale keys.
- Move column item editor and Page Layout admin copy onto structured admin locale keys.
- Move Page asset, import, inline block, and page form helper copy onto structured admin locale keys.
- Move Plugin Catalog, block type, domain, page move, and block form admin copy onto structured admin locale keys.
- Move Blocks, Block Types, and System Plugins listing copy onto structured admin locale keys.
- Move Block Type contract modal catalog, storage, translation, renderer, and gap copy onto structured admin locale keys.
- Move Page Layout Slot form identity, wrapper markup, trusted HTML, and status copy onto structured admin locale keys.
- Move Media asset picker controls, filters, empty states, upload, and modal actions onto structured admin locale keys.
- Move Page Slot block picker modal, tabs, search, table, and empty-state copy onto structured admin locale keys.
- Move Contact Messages listing filters, table, row delete, and bulk delete copy onto structured admin locale keys.
- Move System Plugin detail lifecycle, capabilities, settings, health, and uninstall copy onto structured admin locale keys.
- Move Contact Message detail, notification, classification, technical detail, and delete modal copy onto structured admin locale keys.
- Move Media edit preview, usage, metadata, file details, and delete modal copy onto structured admin locale keys.
- Move Page Slots card, source modal, and delete confirmation copy onto structured admin locale keys.
- Move Page Revision history copy onto structured admin locale keys.
- Move Page Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Page Translation form routing, SEO, Open Graph, and action copy onto structured admin locale keys.
- Move Shared Slot revision history and snapshot detail copy onto structured admin locale keys.
- Move Shared Slot block editor wrapper, locale, empty-state, and table copy onto structured admin locale keys.
- Move Shared Slots index, create, edit, and form copy onto structured admin locale keys.
- Move Search Index admin screen copy onto structured admin locale keys.
- Move Slot Types index copy onto structured admin locale keys.
- Move Site Clone and Delete admin copy onto structured admin locale keys.
- Move Site Domains, Site details, Site Assets, Public Theme, and Site Variables admin copy onto structured admin locale keys.
- Move Sites create/edit form tabs, branding, SEO, contact, and footer action copy onto structured admin locale keys.
- Move Page edit management, overview, publish modal, and translation table copy onto structured admin locale keys.
- Move System Settings general, project identity, mail, diagnostics, privacy, and runtime copy onto structured admin locale keys.
- Move Media Library listing, grid, preview, upload, fetch, folder, and bulk-delete copy onto structured admin locale keys.
- Move Visitor Reports admin screen copy onto structured admin locale keys for native translation readiness.
- Add a native-only admin translation audit mode that ignores the legacy HTML fallback map and blocks `LocalizeAdminHtml` removal until direct structured-key migration is complete.
- Move Profile, Slot Types, flash, and page action partial copy onto structured admin locale keys, bringing the admin translation audit to 100% coverage.
- Move System Icons index and edit modal copy onto structured admin locale keys.
- Move fallback, layout shell, content header, stat card, inline media/link, and shared icon badge block editor copy onto structured admin locale keys.
- Move Sidebar Nav Item, Sidebar Nav Group, and Sidebar Footer block editor copy onto structured admin locale keys.
- Move Users admin listing and form copy onto structured admin locale keys.
- Move Hero block editor copy onto structured admin locale keys.
- Move Gallery Items and Rich Text editor partial copy onto structured admin locale keys.
- Move Page Converter admin screen copy onto structured admin locale keys.
- Move Site Promotion admin screen copy onto structured admin locale keys.
- Move Plugin Catalog detail screen copy onto structured admin locale keys.
- Move CMS API Tokens main admin screen copy onto structured admin locale keys.
- Harden admin translation auditing so new admin Blade view families are discovered automatically and strict baseline checks fail on newly uncovered UI phrases.
- Add an admin translation quality gate script for German and Turkish admin locales.
- Move System Updates blocker copy and Export / Import admin screen copy onto structured admin locale keys.
- Move Columns, Link List, Feature Grid, and Contact Form block editor copy onto structured admin locale keys.
- Move Header Actions, Audio, Breadcrumb, and Download block editor copy onto structured admin locale keys.
- Move Link List Item, List, Table, Container, and Grid block editor copy onto structured admin locale keys.
- Move Runtime Status, Search Form, Sticky Navbar, Header, and Sticky Navbar settings copy onto structured admin locale keys.
- Move Accordion, Callout, Column Item, Download Inline, and Feature Item block editor copy onto structured admin locale keys.
- Document that the `LocalizeAdminHtml` bridge must be removed when admin translation migration is complete.
- Move Button Link, Trusted HTML, Section, Tabs, FAQ, Text, and TOC block editor copy onto structured admin locale keys.
- Move Slide and Gallery block settings copy onto structured admin locale keys.
- Move Sidebar and Navbar brand/navigation block editor copy onto structured admin locale keys.
- Move shared pagination and small block presentation settings copy onto structured admin locale keys.
- Move File, Video, Quote, Text Inline, and Feature Grid editor copy onto structured admin locale keys.
- Move Cluster and Slider block settings copy onto structured admin locale keys.
- Move CTA, Button, Navigation Auto, and shared background media editor copy onto structured admin locale keys.
- Move Image, Button Inline, Slide, Navigation Auto Inline, and API token capability copy onto structured admin locale keys.

## 1.34.1

- Bumped CMS to `1.34.1`.
- Move System Updates and Backups screen card, body, modal, action, and status copy onto the selected admin locale.
- Add regression coverage for localized System Updates and Backups admin screen body copy.

## 1.34.0

- Bumped CMS to `1.34.0`.
- Add an admin translation audit command for measuring hard-coded Blade UI copy coverage against the admin HTML fallback map.
- Broaden German and Turkish admin HTML fallback coverage to 100% for audited admin Blade UI copy across media, plugins, settings, visitor reports, contact messages, page/slot, site, revision, and system screens.

## 1.33.1

- Bumped CMS to `1.33.1`.
- Make Sites and Pages admin listing screens resolve primary screen, filter, table, and action copy from the selected admin locale.
- Add an admin HTML localization fallback so resource, system, media, user, locale, and report screens use the authenticated admin locale beyond the sidebar/topbar while deeper Blade migrations continue.

## 1.33.0

- Bumped CMS to `1.33.0`.
- Add the first file-based CMS translations layer for admin, public system copy, and block defaults.
- Add an install-wide admin panel language setting and use it in the admin shell/sidebar/topbar.
- Add per-user admin panel language preferences on the Profile screen, with system admin language fallback.
- Make public Search UI and Search Form defaults resolve copy from the current public locale.
- Make Contact Form default visitor labels resolve from the block translation catalog.
- Make public Comments and Rating system block copy and engagement success states resolve from the current public locale.
- Make CMS auth and password reset screens, auth validation copy, and reset email copy resolve from the admin locale.
- Make Dashboard and Engagement admin screens resolve visible interface copy from the admin locale.
- Make Contact Form, Comments, and Rating validation feedback resolve from the active public locale and keep engagement validation redirects on the relevant block.
- Make Engagement admin comment status flash messages resolve from the admin locale.
- Make the admin block type picker and Comments/Rating system block editor settings resolve copy from the admin locale.

## 1.32.246

- Bumped CMS to `1.32.246`.
- Render public page `<html lang>` from the page translation locale instead of the Laravel app fallback locale.

## 1.32.245

- Bumped CMS to `1.32.245`.
- Replace the Add/Edit Locale picker with a short standard language list and keep country variants/custom BCP 47 style tags behind custom locale details.
- Show the same locale picker on Edit Locale, with the current standard locale selected when available.
- Simplify Internal Content API locale options to the same curated standard language list.

## 1.32.244

- Bumped CMS to `1.32.244`.
- Add a searchable standard locale picker to the Add Locale admin form and expose the same locale option catalog through the Internal Content API.
- Broaden locale code validation to accept route-safe BCP 47 style tags such as `zh-hant-hk` while preserving custom locale support for operator cases.

## 1.32.243

- Bumped CMS to `1.32.243`.
- Fix the Site edit screen so site-level Branding media pickers render outside a block editor context instead of raising a 500 error.
- Preserve Contact Form submit and success copy from Internal Content API content plans, and use German default public form labels when rendering German locale pages.
- Add Internal Content API locale create/update endpoints with `site-settings.write` capability checks so migration tools can correct install locales before applying localized content.

## 1.32.242

- Bumped CMS to `1.32.242`.
- Preserve authored Gallery media order when Internal Content API plans assign `gallery_items` or `gallery_media_ids`.

## 1.32.241

- Bumped CMS to `1.32.241` and pinned WebBlocks UI to `v2.7.17`.
- Show a non-dismissible System Updates progress modal with the version path and shared WebBlocks UI spinner when an operator starts or continues an update.

## 1.32.240

- Bumped CMS to `1.32.240`.
- Center the Gallery lightbox `Viewer title` in the viewer header.

## 1.32.239

- Bumped CMS to `1.32.239`.
- Add a Gallery `Viewer title` setting so lightbox modals can show the current image collection name without restoring legacy public Gallery headings.
- Stop public Gallery rendering from exposing technical import notes such as `Imported from ... during ... migration` as item captions, overlay meta, or lightbox metadata.

## 1.32.238

- Bumped CMS to `1.32.238`.
- Make sibling alternating media/text Grid blocks share one parent sequence so reordering adjacent profile grids no longer preserves editor-selected per-grid left/right placement.

## 1.32.237

- Bumped CMS to `1.32.237`.
- Make alternating media/text Grid ordering work when the Grid directly contains a Slider and a text Section, matching existing editorial page structures.

## 1.32.236

- Bumped CMS to `1.32.236`.
- Keep alternating media/text Grid blocks on the normal `wb-grid` wrapper while reordering direct Section columns by detected media/text content.

## 1.32.235

- Bumped CMS to `1.32.235`.
- Add a Grid setting that renders direct Section children as alternating media/text rows, so editors can reorder sections without manually maintaining left/right slider and copy placement.

## 1.32.234

- Bumped CMS to `1.32.234`.
- Add mode-awareness analysis to canonical site CSS API responses so migration and new-site tools can catch hard-coded light palette regressions before marking a site complete.
- Pin WebBlocks UI `v2.7.16` and add native Navbar Navigation active indicator and active matching settings so current-page menu state can be made visible without site-specific CSS.

## 1.32.233

- Bumped CMS to `1.32.233`.
- Allow existing `header-actions` blocks to update search, mode, and accent toggle settings through the Internal Content API.
