---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/core-concepts
cms_title: Core Concepts
cms_layout: docs
cms_source_id: webblocks-cms:docs/core-concepts.md
---

# Core Concepts

WebBlocks CMS uses an explicit relational content model built around pages, layouts, slots, and blocks.

## Content Model

The core structure is:

`Page -> Layout -> Slots -> Blocks`

- A page owns routing, site context, workflow state, and layout selection.
- A page can also own relational page asset rows for page-scoped CSS and JS file references.
- A layout defines the available structural regions.
- Slots are named placement areas inside the layout, such as `header`, `main`, `sidebar`, and `footer`.
- Blocks are the actual content units placed into slots and, when supported, nested under other blocks.

Pages do not store free-form page-builder JSON. Content and relationships are kept in relational tables so structure stays explicit and reviewable.

## Page Assets

- Page Assets are a core CMS feature for page-scoped CSS and JS file references.
- Page Assets are stored relationally in `page_assets`, not inside `pages.settings` JSON.
- V1 accepts only local install paths under `/site/...`.
- External URLs, inline CSS, inline JS, query strings, fragments, traversal, and non-matching extensions are rejected.
- CSS currently renders only in the public document head.
- JS currently renders only in the public document head with `defer`.
- CMS-owned public assets live under `public/cms/`.
- `public/storage` is the Laravel public storage symlink for `storage/app/public` and is separate from site/page override assets.
- Canonical public asset paths are `public/site/{site_handle}/css/site.css`, `public/site/{site_handle}/js/site.js`, `public/site/{site_handle}/pages/{page_slug}/page.css`, and `public/site/{site_handle}/pages/{page_slug}/page.js`.
- Page Assets render only for the owning public page and are excluded from admin layouts and unrelated pages.
- Page revisions, duplicate, move, and site export or import treat Page Assets as page-owned configuration.
- Single-page JSON import can also create `page_assets` rows when the payload uses valid local `/site/...` paths that pass the existing page asset validation rules.
- Search indexing does not treat page asset paths as page body content.

## Page Site Scope

- A page belongs to exactly one site at a time.
- The normal `Edit Page` form keeps `Site` read-only for existing pages.
- Duplicating a page creates a new page record in the same site or another accessible site.
- Cross-site page moves use a dedicated `Move to another site` workflow instead of an inline field change.
- Page duplication uses a dedicated `Duplicate page` workflow instead of piggybacking on the normal page form.
- The move runs as a controlled transaction so `pages.site_id` and `page_translations.site_id` stay valid against the composite `(page_id, site_id)` foreign key.
- Duplicate always starts the new page as draft and copies content state without copying the source page revision history.
- The move preserves the page id, translations, slots, page-owned blocks, block translations, order, workflow state, and revision history.
- Duplicate preserves layout, translations, slots, page-owned blocks, nested block relationships, block translations, and compatible Shared Slot references, but it creates a new page id.
- Target-site path conflicts are blocking validation errors in the current version.
- Shared Slot references must be remappable to compatible same-handle Shared Slots on the target site, otherwise the move is blocked.
- Same-site duplicate preserves existing Shared Slot references.
- Cross-site duplicate remaps only compatible same-handle target-site Shared Slots.
- When a cross-site duplicate cannot remap some Shared Slot-backed slots, the default behavior is still to block the duplicate.
- The duplicate workflow can now explicitly disable only those incompatible duplicated page slots instead of persisting an invalid cross-site Shared Slot reference.
- That opt-in fallback writes the duplicated page slot as `disabled`, clears `shared_slot_id`, leaves the source page unchanged, and does not copy Shared Slot block trees into the duplicated page in this version.
- Site-level portability tools such as Export / Import and Site Clone remain separate from page moves and page duplication.
- `Admin -> Pages -> Import Page` is a page-scoped portability workflow. It creates one new page from a documented `webblocks.cms.page.v1` JSON payload and is intentionally separate from site-level Export / Import.
- V1 single-page import always creates a new page and always imports it as draft.
- V1 single-page import does not import source page revision history.
- Shared Slot-backed page slots in single-page import must reference a compatible target-site Shared Slot by stable handle. The importer does not create Shared Slots automatically in V1.

## Site Identity

- WebBlocks CMS product identity is fixed in the admin shell and comes from `App\Support\WebBlocks`.
- Project Identity is install-level admin context configured in `Admin -> System -> Settings`.
- Project Identity currently includes `Project Name` and `Project Tagline`.
- Project Identity is used only for admin topbar context and admin browser titles.
- Project Identity does not change the fixed WebBlocks CMS sidebar brand, the sidebar version footer, public site metadata, public favicon, public search scope, or page translation SEO values.
- Site records own public-facing identity and metadata.
- `sites.name` remains the internal admin record name.
- `display_name` is the optional public site name override.
- `tagline` is optional public-facing copy for the site.
- `seo_title`, `seo_description`, and `seo_keywords` are site-level fallback metadata.
- `favicon_media_id` and `social_image_media_id` are optional site-level media references for public head output.
- `site_variables` are optional site-level reusable plain-text values for controlled public token replacement.
- Page translation rows also own localized SEO override fields such as `seo_title`, `seo_description`, `seo_keywords`, `og_title`, `og_description`, and `og_image_media_id`.
- This keeps public page metadata locale-aware and out of shared page JSON settings.

## Media

- `Media` is the canonical name for shared uploaded files across the active CMS runtime.
- Active technical names now use `Media`, `media_id`, `media`, `media_folders`, and `block_media` across current runtime code, requests, revisions, and site transfer packages.
- Legacy `Asset` names remain only for compatibility wrappers, historical migrations, and older payload or archive normalization.
- `Admin -> Media` keeps the main list title `Media` and the listing card title `Media Library`.
- On the Media list, the title link and pencil icon both open `Edit Media: {title}` and carry a safe Media return URL back to the current filtered list.
- The eye icon remains the large preview modal action from the list.
- The `/webadmin/media/{id}` detail route redirects to `/webadmin/media/{id}/edit` so media detail links land on the canonical edit screen.
- `Edit Media` merges preview, file details, metadata, organization, usage, and danger-zone management into one screen.
- `Copy public URL` now lives in the `File Details` area of the edit screen instead of the list action column.
- Delete follows the standard admin modal plus `Danger Zone` pattern rather than browser confirm markup.

## Site Variables

- Site Variables are stored relationally in `site_variables`.
- They are site-scoped, ordered records with key, label, value, enabled state, and sort order.
- The supported public token syntax is exactly `{{ site.variable_key }}` with optional inner whitespace.
- Keys are normalized to lowercase snake_case and must match the conservative pattern `^[a-z][a-z0-9_]*$`.
- Unknown tokens, disabled variables, invalid keys, and non-site tokens remain unchanged.
- Replacement is text substitution only. It is not a general template engine.
- No conditionals, loops, function calls, env access, config access, PHP execution, nested variables, or arbitrary expressions are supported.
- Replacement happens only during shared public rendering and public search indexing.
- Admin forms and admin previews keep the raw stored token text.
- Variable values are treated as plain text. In HTML-capable public contexts they are inserted as escaped text, not executable markup.

## Page Builder

Editing happens through slots.

Typical flow:

1. Create a page.
2. Assign a layout.
3. Edit the layout's slots.
4. Add and order blocks inside those slots.

Blocks can have parent-child relationships, which allows grouped content structures without collapsing the model into opaque blobs.

## Blocks

Blocks are the reusable editorial units of the CMS.

- Shared data holds structure, placement, assets, and operational settings.
- Translated data holds user-facing text for each locale.
- User-facing content is not stored in arbitrary JSON blobs.

System navigation blocks follow the same relational rule. `Navbar` keeps the persisted `sticky-navbar` handle for compatibility, but it now acts as a primitive container block: it renders only `nav.wb-navbar` plus nested child blocks. It does not render brand markup, menu links, header actions, or a forced container by itself.

Navbar composition is relational rather than JSON-driven:

- `Navbar` owns only wrapper semantics and a shared `Position` setting.
- `Container` owns width constraint. Legacy containers still default to stacked flow for compatibility, but `Flow = None` makes Container layout-neutral so child layout blocks control composition.
- `Cluster` is the horizontal or grouped layout primitive. Its settings control width, justification, cross-axis alignment, wrapping, and gap.
- `Stack` remains the vertical flow primitive.
- `Navbar Brand` owns logo and brand text.
- `Navbar Navigation` owns menu selection and renders site `navigation_items` using WebBlocks UI navbar classes. On narrow screens it also renders an accessible burger toggle that opens the same menu through the WebBlocks UI dropdown behavior while leaving brand and header actions in the main navbar row.
- `Header Actions`, `Search Form`, `Container`, and other compatible blocks can be placed inside `Navbar` as children when needed.
- `Navbar Brand` and `Navbar Navigation` must live somewhere inside the `Navbar` ancestor tree, but they do not need to be direct children of the root `Navbar` block.
- `Navbar Brand` supports logo-only usage when a logo image exists. When visible title text is empty, an explicit accessible label or the resolved site label provides the safe fallback name.

Recommended composition:

- `Navbar`
- `Container (Flow: None)`
- `Cluster (Width: Full, Justify: Between, Align: Center, Wrap: Nowrap)`
- `Navbar Brand`
- `Cluster (Justify: End, Align: Center, Wrap: Nowrap)`
- `Navbar Navigation`
- `Header Actions`

Use `Container` for width, `Cluster` for horizontal distribution, and `Stack` for vertical flow. `Navbar` remains only the `nav` wrapper and does not add CMS-specific layout helpers on its own.

This keeps header composition explicit, reusable, and aligned with WebBlocks UI instead of duplicating a CMS-only navbar design surface.

This keeps content ownership clear across multisite, localization, revisions, and public rendering.

## Search

Search V1 is derived public infrastructure, not project-layer website logic.

- Search indexes published public pages only.
- Search rows are scoped by site and locale.
- Search uses page translations as the source of truth for title and public URL metadata.
- Search content is built from page-owned published blocks plus compatible Shared Slot blocks resolved in the context of the consuming page.
- Hidden Shared Slot source pages are excluded from public route resolution and from standalone search results.
- The first version stores derived rows in the database table `public_search_index` and uses conservative SQL matching instead of an external search service.
- Search rows are derived runtime data and can be rebuilt safely from CMS content.

## Page Layouts

Public page structure is controlled at the page and slot layer.

- `Page Layout` is the admin-facing name for the page-level outer-shell configuration.
- Page Layouts are now managed install-level records under `Admin -> System -> Page Layouts`.
- The stored compatibility field remains `public_shell` in this phase so existing page data and handles stay valid.
- `default` is the standard public shell.
- `docs` is the documentation-oriented shell for layouts with header, sidebar, and main content regions.
- Built-in `Default Layout` and `Docs Layout` are seeded system layouts with stable handles `default` and `docs`.
- Pages store the selected Page Layout handle, not a separate resolved shell mode.
- Page Layout now owns optional validated public `body_class` tokens.
- Page Layout Slots are relational install-level records attached to a Page Layout and define slot order plus wrapper metadata.
- Slot Types are the reusable catalog for Page Layout Slot assignment.
- Runtime rendering resolves the stored handle to a Page Layout record when available, then resolves body classes and slot wrappers from its managed Page Layout Slots.
- Body Class is intended for layout-specific CSS on the public `<body>`, while Page Layout Slot wrapper ids and classes provide region-level CSS hooks inside that layout.
- Public Navbar sticky behavior comes from the shipped `wb-navbar` primitive. When a default public header slot contains only a Navbar block, CMS promotes the slot wrapper to the `nav.wb-navbar` root so sticky behavior is not constrained by an extra header wrapper.
- Edit Page compares the selected Page Layout's managed Layout Slots against the page's current Page Slots.
- Missing Layout Slots can be added explicitly through `Add Missing Layout Slots`.
- Extra Page Slots are preserved for safety and are reported rather than deleted.
- New pages use active managed Page Layout Slots from the selected Page Layout before falling back to legacy slot arrays.
- Changing Page Layout on normal save does not automatically mutate existing Page Slots.
- V1 custom Page Layouts may use custom handles, but they still reuse the existing `default` or `docs` shell behavior conservatively for compatibility.
- Slot name determines the semantic public wrapper role for that region.
- Slot wrappers are resolved automatically from managed Page Layout Slots when available, with legacy fallback definitions for built-in layouts. Unknown slots use the safe default `div` wrapper.
- Advanced trusted layout HTML exists only for wrapper-adjacent structural markup. It is not a general scripting surface and must not be used for scripts.
- Header slots are layout-neutral by default and do not force `wb-stack` around their block trees.
- Main slots may still own stacked rhythm through their shell partial when that presentation is intentional.
- Header-to-main spacing belongs to the public shell wrapper, not to `Navbar` or other individual header blocks. Default-shell header and main wrappers own that rhythm so the first content block does not need ad hoc top margins.
- Sticky navbar behavior should stay owned by the shipped WebBlocks UI `.wb-navbar` contract. Page layout and header slot wrappers own page-level structure, but CMS should not inject a parallel navbar sticky class.
- Blocks render content inside those slot wrappers and must not own the outer page shell.
- Page Layout validation allows trusted wrapper snippets for structural gaps, but rejects scripts, event attributes, `javascript:` URLs, `iframe`, `object`, and `embed` content.

For docs-style pages, use the page layout instead of pushing layout responsibility down into individual content blocks. The normal recipe is `Page Layout = Docs Layout` with `Header`, `Sidebar`, and `Main` slots so the shell can map them to the docs navbar, sidebar, and main wrappers automatically.

In V1, Page Layout owns the outer public shell choice, slot names own region wrapper semantics, and blocks own content inside those wrappers.

## Public Metadata

- Public metadata is resolved from the current site and current page translation context, not from Project Identity or editable legacy application name or slogan settings.
- Public page titles default to `Site Label · Page Label`.
- Site label precedence is: site `display_name`, site `seo_title`, site `name`, then a safe CMS fallback.
- Page label precedence is: page translation `seo_title`, then page translation title.
- Description precedence is: page translation `seo_description`, site `seo_description`, then no description tag.
- Keywords precedence is: page translation `seo_keywords`, site `seo_keywords`, then no keywords tag.
- Open Graph title precedence is: page translation `og_title`, otherwise the same site-first public title pattern.
- Open Graph description precedence is: page translation `og_description`, page translation `seo_description`, site `seo_description`.
- Open Graph image precedence is: page translation `og_image_media_id`, site `social_image_media_id`, then no `og:image` tag.
- Site favicon media is unchanged and still stays site-level only in this phase.
- Multisite metadata stays host-aware through the resolved public site; the primary site is not used blindly when another site matches the current host.

## Search Context

- Public search stays scoped by resolved site and locale.
- The search modal copy can include the resolved site label so users can see which site is being searched.
- Public search copy uses Site Identity, not Project Identity.

## Shared Slots

Shared Slots are a slot-content ownership layer that sits under the existing page layout model.

- Page layout still owns which slots are available.
- Page shell plus slot name still own the public wrappers for each slot.
- Each page slot source can be `page`, `shared_slot`, or `disabled`.
- `page` means the slot uses blocks owned directly by the page, which remains the current behavior.
- `shared_slot` means the slot references a site-scoped reusable Shared Slot block tree that is rendered dynamically at public runtime.
- `disabled` means the slot wrapper still exists, but no blocks are rendered inside it.
- Shared Slots are site-scoped reusable slot block trees, not copy-based templates.
- Shared Slot references are valid only within the same site. Cross-site page operations must remap to a compatible target-site Shared Slot or fall back to a safe blocked or disabled result depending on the operation and explicit user choice.
- Shared Slots do not own page shells or slot wrappers. The page shell still owns the outer shell, and the consuming page slot still owns the wrapper for `header`, `main`, `sidebar`, or other slot regions.
- A valid Shared Slot contributes only the block tree rendered inside that existing page slot wrapper.
- Public Shared Slot rendering is conservative:
- Cross-site shared slot references render nothing.
- Shared Slot admin screens describe the optional `SharedSlot.public_shell` constraint as `Page Layout`, but the stored field name remains `public_shell` for backward compatibility in this release.
- If `SharedSlot.public_shell` is set, it must exactly match the consuming page layout handle.
- If `SharedSlot.slot_name` is set, it must match the consuming page slot name.
- Null or empty `public_shell` and `slot_name` act as generic matches.
- A custom Page Layout handle that maps to `shell_type = docs` does not automatically match a Shared Slot constrained to `docs` in V1.

Current scope now includes foundation, public rendering, site-scoped admin management, page slot source assignment, and site portability support for Shared Slots.

- Shared Slots have their own admin listing and metadata forms under the site-content area of the admin.
- Shared Slot block trees are edited through a dedicated Shared Slot block editor that reuses the current block authoring model instead of copying content into consuming page slots.
- Shared Slot blocks remain real `blocks` records connected through `shared_slot_blocks`, with translated text staying in the existing translation tables.
- Deleting a Shared Slot is blocked while any page slot still references it.
- The page editor now exposes a per-slot source selector with three supported modes:
- `Page Content`: `page_slots.source_type = page` and `shared_slot_id = null`.
- `Shared Slot`: `page_slots.source_type = shared_slot` and `shared_slot_id` points to a compatible active Shared Slot from the same site.
- `Disabled`: `page_slots.source_type = disabled` and `shared_slot_id = null`.
- Changing a slot source does not delete or detach the existing page-owned block tree for that slot. If an editor switches a slot to `shared_slot` or `disabled`, the page-owned blocks remain attached so switching back to `Page Content` restores the prior page-specific content.
- Adding missing Layout Slots follows the same safety principle: it creates only new Page Slots and does not alter existing Shared Slot-backed slots, Disabled slots, or page-owned block trees.
- The page editor filters Shared Slot choices conservatively. Only active Shared Slots from the same site appear, and optional Shared Slot `public_shell` and `slot_name` constraints must match the consuming page shell and slot name.
- Shared Slot admin forms now label that optional `public_shell` constraint as `Page Layout` so the user-facing term matches the Page editor and Page Layout management screens.
- Site export/import and clone still transfer page-level `public_shell` handles as part of page configuration. Install-level Page Layout definitions remain local to the install in V1, so target installs should define any custom handles they expect to render without fallback.
- Install-level Page Layout Slot definitions also remain local to the install in V1.
- Runtime public rendering guards remain in place even after write-time validation, so invalid, stale, cross-site, inactive, or incompatible assignments still render no shared content publicly.
- Shared Slots now participate in site export/import and site clone. Their metadata, hidden-source-page block trees, translations, nested ordering, and media references are transferred as first-class site content. Consuming page slots keep `shared_slot` references by Shared Slot handle during export and are remapped to target-site Shared Slots during import and clone.
- Hidden Shared Slot source pages remain internal. They are excluded from normal page export payloads, ordinary page listings, and public routing even though their block records still back the Shared Slot editor and portability flows.
- Shared Slots now have their own revision history and restore flow that is intentionally separate from page revisions.
- Shared Slot revisions capture Shared Slot metadata plus the reusable Shared Slot block tree behind the hidden internal source page.
- Restoring a Shared Slot revision restores the Shared Slot in place. The Shared Slot id stays stable, existing `page_slots.shared_slot_id` references remain intact, and the restored content immediately affects every page that references that Shared Slot.
- Shared Slot revisions do not treat page revisions as authoritative for Shared Slot content, and page revisions do not pretend to capture Shared Slot block trees.

## Page And Block Publishing

Page workflow publishing and block publishing are separate by default. Publishing a page record makes the page routable when its locale/path rules match, but it does not silently publish draft or in-review blocks inside that page. This lets editors and reviewers publish the page shell while holding selected blocks for later review.

When a user explicitly chooses to include page-owned blocks, WebBlocks CMS publishes only blocks owned by the page's non-shared page slots. Nested child blocks under those page-owned slots are included. Shared Slot-backed slots are excluded; their reusable block trees must be reviewed and published through Shared Slot-specific workflows.

The Internal Content API follows the same rule. `POST /webadmin/api/pages/{page}/publish` defaults to `include_page_owned_blocks: false`, requires `content.publish`, and excludes Shared Slots. AI/operator tools must opt into `include_page_owned_blocks: true` only with explicit user approval.

## Site Promotion Boundary

- Site Promotion works on site-owned content, not on the whole install.
- It can update safe site identity fields, locale assignments, site variables, pages, page translations, slots, blocks, Shared Slots, navigation, page assets, and optional package file assets.
- It preserves install-level and live-runtime data such as users, sessions, backups, update history, contact submissions, visitor reporting, domains, environment settings, and secrets.
- It does not treat `public_search_index` as portable content. Search rows are rebuilt from promoted content after apply.
- It is a controlled package workflow, not raw database replication.

## Project Boundary

WebBlocks CMS core contains reusable CMS functionality.

- Keep install-specific code that must survive CMS updates in `project/`.
- Keep site-specific commands, views, routes, config, import helpers, and migration workflows out of core when they are not reusable product features.
- Treat `project/` as the update-safe extension boundary for one install.
- Keep reusable product behavior such as blocks, layouts, admin UI, public renderers, workflow, backup/restore, export/import, and generic project-layer support in core.
- Keep site migration/import helpers and install-specific content sync tooling in `project/`.
- Fast website migration bridges can live in `project/` when they are intentionally temporary and website-specific. The WebBlocks UI remaining-docs importer is one example: it stores fetched docs `<main>` fragments as trusted HTML in project payload form without teaching CMS core about that website.
- That kind of bridge should preserve existing curated CMS content, keep shell or navigation behavior owned by the CMS, and make the temporary trusted HTML boundary explicit so later refinement into first-class blocks stays possible.
- Core release packages do not ship `project/` content.

See `../DEVELOPMENT.md` for the full development and release workflow.
