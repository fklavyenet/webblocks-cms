# WebBlocks CMS Release Skill

Use this skill when preparing an actual WebBlocks CMS product release, package publication, or stable update-server publish. Do not use it for ordinary source edits unless the user explicitly asks to release, tag, publish, or prepare a package.

## Boundaries

- Release packages are product packages. They must not include install-specific `project/` content, site content, local environment files, secrets, logs, or temporary deployment artifacts.
- Installed CMS working copies are update consumers. They must not publish upstream or act as maintainer release authorities.
- Do not use GitHub Actions, GitHub releases, `gh`, or GitHub asset URLs for CMS update publishing.
- Publisher is canonical: `publisher.webblocksui.com`, product `webblocks-cms`, channel `stable`.
- Maintainer publishing normally needs only `WEBBLOCKS_PUBLISHER_TOKEN`; never print, log, or commit the token.

## Release Flow

1. Confirm the user requested a real release or publish flow.
2. Review changed scope and update `CHANGELOG.md` with concise operator-facing notes.
3. Bump `packages/webblocks-cms/src/Support/WebBlocks.php` only for real release tasks.
4. Commit source changes first when appropriate, keeping unrelated local changes out of the release commit.
5. Run risk-based validation before packaging.
6. Run `composer release:prepare`.
7. Run `composer release:publish-update -- --dry-run`.
8. Run `composer release:publish-update`.
9. Verify latest metadata from Publisher, including version, product, channel, artifact URL, and checksum.
10. Create and push a git tag only when the release flow requires it.

## Schema-Changing Releases

For schema-changing releases:

- Update fresh/package consumer schema paths when needed.
- Add a package update migration under `packages/webblocks-cms/database/migrations/updates` for existing package-native installs.
- Add or update update migration regression tests.
- Add graceful missing-schema behavior when runtime code may otherwise raw-500.
- In the final report, explicitly state fresh schema path status, package update migration status, regression test status, and graceful missing-schema status.

## Validation

- Run `composer format:changed`.
- Run focused tests first for the changed surface.
- Use `composer test:release-fast` for small package-native hotfixes.
- Use `composer test:update`, `composer test:install`, `composer test:artifacts`, `composer test:admin-smoke`, or `composer test:full` according to risk.
- Run `git diff --check` before committing or publishing.

## Final Report

Include:

- version
- commit hash
- tag
- artifact path
- SHA-256
- Publisher latest verification
- validation commands and results
- schema/update migration status if relevant
- warnings or limitations
