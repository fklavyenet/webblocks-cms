# Testing Strategy

WebBlocks CMS release validation should be risk-based. The full suite remains available, but routine hotfixes should start with the smallest focused script that protects the changed surface.

Use DDEV-first commands:

```bash
ddev composer test:release-fast
ddev composer test:package
ddev composer test:update
ddev composer test:install
ddev composer test:artifacts
ddev composer test:admin-smoke
ddev composer test:full
```

## Composer Scripts

- `ddev composer test:release-fast`: the default aggregate gate for small package-native hotfixes. It intentionally samples package update migration routing, package extraction, current System Updates behavior, package install command behavior, package provider bootstrap, and package-rooted release artifact boundaries.
- `ddev composer test:package`: focused package runtime and source-authority boundary coverage. Use for package provider, package route/view slices, wrapper cleanup, Composer metadata, or package status diagnostics. Use `test:artifacts` instead for release ZIP shape and package public asset archive checks, and `test:install` instead for fresh consumer install behavior.
- `ddev composer test:update`: focused package-native updater coverage. Use for System Updates, update server client parsing, migration runner behavior, installed-version persistence, backup readiness, package extraction, or package update migrations.
- `ddev composer test:install`: focused fresh Composer consumer install coverage. Use for `webblocks:install`, fresh install schema, first-admin setup, consumer auth, and post-install admin/public route smoke.
- `ddev composer test:artifacts`: focused release package archive and source checkout boundary coverage. Use whenever `.gitattributes`, package public assets, package composer metadata, updater support files, or GitHub release workflow package shape changes.
- `ddev composer test:admin-smoke`: lightweight admin route/layout smoke coverage. Use for shared admin layout, sidebar, fixed CMS brand shell, and shared admin partial changes.
- `ddev composer test:legacy-bridge`: manual archival coverage for the retired `1.31.53 -> 1.32.33` root-managed bridge path. Do not use this as a routine package-native release gate.
- `ddev composer test:full`: the full package-native suite, excluding manual legacy bridge tests. Use before major releases, large cross-cutting changes, or when focused results point to broader risk.

## Formatting Checks

Use focused Pint and indentation checks for files touched by a feature or hotfix. The broad `ddev composer format:test` script remains the repository-wide baseline check, but it currently reports pre-existing Pint and indentation drift across unrelated legacy files. Do not mass-reformat the repository as part of focused behavior work; treat a broad formatting-baseline cleanup as a separate maintenance task. When Pint's Laravel defaults and the project-specific 2-space PHP indentation guard disagree on touched legacy files, keep the project 2-space indentation rule and report the focused Pint result honestly.

## Release-Critical

Representative tests:

- `tests/Feature/ReleasePackageBoundaryTest.php`
- `tests/Feature/ComposerPackageMetadataTest.php`
- `tests/Feature/PackageServiceProviderBootstrapTest.php`
- `tests/Feature/PackageConsumerInstallCommandTest.php`
- `tests/Feature/Admin/SystemUpdatesTest.php`
- `tests/Unit/System/Updates/UpdateMigrationRunnerTest.php`
- `tests/Unit/System/Updates/UpdatePackageExtractorTest.php`

Run with `ddev composer test:release-fast` for small package-native hotfixes. Add `ddev composer test:full` when the release changes broad admin/content behavior, schema contracts, import/export portability, or public rendering.

`test:release-fast` is deliberately an aggregate gate, so it overlaps lightly with `test:update`, `test:install`, and `test:artifacts`. The focused scripts below are preferred during implementation when the changed surface is narrower.

## Package-Install-Critical

Representative tests:

- `tests/Feature/PackageConsumerInstallCommandTest.php`
- `tests/Feature/PackageConsumerInstallAuthTest.php`
- `tests/Feature/PackageFreshInstallMigrationTest.php`

Run with `ddev composer test:install` for fresh Composer consumer install, fresh schema, package route bootstrap, first-admin setup, and post-install auth or admin/public smoke changes. Use `ddev composer test:package` when the change also touches package source authority, or `ddev composer test:artifacts` when it touches release artifact shape.

## Package-Update-Critical

Representative tests:

- `tests/Feature/Admin/SystemUpdatesTest.php`
- `tests/Unit/System/Updates/UpdateMigrationRunnerTest.php`
- `tests/Unit/System/Updates/UpdatePackageExtractorTest.php`
- `tests/Unit/System/Updates/UpdateServerClientTest.php`
- `tests/Unit/System/InstalledVersionStoreTest.php`
- `tests/Feature/PageTranslationParentKeyUpdateMigrationTest.php`

Run with `ddev composer test:update` for updater client parsing, package archive extraction, package-native migration execution, backup preflight, installed-version persistence, and package update migration changes.

## Migration / Backup / Restore-Critical

Representative tests:

- `tests/Feature/PageTranslationParentKeyUpdateMigrationTest.php`
- `tests/Feature/Admin/SystemBackupsTest.php`
- `tests/Feature/System/SystemBackupRestoreManagerTest.php`
- `tests/Feature/Console/SystemBackupRestoreCommandTest.php`
- `tests/Unit/System/BackupRestoreArchiveInspectorTest.php`
- `tests/Unit/System/DatabaseRestoreRunnerMysqlTest.php`
- `tests/Unit/System/DatabaseRestoreRunnerSqliteTest.php`

Run the exact migration test class for a migration-only hotfix, then `ddev composer test:update` if the migration runs through System Updates. Add backup and restore classes for archive format, database dump, restore, or pre-update backup changes.

## Release-Artifact-Boundary

Representative tests:

- `tests/Feature/ReleasePackageBoundaryTest.php`
- `tests/Feature/CoreProjectBoundaryTest.php`
- `tests/Feature/PackageWrapperCleanupTest.php`
- `tests/Feature/Console/PackageStatusCommandTest.php`

Run with `ddev composer test:artifacts` for current package-native release package content, package-rooted ZIP layout, `public/cms` asset inclusion, `project/` exclusion, GitHub workflow archive behavior, and package wrapper cleanup boundaries. This gate intentionally does not validate the retired root-managed bridge archive shape.

## Admin-Smoke

Representative tests:

- `tests/Feature/Admin/AdminDashboardRouteTest.php`
- `tests/Feature/Admin/AdminSidebarNavigationTest.php`
- `tests/Feature/Admin/SharedAdminPartialPackageViewTest.php`

Run with `ddev composer test:admin-smoke` for layout, sidebar, shared admin partial, and package admin route bootstrap changes. Add specific admin feature tests when the changed area is deeper than route/layout smoke.

## Public-Rendering / Content

Representative tests:

- `tests/Feature/PublicEditorialBlocksRenderingTest.php`
- `tests/Feature/PublicMediaBlocksTest.php`
- `tests/Feature/PublicSharedSlotRenderingTest.php`
- `tests/Feature/PublicRichContentTest.php`
- `tests/Feature/MediaVisualBlockContractsTest.php`
- `tests/Feature/BlockTypePhaseThreeContractsTest.php`
- `tests/Feature/Integrity/BlockTranslationIntegrityTest.php`
- `tests/Feature/Admin/PageBuilderExperienceTest.php`

Run focused classes or method filters for block rendering, public shell, media, rich text, shared slots, translation integrity, or page builder changes. These are important but often too broad for package/update-only hotfixes.

## Retired Legacy / Root-Managed Bridge

Manual archival tests:

- `tests/Unit/System/Updates/LegacyRootManagedUpdateCompatibilityTest.php`
- bridge archive behavior covered by `scripts/build-root-managed-bridge-archive.sh`

The old root-managed bridge path exists only for historical/manual validation of the completed `1.31.53 -> 1.32.33 bridge -> 1.32.34+ package-rooted` transition. Current project policy is that there are no remaining old root-managed installs to support in routine release validation.

Routine package-native gates exclude the `legacy` PHPUnit group and do not depend on `LegacyRootManagedUpdateCompatibilityTest`. Run `ddev composer test:legacy-bridge` only when intentionally auditing the archived bridge script, changing historical update documentation, or investigating a pre-package-native recovery scenario. Do not block normal package-native releases on this retired bridge coverage.

## Slow / Full-Suite-Only Candidates

These classes are high value but broad, expensive, or content-heavy. Prefer focused method filters during implementation and include them in full-suite or feature-area validation when relevant:

- `tests/Feature/Admin/PageBuilderExperienceTest.php`: very large page builder and content-management workflow coverage.
- `tests/Feature/SiteExportImportTest.php`: full export/import reconstruction and portability flows.
- `tests/Feature/ContactFormModuleTest.php`: broad contact form, mail, settings, and notification behavior.
- `tests/Feature/PublicSharedSlotRenderingTest.php`: shared slot rendering and switching behavior across public pages.
- `tests/Feature/MediaVisualBlockContractsTest.php`: media-heavy block contracts and rendering behavior.
- `tests/Feature/Admin/MediaManagementTest.php`: admin media workflows.
- `tests/Feature/Admin/PageEditorialWorkflowTest.php`: editorial workflow state behavior.

## Cleanup Candidates

| Test file | Why it may be obsolete or duplicated | Newer coverage for same risk | Recommended action |
| --- | --- | --- | --- |
| `tests/Unit/System/Updates/LegacyRootManagedUpdateCompatibilityTest.php` | Protects old `1.31.53` root-managed updater behavior. No old root-managed installs remain to support. | Package-native update coverage in `SystemUpdatesTest`, `UpdateMigrationRunnerTest`, `UpdatePackageExtractorTest`, and `ReleasePackageBoundaryTest`. | Retained only in the `legacy` group and `ddev composer test:legacy-bridge`; excluded from routine package-native gates. |
| Bridge assertions in `tests/Feature/ReleasePackageBoundaryTest.php` | The release artifact test mixed current package-rooted boundaries with root-managed bridge script assertions. | Package-rooted release shape is covered by the same class; bridge behavior is historical. | Moved into `LegacyRootManagedUpdateCompatibilityTest` so `ReleasePackageBoundaryTest` stays package-native. |
| Broad `--filter=Package` release validation habit | Runs unrelated package-named tests and repeats expensive archive/install checks. | `ddev composer test:package` names the intended package gate explicitly. | Replace routine use with script-based gates. |
| `tests/Feature/PackageConsumerInstallAuthTest.php` plus `PackageConsumerInstallCommandTest.php` | Both exercise fresh install state; one focuses command baseline, the other route/auth smoke. | Together they remain useful for install releases, now under `ddev composer test:install`. | Keep both for now; consider shrinking route matrix if install validation becomes too slow. |
| `tests/Feature/PackageServiceProviderBootstrapTest.php` | Broad package source-authority assertions can overlap with wrapper cleanup and package status tests. | `PackageWrapperCleanupTest` and `PackageStatusCommandTest` cover narrower slices. | Keep as release-critical while package transition is recent; revisit after package-native stabilizes. |

## PHPUnit Groups

The suite currently uses a narrow `legacy` PHPUnit group only for retired root-managed bridge coverage. Routine composer scripts exclude that group, while `ddev composer test:legacy-bridge` runs it deliberately. Broader groups such as `package`, `update`, `release`, `artifact`, `admin-smoke`, and `slow` can still be added later after script boundaries have settled.
