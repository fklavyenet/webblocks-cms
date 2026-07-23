# Upgrading and Installation Topologies

Back up the application files, database, environment configuration, storage, and uploads before changing CMS versions or installation topology. Validate the result in a non-production environment. Do not delete host-owned files or data as a blanket migration step.

## 1.41.0

WebBlocks CMS `1.41.0` replaces the two-phase System Update flow with a one-click flow. Upgrading through the in-app updater is supported from any `1.40.x` package-mode install: the release artifact keeps the package shape and file paths the `1.40.x` engine validates, so the old engine can apply `1.41.0` directly.

- **One-click updates with automatic backup and restore.** A single `Update to X` action now downloads, backs up, applies, migrates, and verifies the release in one run. The updater takes an automatic pre-update backup before applying and automatically restores it when the apply fails; a failed-then-restored run is recorded with the new `restored` run status. If the automatic restore also fails, the run is recorded as `failed` with both error trails in the run log.
- **Removed admin endpoints.** The `system/updates/continue`, `system/updates/cancel`, and `system/updates/support-report` admin endpoints are removed along with the two-phase pending state and the super-admin support-report download. Anything that linked to those routes must use the remaining `system/updates` index, check, apply, and indicator routes.
- **Pre-update backup download step removed.** The update screen no longer offers a backup download before applying. Automatic pre-update backups (and all other backups) remain available on the Backups screen for download, restore, and deletion, and via `php artisan system:backup:restore` on the CLI.
- **Server-backup advisory.** The update screen now shows an advisory recommending a fresh backup before a major update, linking to the Backups screen. Take a server-level backup (files plus database) before large upgrades; the automatic pre-update backup covers the database and CMS-managed uploads, not host-owned files outside the CMS.
- **Source-maintained apply mode retired.** `WEBBLOCKS_UPDATES_MIGRATION_STRATEGY` is ignored, and the `installer.migration_strategy` and `installer.pending_cache_ttl_seconds` config keys are removed. In-app updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms` and always run package update migrations from `database/migrations/updates` when present. Source-maintained maintenance checkouts update through git/Composer, not the in-app updater.
- **Trimmed update-screen view contract.** Forks that override `admin/system/updates.blade.php` must adapt: the view now receives `report`, `runs`, `preflight`, and `checkedAt` only. The pending-update, blocker-state, and support-report view data is gone.
- **New language keys for translated forks.** The `updates.*` admin language group is rewritten to the v3 vocabulary and adds the `server_backup_advisory` and `server_backup_advisory_link` keys plus the `statuses.restored` label. Forks that maintain their own admin translations should re-sync the `updates.*` group against the shipped `en`/`de`/`tr` files.

## Package-only repository transition

WebBlocks CMS `1.37.0` is the first release tagged directly from the package-only repository root. Installations on the `1.36.1` compatibility release may upgrade through their documented Composer or Publisher/System Updates path. The repository transition changes source distribution topology; it does not replace host-owned application state or introduce a CMS schema or WebBlocks UI version change.

## Composer/package-native installations

Keep the same Composer package identity, `fklavyenet/webblocks-cms`, and use Composer in the host application to resolve an intended released version. Follow release-specific notes and use the CMS System Updates flow only according to the installed package's documented update contract.

## Existing full-repository clones

The historical WebBlocks CMS repository was a complete Laravel application. The package-only repository no longer contains that host application. Do not assume `git pull` across this transition is safe: it can remove or conflict with the host shell.

Plan a staged conversion instead. Inventory and back up the existing `.env`, database, storage/uploads, project content, plugins, public overrides, and installed-version state. Prepare a normal Laravel 13 host separately, require the same Composer package identity, verify configuration and database connectivity, and test package installation/update behavior before redirecting traffic or retiring the old clone. Preserve the old installation until the replacement is verified.

## Source-maintained installations

Some maintenance layouts keep package source at `packages/webblocks-cms`. Compatibility code still recognizes this topology. Do not delete or relocate that source merely because the public repository becomes package-only; the maintenance harness must first adopt an explicit authoritative checkout/synchronization model.

As of `1.41.0`, the in-app updater no longer has a source-maintained apply mode: System Updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms`. Source-maintained maintenance checkouts are updated through git/Composer, not through the in-app updater.

## Publisher/System Updates consumers

Publisher artifacts and in-app System Updates are not GitHub source checkouts. Continue using the installed client's supported, checksum/signature-verified update flow. Do not replace it with a Git pull, and do not retire legacy bridge behavior without separately released compatibility evidence.

## New Laravel hosts

For a new Laravel 13 application, follow the Composer-first flow in [README.md](README.md). The package install command may patch the normal User model and remove only Laravel's untouched welcome route; review backups created by the command and keep host customization under host ownership.
