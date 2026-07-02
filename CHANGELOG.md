# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

## 1.32.210

- Bumped CMS to `1.32.210`.
- Polish the System Updates screen with a stronger status band, icon-led safety/readiness sections, cleaner release metadata, and duplicate release-note suppression.

## 1.32.209

- Bumped CMS to `1.32.209`.
- Redesign the CMS System Updates admin screen into a status-first WebBlocks UI layout with compact safety cards, visible Release and Readiness panels, and a read-only Update History table.

## 1.32.208

- Bumped CMS to `1.32.208`.
- Replace the Recent API Activity token modal table with responsive activity cards so long request paths, capabilities, and user-agent values stay readable without stretching the dialog.

## 1.32.207

- Bumped CMS to `1.32.207`.
- Compact the CMS API Token create/edit capability picker into grouped, collapsible permission sections so expanded plugin and commerce capabilities remain manageable inside cards and modals.

## 1.32.206

- Bumped CMS to `1.32.206`.
- Add the first WebBlocks Commerce plugin foundation with a manual ZIP package skeleton, plugin-owned commerce tables/models, setup-required health checks, MVP architecture documentation, operator guide, product admin screens, read-only order admin screens, secret-safe settings diagnostics, public buy pages, a plugin-owned Commerce Buy Button block, a no-network fake checkout flow, and PayPal hosted checkout/webhook capture handling when configured.
- Add trusted CMS API endpoints for plugin lifecycle actions, WebBlocks Commerce product/order access, and Commerce Buy Button placement discovery so operator tools can install/setup Commerce, create products, and add plugin-owned buy buttons without browser admin automation.

## 1.32.205

- Bumped CMS to `1.32.205`.
- Add privacy-preserving CMS adoption telemetry to update checks with a random local installation ID, opt-out via `WEBBLOCKS_TELEMETRY=false`, and documentation that Publisher downloads and active anonymous installations are separate metrics.

## 1.32.204

- Bumped CMS to `1.32.204`.
- Add a cached async top-navbar update indicator so super admins can see when a CMS update is available without opening `Maintenance -> Update`.

## 1.32.203

- Bumped CMS to `1.32.203`.
- Move admin breadcrumbs into the top navbar across management screens, remove the fixed topbar product identity, and correct Edit Page titles to `Edit Page: #{id} {title}`.
- Add the Product Maturity Assessment documentation entry to the distributed docs index.

## 1.32.202

- Bumped CMS to `1.32.202`.
- Let Pages list View actions open the admin preview for draft/unpublished pages, show the page ID in Edit Page titles, and render Page Details metadata in wrapping tables.

## 1.32.201

- Bumped CMS to `1.32.201`.
- Allow trusted CMS API tokens with `content.read` to fetch the canonical HTML page preview at `/webadmin/pages/{page}/preview`, while preserving browser login behavior for unauthenticated visitors.

## 1.32.200

- Bumped CMS to `1.32.200`.
- Let editors set slash-bearing Page Translation paths from the admin form, so nested public URLs such as `/games/fruit-train` can be corrected without API or database access.
- Clarify Internal Content API guidance and examples so AI/operator tools preserve slash-bearing page paths such as `/games/fruit-train` instead of flattening them.
- Clarify Pages admin block counts by labeling the index count as total nested page-owned blocks and slot counts as top-level slot blocks.
- Clean up remaining admin editorial tests and block editor placeholder examples so canonical page URLs use Page Translation paths such as `/about`, while `/p/...` remains legacy redirect-only.
- Add content-hash cache busting to public site-level `site.css` and `site.js` asset URLs so browser caches refresh after admin or API asset writes.

## 1.32.198

- Bumped CMS to `1.32.198`.
- Expose and document mode-aware `site.css` guidance through the Internal Content API, OpenAPI discovery, content contract, AI guide, and public asset/theme docs so AI/operator tools preserve WebBlocks UI Light/Dark/Auto behavior when editing canonical site CSS.

## 1.32.197

- Bumped CMS to `1.32.197`.
- Let the Pages admin search box match numeric page IDs, so operators can paste IDs such as `27` and find the matching page directly.

## 1.32.196

- Bumped CMS to `1.32.196`.
- Add Internal Content API engagement endpoints so trusted AI/operator tools can read Comments and Rating feedback with `engagement.read` and moderate comment status with `engagement.moderate` without exposing visitor hashes, IP hashes, or user-agent values.
- Reuse the active draft staged update for a published source page when AI/operator tools call `create_staged_update_for_published_page` repeatedly, and update discovery guidance so later revisions use `replace_staged_page_update` instead of creating extra staged pages.

## 1.32.195

- Bumped CMS to `1.32.195`.
- Add system-owned Rating and Comments blocks with separate storage for idempotent star ratings and moderated public comments, plus rate limits, spam quarantine, public renderers, and an Engagement admin review surface.

## 1.32.194

- Bumped CMS to `1.32.194`.
- Harden site asset management so CMS reports writable-readiness for canonical `site.css` and `site.js`, prepares asset directories for new/updated sites, and returns controlled admin/API validation errors instead of raw 500s when hosting permissions block directory creation.

## 1.32.193

- Bumped CMS to `1.32.193`.
- Add Internal Content API endpoints for updating, hiding, reordering, and deleting CMS Navigation menu items, with `navigation.write` for non-destructive mutations and explicit `navigation.delete` for item deletion.

## 1.32.192

- Bumped CMS to `1.32.192`.
- Add Internal Content API read/write endpoints for canonical site `site.css` and `site.js` files, guarded by explicit `site-assets.read` and `site-assets.write` token capabilities and the same checksum/revision protection used by the admin editor.

## 1.32.191

- Bumped CMS to `1.32.191`.
- Add `Sites -> Edit Site -> Assets` for managing canonical physical `site.css` and `site.js` override files without SSH, including checksum conflict protection and pre-overwrite revision snapshots.

## 1.32.190

- Bumped CMS to `1.32.190`.
- Generate native update-server release details from the current `CHANGELOG.md` entry so System Updates shows release-specific highlights and technical metadata instead of repeated publishing workflow placeholder text.

## 1.32.189

- Bumped CMS to `1.32.189`.
- Add Media Library-backed background images for Hero, Section, Card, CTA, and Content Header blocks, including admin pickers, Internal Content API assignment, and CMS-owned public CSS for cover, position, and overlay rendering.

## 1.32.188

- Bumped CMS to `1.32.188`.
- Allow Internal Content API content plans to assign uploaded Media Library records to native media-backed blocks, including image, gallery, file/download, video, and brand logo blocks, while keeping remote media fetch rejected.

## 1.32.187

- Bumped CMS to `1.32.187`.
- Add full Internal Content API Media Library management with explicit `media.upload`, `media.replace`, `media.move`, and `media.delete` capabilities, site branding updates for `favicon_media_id` and social image media, and stricter brand block settings validation so AI/operator tools can manage admin-visible media records, assign public favicon/logo fields normally, and avoid `/cms/brand` file bypasses.

## 1.32.186

- Bumped CMS to `1.32.186` and updated the pinned WebBlocks UI CDN/runtime, icon manifest, and AI contract references to `v2.7.13`.

## 1.32.185

- Add CMS API token activity tracking and a Tokens list history action that shows each token's latest 10 API requests in a WebBlocks UI modal without storing request bodies, query strings, responses, or token values.
- Clarify the CMS admin UI playbook so AI agents prefer a configured WebBlocks UI source checkout when available, but can still work from committed CMS rules and existing CMS WebBlocks UI patterns when ordinary installs do not have the UI repository locally.

## 1.32.184

- Add Internal Content API `media.read` and metadata-only `media.write` capabilities, including safe Media Library metadata updates for `title`, `alt_text`, `caption`, and `description` without upload, delete, replace, or remote fetch support.

## 1.32.183

- Add Internal Content API media discovery and safe existing-block update support so AI/operator tools can assign native brand logo media to structured `navbar-brand` and `sidebar-brand` blocks without Trusted HTML fallbacks.
- Document the discovery-first media and existing-block update workflow, including the extra `shared-slots.write` requirement for Shared Slot source blocks.

## 1.32.182

- Require the Edit Page slot delete action to open a WebBlocks UI confirmation modal and reject direct slot delete requests that do not include the modal confirmation guard.

## 1.32.181

- Tighten Internal Content API content plan validation so AI/operator tools must use nested `children`, cannot submit locale-keyed block translations or flat `id`/`parent_id` block relationships, cannot create childless wrapper blocks, and receive a `renderability` summary in validate/apply responses.
- Add Internal Content API support for safe site public theme preset updates, missing page layout slot sync, and explicit Shared Slot block publishing so AI/operator tools can prepare reusable header navigation without browser-admin workarounds.

## 1.32.180

- Show page IDs in the Pages admin listing so operators can identify records directly from the first data column.

## 1.32.179

- Improve Internal Content API staged update guidance by exposing promote workflow metadata, returning staged-page promote actions, and rejecting staged update page publish calls with safe promote instructions.

## 1.32.178

- Add CMS-owned public theme preset token styling so `canvas`, `atlas`, `pulse`, `prism`, `graphite`, and `horizon` visibly affect public page backgrounds, surfaces, text, links, buttons, badges, and public icon tone roles through `data-wb-public-theme`.

## 1.32.177

- Hide public Header Actions preset/accent controls so site-level Public Theme presets remain the single public theme selector while search and safe color mode controls continue to render.

## 1.32.176

- Fix Public Theme preset saving so `Sites -> Edit Site -> Theme` posts lowercase preset values, accepts accidental title-case input, and keeps public body hook previews lowercase.

## 1.32.175

- Add site-scoped Public Theme selection under `Sites -> Edit Site -> Theme`, save supported presets with existing-install update migration coverage, and render `data-wb-public-theme="{preset}"` on public bodies with `canvas` fallback.
- Clarify repo-local AI skill boundaries so live installed-site `Update Now` actions and live browser smoke/visual checks remain operator-owned unless explicitly requested.

## 1.32.174

- Ensure public icon tone CSS is shipped and served from the live CMS public asset path so selected icon tones visibly affect rendered icons.
- Add repo-local AI skill playbooks for CMS release, admin UI, Internal Content API page building, and Docs to CMS sync workflows.

## 1.32.173

- Fix public icon tone CSS so icon-enabled content blocks visually apply selected icon tones in public rendering.

## 1.32.172

- Add public icon tone support for icon-enabled content blocks using controlled visual tone settings and safe public CSS classes.
- Document planned site-level public theme presets and block visual tones.

## 1.32.171

- Add Internal Content API staged updates for published pages, letting operator tools create a draft staging copy, replace page-owned managed slots, preview it, and explicitly promote it back to the published source page with `content.publish` while preserving public page status/path and excluding Shared Slot cascades.

## 1.32.170

- Add reusable product-level public icon and badge support for selected content blocks using active `System -> Icons` catalog slugs and safe `wb-icon wb-icon-{slug}` public output.

## 1.32.169

- Add stable `wb-page-{slug}` public body classes, including `wb-page-home` for root homepage rendering, so site-level CSS can target individual pages safely.

## 1.32.168

- Fix large Internal Content API page publish requests so `include_page_owned_blocks` publishes page-owned draft or in-review block trees in bulk while still excluding Shared Slot content.

## 1.32.167

- Make Page Translation `path` the canonical public page URL, including slash-bearing paths such as `/docs/internal-content-api`, while keeping `/p/...` as a legacy redirect.
- Fix Internal Content API draft create/replace path handling so `page.path` and `expected_path` use canonical public paths instead of slug-normalized `/p` paths.
- Add allowlisted `source_sync` page metadata persistence and readback for trusted AI/operator docs sync workflows.
- Preserve `/webadmin`, `/webadmin/api`, `/cms`, `/search`, `/search.json`, `/contact-messages`, `/install`, and host auth route ownership ahead of public page matching.

## 1.32.166

- Fix the CMS API token one-time `.env` example so local AI/operator tools use `WEBBLOCKS_CMS_API_URL=https://example.com/webadmin/api` instead of the public site root.
- Seed docs-only Docs to CMS source metadata front matter for the initial public documentation batch.
- Expand docs-only Docs to CMS source metadata coverage across remaining public product documentation under `docs/`.

## 1.32.165

- Keep Contact Messages notification status cells compact by removing list-level explanatory text and showing failed notification summaries only through a WebBlocks UI tooltip help icon.

## 1.32.164

- Fix Contact Message email notification status so `Sent` is recorded only after a real configured mail transport accepts the send call; disabled notifications, missing recipients, incomplete SMTP, and `log`/`array`/`null` mailers now show skipped or not configured instead of sent.
- Add Contact Message notification source/reason metadata, admin list/detail guidance, and package update migration coverage for existing installs.
- Add installation and setup guidance for Contact Form mail delivery, recipient fallback order, storage-vs-notification behavior, smoke testing, and secret-safe diagnostics.
- Expand the Markdown Docs to CMS Sync documentation into an operational runbook for changed `docs/` Markdown files, including source matching, batch behavior, stop conditions, report formats, and safe draft apply rules.
- Document the internal CMS admin/auth UI standards under `ai/standards` and move internal audit notes out of public `docs/`.
- Document the Internal Content API route contract for token-protected AI/operator CMS content operations.
- Exclude internal AI audit/worklog paths from release exports while keeping public documentation focused on user and developer guidance.
- Document public block renderer markup and WebBlocks UI class output for shipped core block renderers.
- Document the target-site agnostic Markdown documentation to source-linked CMS page publication model for future AI/operator workflows.
- Add a feature inventory documentation page that maps CMS features to admin/public/API surfaces and documentation gaps.
- Add user-facing Contact Forms and Contact Messages documentation covering native form blocks, submissions, spam handling, notifications, diagnostics, and AI/operator usage.

## 1.32.163

- Hide the native Contact Form anti-spam check field with CMS-owned public CSS and replace the old public `website` field contract with a renderer-generated signed `form_check_{token}` field.
- Keep filled check-field and too-fast Contact Form submissions on the normal generic success path without storing Contact Messages or sending notifications.
- Update Contact Form docs and Internal Content API metadata so AI/operator tools use the native `contact_form` block instead of raw forms or `mailto:` workarounds.

## 1.32.162

- Add optional page-owned block publishing on Edit Page -> Overview while keeping normal page publish page-only by default.
- Add `content.publish` API endpoints for page publish and page-owned block publish operations, with Shared Slot content excluded and reported separately.
- Document that page publishing and block publishing are separate unless `include_page_owned_blocks` is explicitly selected.

## 1.32.161

- Complete native Contact Form discovery for AI/operator tools by documenting `contact_form` in block contracts, exposing its safe submit/spam/storage/notification contract through `/webadmin/api/content-contract`, and updating the contact-page API example to use the native block instead of `mailto:` or Trusted HTML workarounds.
- Align fresh install Contact Message notification defaults with the package migration path.

## 1.32.160

- Add `replace_existing_draft_page` Internal Content API plans for draft-only page-owned slot replacement with path or updated-at safety guards, transaction-scoped block replacement, and page revision audit snapshots.
- Include safe CMS/product version metadata in authenticated API discovery.

## 1.32.159

- Extend the Internal Content API CSRF bypass to the package CSRF middleware exception list so all `/webadmin/api` Bearer-token write routes honor the JSON-only contract in package consumers.

## 1.32.158

- Fix CMS Content API write routes so `POST /webadmin/api/content/validate` and `POST /webadmin/api/content/apply` honor the JSON-only Bearer token contract without CSRF 419 responses.

## 1.32.157

- Add an Edit action for CMS API tokens so super admins can update token names and capabilities without exposing or rotating token secrets.

## 1.32.156

- Add capability selection to CMS API token creation, keep normal page-building permissions selected by default, and leave publish/delete permissions opt-in for trusted operator tools.
- Remove the personal example from the API token name placeholder and show a safe capability summary on existing token rows.

## 1.32.155

- Add a separate destructive Delete action for CMS API tokens so super admins can permanently remove active or revoked token records while keeping Revoke as an audit-preserving disable action.

## 1.32.154

- Add discovery-first CMS Content API bootstrap, OpenAPI, AI guide, and examples endpoints for external AI/operator tools using Bearer tokens.
- Harden content validate/apply as JSON-only Bearer API writes with capability checks, CSRF-free API behavior, guidance links, and safe token audit metadata.
- Add API Tokens screen usage guidance and document the discovery-first workflow.

## 1.32.153

- Fix package-native System Update recovery for old repo-shaped Composer vendor installs by normalizing Composer installed package metadata before autoload regeneration and failing safely if stale nested WebBlocks CMS paths remain.

## 1.32.152

- Normalize old repo-shaped Composer vendor installs to the flat canonical package root during System Update, including WebBlocks CMS autoload metadata.

## 1.32.151

- Apply package-native System Updates to the canonical Composer package root at `vendor/fklavyenet/webblocks-cms` instead of maintaining `packages/webblocks-cms` as a second updated runtime copy.

## 1.32.150

- Ensure installed package docs ship the AI Page Building Guide under `vendor/fklavyenet/webblocks-cms/docs` for downstream AI/operator tools.

## 1.32.149

- Add an AI Page Building Guide and token-protected `/webadmin/api/content-contract` discovery endpoint for safe generic draft page creation by trusted AI/operator tools.

## 1.32.148

- Add authenticated Edit Page preview at `/webadmin/pages/{page}/preview` for draft, in-review, and published pages, with noindex protection, a preview banner, site-scoped admin authorization, no visitor-report logging, and public routes still limited to published content.
- Document the CMS admin resource/action URL standard so browser admin member actions stay under `/webadmin/{resource}/{id}/{action}` and JSON APIs stay under `/webadmin/api`.

## 1.32.147

- Ensure package-native System Updates create the CMS API token table for existing installs and show controlled setup guidance instead of a 500 when that schema is missing.

## 1.32.146

- Add super-admin CMS API token management under `System -> API Tokens` with database-hashed tokens, one-time token display, revocation, and database-backed `/webadmin/api` bearer authentication.

## 1.32.145

- Add Phase 2A Internal Content API foundations for safe navigation menu items, Shared Slots, Shared Slot blocks, and compatible page slot Shared Slot assignment.
- Extend Internal Content API content plans with optional `navigation_menus`, `shared_slots`, and `page_slot_shared_slots` sections while preserving draft-first, non-destructive apply behavior.

## 1.32.144

- Add the Phase 1 Internal Content API under `/webadmin/api` with token-protected JSON discovery endpoints and draft-only content plan validate/apply operations.
- Fix Page Converter section output so detected `<section>` fragments create Section container blocks with meaningful child blocks instead of empty wrappers.

## 1.32.143

- Fix CMS auth coexistence by moving package login/logout usage to CMS-owned `webblocks.auth.*` route names so host-owned `login` routes no longer steal `/webadmin` redirects or form posts.
- Add a regression for a competing QuizTem-style `/quiztem/login` route while preserving CMS `/webadmin/login`, password reset, and logout paths.

## 1.32.142

- Harden package `webblocks:install` for existing Laravel hosts by detecting partial CMS schemas before fresh migrations and reporting CMS tables, row counts, migration rows, and known foreign key conflicts.
- Add explicit `webblocks:install --repair-partial` recovery for empty partial CMS tables, while refusing automatic repair for any non-empty CMS table.
- Guard the package fresh-install schema creation and avoid the historical `system_update_runs_triggered_by_user_id_foreign` MySQL constraint-name collision.

## 1.32.141

- Release the Page Converter MVP under Pages with paste/upload HTML analysis, signed conversion plan review, and verified draft-only page creation.
- Support conservative conversion into text/content blocks, code/table/quote/html fallback, button links, callout/alert, section, content header, hero, CTA, explicit card regions/children, and clear `<details>` accordions.
- Keep the MVP non-destructive: no remote fetching, crawling, media import, navigation/shared-slot creation, overwrite, publish, ZIP, or batch import behavior.

## 1.32.140

- Align Export / Import row action cells with the standard compact WebBlocks UI admin table action pattern.

## 1.32.139

- Fix MySQL/MariaDB backup dumps so option-file-sensitive database passwords remain intact when the pre-update backup runs `mysqldump`.

## 1.32.138

- Move the Site Transfer import review form above package counts and show package counts in a compact admin table so validated packages can be acted on sooner.

## 1.32.137

- Remove old Publisher/update server, product, and channel environment overrides from CMS update checks and maintainer publishing.
- Make package-owned `ReleaseDefaults` the only source for the release server, product key, channel, and update/publish API paths.
- Keep `WEBBLOCKS_PUBLISHER_TOKEN` as the only normal publish environment secret.

## 1.32.136

- Move CMS update and publisher identity defaults into package product code so installed sites no longer need normal update server, product, or channel environment keys.
- Keep legacy update and publisher identity overrides available for the transition release, while maintainer publishing normally only requires `WEBBLOCKS_PUBLISHER_TOKEN`.

## 1.32.135

- Add a transition verification release after moving CMS update publishing and installed update consumption to `publisher.webblocksui.com`.
- No functional runtime changes beyond release/version metadata.

## 1.32.134

- Move installed CMS update checks to `publisher.webblocksui.com` so publishing and update consumption use the same canonical Publisher service.
- Keep maintainers publishing to `https://publisher.webblocksui.com/api/updates/publish` while installed sites read latest metadata from `https://publisher.webblocksui.com/api/updates/latest`.

## 1.32.133

- Standardize CMS release publishing on `publisher.webblocksui.com` as the canonical Publisher endpoint.
- Keep the canonical Publisher environment key set as the only supported publish configuration during the transition.

## 1.32.132

- Keep the base admin layout JavaScript minimal by loading only pinned WebBlocks UI and shared CMS admin core globally, with picker, builder, rich-text, gallery, media-copy, page-assets, and password-toggle behavior loaded from page-scoped static admin assets.
