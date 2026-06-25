# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

- Seed docs-only Docs to CMS source metadata front matter for the initial public documentation batch.

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
