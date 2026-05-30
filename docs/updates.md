# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.
- Fresh Composer consumers should install first with `composer require fklavyenet/webblocks-cms` and `ddev artisan webblocks:install` before using the normal release-based update flow.
- Current package-native installs consume package-rooted release ZIPs directly.
- Historically, pre-package-native installs such as `1.31.53` could not directly consume package-rooted release ZIPs and required the old-shape `1.32.33` root-managed bridge first. That bridge path is now retired from routine release validation because there are no remaining old root-managed installs to support in normal gates.

## Operational Expectations

- Run updates from published releases only.
- Keep install-specific files in preserved paths such as `.env`, `storage/`, and `project/`.
- Treat development and release workflows separately.
- In source-maintained maintenance checkouts, local source edits are already present in the working tree. System Updates should not be used to apply those local changes, and the running CMS code version is compared with the latest published release for update availability.
- Release packages contain reusable CMS core code only and must not ship install-specific `project/` content.
- Update-time preserved paths do not change the release package boundary: `project/` stays local to the install and outside the published artifact.
- Installed CMS working copies are update consumers, not upstream publishers. If an installation has a git `origin`, keep fetch access if needed but disable push with `git remote set-url --push origin DISABLED`.

## Release Details

The System Updates screen shows human-readable release details before an admin starts an update. The status hero explains whether an update is available, the running CMS code version is current, the local/source version is newer than the latest published package, the update is incompatible, or the update server response cannot be trusted. The visible summary compares the running CMS code version with the latest published release. Stored installed version remains an install-history/update-persistence value and can be inspected in technical details, but it is not used to make the main summary or Update Options actionable.

The `Release Preview` card renders structured metadata from fields such as `title`, `summary`, `highlights`, `fixes`, `compatibility_notes`, `migration_notes`, `asset_notes`, `operator_notes`, and `technical_notes`. The CMS renders those fields as escaped plain text, with highlights first, fixes/changes compactly listed, compatibility/migration/asset notes in callouts, operator notes grouped as checklist-style items, and technical release notes visually quieter than operator notes.

The legacy `release_notes` string remains supported for older release payloads. If no release notes are present, the screen says `No release notes were provided for this release.` The updater does not infer changes from version numbers. Artifact URLs, checksums, diagnostics, and low-level response details remain in the collapsed technical details area.

The GitHub tag release workflow publishes the structured release detail fields in top-level and nested detail payload shapes alongside the legacy `release_notes` value so the update service can serve rich notes to compatible System Updates screens while older clients continue to receive plain notes. Compatible clients read structured details from top-level fields, `details`, `release_details`, and update-server `meta.release_details` or `meta.details` payloads.

## Update Apply Flow

When an in-app System Update is applied successfully, WebBlocks CMS runs the post-install flow in this order:

- migration handling for the current install strategy
- core catalog seeding for shipped install-level catalogs
- core block type catalog sync with `ddev artisan block-types:sync-core`
- cache clear steps

The cache clear steps include Laravel config, view, application cache, and route clears so updated package-owned Blade layouts and helpers are recompiled after file replacement. On live PHP-FPM installs with OPcache configured not to validate timestamps, reload the relevant PHP-FPM service after a successful update so PHP cannot keep serving pre-update package classes from memory.

For source-maintained maintenance checkouts, migration handling keeps the historical root `database/migrations` authority and runs `artisan migrate --force`. This path is selected only when the root Composer manifest has the maintenance-repository WebBlocks CMS autoload authority, including `WebBlocks\\Cms\\ => packages/webblocks-cms/src/`.

For package-native fresh Composer consumers installed with `webblocks:install`, System Update does not run the host Laravel application's root `database/migrations` directory. This remains true even though the transition updater installs package files into `packages/webblocks-cms`. Package directory presence alone is not a source-checkout signal. This prevents pending Laravel starter migrations such as `0001_01_01_000000_create_users_table.php` from colliding with CMS tables created by the package fresh-install schema. Package consumer updates only run dedicated package-owned update migrations from `packages/webblocks-cms/database/migrations/updates` when that directory contains PHP migration files; otherwise the updater records that host migrations were skipped and continues with catalog seeding, block type sync, cache clears, and installed-version persistence. Package update migrations are also the place for safe existing-install schema repairs, such as adding missing parent keys required by full database backup/restore portability.

During the package transition, some Composer consumers load `WebBlocks\Cms\` from `vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src` while the in-app updater also maintains an install-root `packages/webblocks-cms` copy. System Update now replaces both safe CMS package runtime roots when that Composer autoload shape is detected, so a successful package-native update cannot leave stale active vendor controllers behind while only refreshing the root transition copy.

Modern updates preserve the `/webadmin` admin and `/cms` asset split introduced by the v1.32.56 migration. `/cms` is a static asset namespace only, not an admin prefix, because Nginx `try_files` can resolve `/cms/` as the physical `public/cms/` directory before Laravel sees a route. Updates must not restore CMS-owned `/cms` admin aliases, `/cms` redirects, `/admin` routes, or a `public/cms/index.php` handoff in either the install root or package public assets.

## Retired Bridge From 1.31 Root-Managed Updates

This section is historical. The `1.31.53` updater validated the old root-managed archive contract: `artisan` and `composer.json` had to exist at the archive root, or inside a single wrapper directory. Package-rooted artifacts such as `1.32.31` intentionally did not contain root `artisan`, so those old clients failed before apply with `Package validation failed because composer.json and artisan were not found at the archive root.`

The retired bridge strategy was two-step:

- Publish or republish a bridge release artifact in the old root-managed shape, built from a bridge-capable source that still had the legacy `App\Support\System\Updates\*` wrappers and already validated strict package-rooted `fklavyenet/webblocks-cms` archives. For the `1.32.33` bridge, `v1.32.30` was the source ref.
- Publish package-rooted releases with `minimum_client_version` set to `1.32.18` or newer, so old clients were not offered the latest package-rooted artifact before the bridge.
- After the bridge was applied, the installed updater could validate and apply the strict package-rooted `fklavyenet/webblocks-cms` artifact shape used by modern releases.

`scripts/build-root-managed-bridge-archive.sh VERSION [OUTPUT_DIR] [GIT_REF]` is retained only as an archived/manual recovery tool for the old-shape bridge ZIP; for example, `scripts/build-root-managed-bridge-archive.sh 1.32.33 dist v1.32.30`. The builder intentionally excludes install-owned paths such as `.env`, `storage/`, `project/`, `public/site/`, `public/storage`, and root `config/`; package-owned defaults under `packages/webblocks-cms/config` remain part of the package runtime. Routine package-native validation does not run this bridge path.

The completed historical path was `1.31.53 -> 1.32.33 bridge -> 1.32.34+ package-rooted`. Already bridge-capable installs such as `1.32.30` skipped the bridge and updated directly to a package-rooted `1.32.34+` release. Current release gates protect only the package-rooted artifact and package-native updater behavior.

The block type sync is idempotent and keeps the database-backed `block_types` catalog aligned with the shipped core CMS catalog on existing installs:

- missing core block types are created
- existing core block types are updated in place
- custom install-specific block types are preserved
- duplicate core rows are not created

This closes the gap where a code update could add new shipped block types without guaranteeing that an older install's `block_types` table was refreshed to match.

When the updater runs inside a git-backed installation clone that still points at the canonical CMS upstream, CMS now also disables `origin` push automatically after post-install commands so future accidental `git push` attempts fail clearly while normal fetch or pull access remains available.

## Related Docs

- [Operations](operations.md)
- [Installation](installation.md)
- [Development Workflow](../DEVELOPMENT.md)
