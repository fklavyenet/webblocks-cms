# AI Development Rules

## General

- Follow Laravel conventions unless this file says otherwise.
- Use two spaces for indentation.
- PHP files in this repository use 2-space indentation.
- Use DDEV commands by default.
- Use `ddev artisan`, not `php artisan`.
- Do not include manual verification/check steps inside implementation commands.
- After meaningful feature or behavior changes, update `README.md`, `CHANGELOG.md`, and relevant docs.
- Keep implementation prompts and project commands in English.
- Prefer focused, reviewable changes over broad rewrites.

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
- Do not assume the `/admin` path belongs to CMS.
- In coexistence designs, `/webadmin` is the canonical CMS admin prefix and `/cms` remains static assets only.
- Preserve the host `/login` model; do not introduce a separate mandatory CMS login system.
- Treat CMS authorization as CMS membership/role authorization.
- Do not automatically equate host product admin status with CMS admin status.
- Do not design installer, register, or invite flows that create duplicate users for the same email.
- Keep current implementation and target direction separate when documenting coexistence changes.

## Admin UI

- Use WebBlocks UI classes and existing CMS admin patterns.
- Prefer shared admin partials and established listing/modal/card/action patterns.
- Use the shared compact listing filter toolbar when a listing has real filters.
- Keep create/upload/add actions in the relevant listing card header when that is the established screen pattern.
- Avoid large inline Blade scripts.
- Named CMS admin JavaScript belongs under `public/cms/js/admin/`.

## Public Rendering

- Public block renderers should prefer shipped `wb-*` classes.
- Do not invent one-off public CSS classes unless the gap is documented.
- Public block renderers must not emit inline scripts.
- CMS-owned public JS belongs under `public/cms/js/`.
- Site/page override assets belong under `public/site/{site_handle}/...`.
- Root-owning blocks should own their renderer root instead of receiving unnecessary wrapper markup.

## Tests

- Add or update focused tests for meaningful behavior changes.
- Run focused tests first when validating.
- Use the risk-based composer scripts in `docs/testing-strategy.md` for routine release validation.
- Prefer `ddev composer test:release-fast` for small package-native hotfixes.
- Use `ddev composer test:package`, `ddev composer test:update`, `ddev composer test:install`, `ddev composer test:artifacts`, or `ddev composer test:admin-smoke` when the changed surface matches that risk area.
- Avoid running the full suite for every small hotfix; run `ddev composer test:full` when risk justifies it or before major releases.
- Use `ddev artisan test --filter=...` for focused tests.
- Use `ddev composer test:full` or `ddev artisan test` for the full suite.
- Do not leave test-created public files, pages, blocks, or database artifacts behind unless they are intentionally part of the test fixture and cleaned up.

## Docs / Release Notes

- Keep `README.md` current for user-facing behavior.
- Keep `CHANGELOG.md` current for meaningful changes.
- Update relevant files under `docs/` when behavior, setup, architecture, or conventions change.
- Do not bump the CMS version or create tags unless explicitly asked to prepare a release.

## Formatting Enforcement

- Laravel Pint remains the formatter for the PHP style rules it supports.
- `scripts/check-php-indentation.php` enforces the project-specific 2-space PHP indentation rule that Pint does not currently catch.
- Verify AI-generated code against the actual written file contents, not only against successful Pint output.
