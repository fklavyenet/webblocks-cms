# AI Development Rules

## General

- Follow Laravel conventions unless this file says otherwise.
- Use two spaces for indentation.
- PHP files in this repository use 2-space indentation.
- Use native local commands by default.
- Use `php artisan` for Artisan commands.
- Native local development targets trusted HTTPS only, and local domains must use `.test` rather than `.local`.
- Do not include manual verification/check steps inside implementation commands.
- After meaningful feature or behavior changes, update `README.md`, `CHANGELOG.md`, and relevant docs.
- Keep implementation prompts and project commands in English.
- Prefer focused, reviewable changes over broad rewrites.

## Repo-local AI Skills

Reusable task playbooks live under `ai/skills/`.

- `ai/skills/webblocks-cms-release.md` covers CMS release, validation, package, and Publisher workflows.
- `ai/skills/webblocks-cms-admin-ui.md` covers CMS admin/auth/dashboard UI pattern-first workflows.
- `ai/skills/cms-internal-content-api-page-building.md` covers trusted `/webadmin/api` page-building workflows.
- `ai/skills/docs-to-cms-sync.md` covers Markdown `docs/` to source-linked CMS documentation page workflows.

Use these skills for matching tasks instead of repeating long instructions in prompts. Keep secrets, tokens, local absolute paths, live logs, temporary deployment details, and environment values out of committed skill files.

## Operator-Owned Live Steps

Do not include live installed-site operations in implementation or release commands unless the user explicitly asks for that exact live operation in the same prompt.

Operator-owned live steps include signing in to a live CMS admin panel, clicking or running `Update Now` on a live installed CMS site, applying a published CMS release to a live installed site, live public-site browser smoke tests, live admin-panel visual checks, and live SSH checks against production installs.

For release work, AI/operator automation may stop after source validation, release artifact creation, Publisher publish, and Publisher latest metadata verification when those steps are explicitly requested. Applying the release from a live CMS installation and testing the live site are manual operator responsibilities by default.

## PHP / Laravel

- Keep controllers thin.
- Prefer services/actions for business logic.
- Prefer Form Request classes for validation where practical.
- Use policies or explicit authorization checks for admin/site-scoped behavior.
- Use transactions for multi-record writes.
- Prefer explicit named classes over large anonymous inline logic.
- Keep migrations reversible where practical.
- Avoid destructive database commands in normal workflows.
- Never suggest or run `migrate:fresh`, `migrate:reset`, `migrate:refresh`, or `db:wipe` unless the user explicitly requests that risk and the CMS destructive-command guard is intentionally bypassed.

## WebBlocks CMS Boundaries

- CMS core contains reusable product behavior.
- Install-specific or website-specific code belongs in `project/`.
- Do not add website-specific import/sync logic to CMS core.
- Release packages must not include `project/`.
- Reusable product/domain extensions belong in plugins with documented package conventions: kebab-case handles, handle-prefixed permissions and commands, `/webadmin/plugins/{plugin-handle}` admin routes, plugin-owned settings namespaces, plugin-owned table prefixes, compatibility metadata, and inert disabled/incompatible behavior.
- WebBlocks UI Manager CDN publish behavior is first-party plugin-owned. Do not move release/CDN validation, manifest writing, artifact publishing, or publish run history into generic CMS core.
- WebBlocks UI Manager must not be bundled or registered as a default CMS runtime plugin. Keep it in `plugins/webblocks-ui-manager` as a manually installed operator plugin artifact.
- Manual plugin setup/migrations are explicit after enable. Enabled plugin-owned routes must guard schema readiness and show controlled setup-required guidance instead of raw database errors when plugin-owned tables are missing.
- Manual plugin uninstall must be disabled-first, limited to manually uploaded plugins, restricted to the configured plugin install root, and must preserve plugin-owned database tables unless a future explicit cleanup tool is intentionally added.
- Keep WebBlocks UI source changes in the WebBlocks UI project, not inside CMS.
- CMS may consume pinned WebBlocks UI assets, but should not edit UI package source.
- Keep WebBlocks CMS free of Vite, Laravel Vite plugin, Tailwind, npm, Node build-chain, package lock, `public/build`, and hot-file runtime requirements. CMS-owned assets ship as static files under `public/cms`, and WebBlocks UI is consumed from pinned published assets.
- Do not assume the `/admin` path belongs to CMS.
- In coexistence designs, `/webadmin` is the canonical CMS admin prefix and `/cms` remains static assets only.
- Browser admin resource routes must stay under `/webadmin` using collection `/webadmin/{resource}`, create `/webadmin/{resource}/create`, edit `/webadmin/{resource}/{id}/edit`, member action `/webadmin/{resource}/{id}/{action}`, and collection action `/webadmin/{resource}/{action}`. Page preview is `/webadmin/pages/{page}/preview`; do not add preview routes under `/admin`, `/cms`, `/webadmin/api`, `/webadmin/pages/preview/{page}`, or `/webadmin/preview/pages/{page}`.
- Preserve the host `/login` model; do not introduce a separate mandatory CMS login system.
- Treat CMS authorization as CMS membership/role authorization.
- Do not automatically equate host product admin status with CMS admin status.
- Do not design installer, register, or invite flows that create duplicate users for the same email.
- Keep current implementation and target direction separate when documenting coexistence changes.
- Runtime-required CMS schema changes must support both fresh/package consumer installs and existing package-native System Updates. Fresh schema alone is not enough; add a package update migration under `packages/webblocks-cms/database/migrations/updates` or make the update fail safely before runtime code can raw-500.
- Package-native System Updates must not require ordinary users to SSH and run manual migrations after a successful update. Successful updates should align code, required schema, cache clears, and post-apply version/schema readiness.

## Admin UI

- Use WebBlocks UI classes and existing CMS admin patterns.
- Prefer shared admin partials and established listing/modal/card/action patterns.
- Use the shared compact listing filter toolbar when a listing has real filters.
- Keep create/upload/add actions in the relevant listing card header when that is the established screen pattern.
- Avoid large inline Blade scripts.
- Named CMS admin JavaScript belongs under `public/cms/js/admin/`.

## WebBlocks UI Usage

WebBlocks CMS uses pinned WebBlocks UI assets for admin, auth, dashboard, settings, and related control panel UI.

Before making admin/auth/dashboard UI changes, read the AI contract shipped with the pinned WebBlocks UI version:

`https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@v2.7.13/packages/webblocks/dist/ai/contract.md`

The CMS code pins this version in `WebBlocks::UI_VERSION`. If the pinned WebBlocks UI version changes, update this contract URL in the same work session.

Use WebBlocks UI pattern-first:

- Start from shipped WebBlocks UI patterns before creating local wrappers.
- Preserve WebBlocks UI dashboard/admin contracts on admin and control panel screens.
- Use `wb-dashboard-shell` for admin/dashboard screens.
- Use `wb-auth-shell` for auth screens.
- Use `wb-settings-shell` for settings screens.
- Use `wb-card` as the only generic framed surface.
- Do not invent generic framed surfaces, wrappers, or nouns besides `wb-card`.
- Admin lists must follow the canonical table/action contract: `wb-page-header`, filters before the list card, `section.wb-card`, `.wb-card-body`, `.wb-table-wrap`, explicit `Actions` header, `td.wb-table-actions`, `.wb-action-group`, and pagination in `.wb-card-footer`.
- Use `wb-modal` for destructive confirmation flows instead of browser `confirm()`.
- Use the shared overlay root `#wb-overlay-root` for overlays.
- Use `wb-toast` for transient success/info feedback.
- Use inline `wb-alert` for validation, blocking, and user-correctable errors.
- Add custom CSS or JS only as a last resort after shipped WebBlocks UI composition is proven insufficient.
- Do not add Tailwind, Vite, React, Vue, Livewire, or Inertia UI layers for WebBlocks UI surfaces.

When reviewing UI changes, verify shells, surfaces, tables, forms, overlays, feedback, branding, custom CSS/JS, and tests against the WebBlocks UI checklist.

## Public Rendering

- Public block renderers should prefer shipped `wb-*` classes.
- Do not invent one-off public CSS classes unless the gap is documented.
- Public block renderers must not emit inline scripts.
- CMS-owned public JS belongs under `public/cms/js/`.
- Site/page override assets belong under `public/site/{site_handle}/...`.
- Root-owning blocks should own their renderer root instead of receiving unnecessary wrapper markup.

## Tests

- Add or update focused tests for meaningful behavior changes.
- For new tables or columns used by admin/API/runtime code, add a package update migration regression test similar to `CmsApiTokensUpdateMigrationTest`, and document whether graceful missing-schema behavior is needed or added.
- Run focused tests first when validating.
- Use the risk-based composer scripts in `docs/testing-strategy.md` for routine release validation.
- Prefer `composer test:release-fast` for small package-native hotfixes.
- Use `composer test:package`, `composer test:update`, `composer test:install`, `composer test:artifacts`, or `composer test:admin-smoke` when the changed surface matches that risk area.
- Avoid running the full suite for every small hotfix; run `composer test:full` when risk justifies it or before major releases.
- Use `php artisan test --filter=...` for focused tests.
- Use `composer test:full` or `php artisan test` for the full suite.
- Do not leave test-created public files, pages, blocks, or database artifacts behind unless they are intentionally part of the test fixture and cleaned up.

## Docs / Release Notes

- Keep `README.md` current for user-facing behavior.
- Keep `CHANGELOG.md` current for meaningful changes.
- Update relevant files under `docs/` when behavior, setup, architecture, or conventions change.
- Do not bump the CMS version or create tags unless explicitly asked to prepare a release.

## Changelog Size Policy

- Keep `CHANGELOG.md` as a recent rolling changelog.
- Add only short, user/operator-oriented release notes to `CHANGELOG.md`.
- Do not add long root-cause explanations, validation logs, commit output, or publish output to `CHANGELOG.md`.
- Document permanent behavior or standard changes in the relevant `docs/*.md` file.
- When `CHANGELOG.md` exceeds the latest 10 release window, move older entries to `docs/releases/changelog-{minor}.md`.
- Preserve archive links in `CHANGELOG.md`.

## Formatting Enforcement

- Laravel Pint remains the formatter for the PHP style rules it supports.
- `scripts/check-php-indentation.php` enforces the project-specific 2-space PHP indentation rule that Pint does not currently catch.
- Verify AI-generated code against the actual written file contents, not only against successful Pint output.
