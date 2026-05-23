# Testing Strategy

WebBlocks CMS release validation should be risk-based. The full suite remains available, but routine hotfixes should start with the smallest focused script that protects the changed surface.

Use DDEV-first commands:

```bash
ddev composer test:release-fast
ddev composer test:package
ddev composer test:update
ddev composer test:full
```

## Composer Scripts

- `ddev composer test:release-fast`: the default fast release gate for small package-native hotfixes. It covers package update migration routing, package extraction, System Updates, the current package update migration repair, package install command behavior, package provider bootstrap, and release artifact boundaries.
- `ddev composer test:package`: package install and package runtime boundary coverage. Use for package provider, package asset, package route/view, wrapper cleanup, Composer metadata, or release ZIP shape changes.
- `ddev composer test:update`: package-native updater coverage. Use for System Updates, update server client parsing, migration runner behavior, installed-version persistence, backup readiness, or package update migrations.
- `ddev composer test:install`: fresh Composer consumer install coverage. Use for `webblocks:install`, first-admin setup, package admin route smoke, consumer auth, and fresh install backup behavior.
- `ddev composer test:artifacts`: release package archive and source checkout boundary coverage. Use whenever `.gitattributes`, package public assets, package composer metadata, updater support files, or GitHub release workflow package shape changes.
- `ddev composer test:admin-smoke`: lightweight admin route/layout smoke coverage. Use for shared admin layout, sidebar, fixed CMS brand shell, and package admin partial changes.
- `ddev composer test:full`: the full test suite. Use before major releases, large cross-cutting changes, or when focused results point to broader risk.

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

## Package-Install-Critical

Representative tests:

- `tests/Feature/PackageConsumerInstallCommandTest.php`
- `tests/Feature/PackageConsumerInstallAuthTest.php`
- `tests/Feature/PackageFreshInstallMigrationTest.php`
- `tests/Feature/PackageServiceProviderBootstrapTest.php`

Run with `ddev composer test:install` for fresh Composer consumer install, package asset publish, package route bootstrap, and first-admin setup changes. Use `ddev composer test:package` when the change also touches package source authority or release artifact shape.

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

Run with `ddev composer test:artifacts` for release package content, package-rooted ZIP layout, `public/cms` asset inclusion, `project/` exclusion, GitHub workflow archive behavior, and package wrapper cleanup boundaries.

## Admin-Smoke

Representative tests:

- `tests/Feature/Admin/AdminDashboardRouteTest.php`
- `tests/Feature/Admin/AdminSidebarNavigationTest.php`
- `tests/Feature/Admin/SharedAdminPartialPackageViewTest.php`
- `tests/Feature/PackageRuntimeSlicesTest.php`
- `tests/Feature/PackageConsumerInstallAuthTest.php`

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

## Legacy / Root-Managed Compatibility

Representative tests:

- `tests/Unit/System/Updates/LegacyRootManagedUpdateCompatibilityTest.php`
- root-managed bridge assertions inside `tests/Feature/ReleasePackageBoundaryTest.php`
- bridge archive behavior covered by `scripts/build-root-managed-bridge-archive.sh`

These protect the historical bridge path. Since there are no remaining old root-managed installs to support, keep them out of routine package-native hotfix gates unless changing bridge scripts, old root wrappers, or update compatibility documentation.

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
| `tests/Unit/System/Updates/LegacyRootManagedUpdateCompatibilityTest.php` | Protects old `1.31.53` root-managed updater behavior. No old root-managed installs remain to support. | Package-native update coverage in `SystemUpdatesTest`, `UpdateMigrationRunnerTest`, `UpdatePackageExtractorTest`, and `ReleasePackageBoundaryTest`. | Move to legacy-only gate now; consider deleting after one more package-native release cycle if bridge support is formally retired. |
| Bridge assertions in `tests/Feature/ReleasePackageBoundaryTest.php` | The release artifact test mixes current package-rooted boundaries with root-managed bridge script assertions. | Package-rooted release shape is covered by the same class; bridge behavior is historical. | Split into a dedicated legacy bridge test later, or move bridge assertions behind a `legacy` group if groups are introduced. |
| Broad `--filter=Package` release validation habit | Runs unrelated package-named tests and repeats expensive archive/install checks. | `ddev composer test:package` names the intended package gate explicitly. | Replace routine use with script-based gates. |
| `tests/Feature/PackageConsumerInstallAuthTest.php` plus `PackageConsumerInstallCommandTest.php` | Both exercise fresh install state; one focuses command baseline, the other route/auth smoke. | Together they remain useful for install releases. | Keep both for now; consider shrinking route matrix if install validation becomes too slow. |
| `tests/Feature/PackageServiceProviderBootstrapTest.php` | Broad package source-authority assertions can overlap with wrapper cleanup and package status tests. | `PackageWrapperCleanupTest` and `PackageStatusCommandTest` cover narrower slices. | Keep as release-critical while package transition is recent; revisit after package-native stabilizes. |

## PHPUnit Groups

The suite does not currently use PHPUnit groups. Adding groups would require touching many test files and is not necessary for this pass because composer scripts now provide stable focused entry points. A later cleanup can add groups such as `package`, `update`, `release`, `artifact`, `admin-smoke`, `slow`, and `legacy` after the script boundaries have settled.
