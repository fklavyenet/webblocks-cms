# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.
- Fresh Composer consumers should install first with `composer require fklavyenet/webblocks-cms` and `php artisan webblocks:install` before using the normal release-based update flow.
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
- A System Update is recorded as successful only after the applied package runtime reports the target version from the canonical `WebBlocks\Cms\Support\WebBlocks` version source. If the applied code still reports an older or unexpected version, the run is recorded as failed and operators should restore the pre-update backup or inspect filesystem/cache state before retrying.

## Release Details

The System Updates screen shows human-readable release details before an admin starts an update. The main flow has two cards: `Install Update` and `Update Details`. `Install Update` explains whether an update is available, the running CMS code version is current, the local/source version is newer than the latest published package, the update is incompatible, or the update server response cannot be trusted. The visible summary compares the running CMS code version with the latest published release. Stored installed version remains an install-history/update-persistence value and can be inspected in `Update Readiness`, but it is not used to make the Install Update action actionable.

`Update Details` keeps release notes, update readiness, and the last update run behind WebBlocks UI accordion rows. Update Readiness is an install and update-service readiness summary, not release notes for the target version. Last Update Run shows the latest relevant run summary and opens details in a modal when needed. A super-admin support report download is available for shared-hosting support cases; it includes safe version/readiness/run summaries and excludes tokens, secrets, absolute local paths, and raw stack traces.

Update run records are automatically pruned after update checks and apply/cancel flows. The default retention keeps the latest five runs, while the latest failed run is preserved until a newer successful run exists. The main admin screen does not list old runs. Terminal operators can inspect retained runs with `php artisan webblocks:updates:runs`, `php artisan webblocks:updates:runs --last`, and `php artisan webblocks:updates:runs --failed`; controlled pruning is available with `php artisan webblocks:updates:prune-runs --keep=5`.

The compact `Release Notes` accordion in Update Details renders structured metadata from fields such as `title`, `summary`, `highlights`, `fixes`, `compatibility_notes`, `migration_notes`, `asset_notes`, `operator_notes`, and `technical_notes`. The CMS renders those fields as escaped plain text and keeps readiness checks, stored installed version, and low-level response details in `Update Readiness`.

The legacy `release_notes` string remains supported for older release payloads. If no release notes are present, the screen says `No release notes were provided for this release.` The updater does not infer changes from version numbers.

Release metadata is prepared locally with `composer release:prepare` and published directly to the update server with `composer release:publish-update`. The native publisher sends structured release detail fields in top-level and nested detail payload shapes alongside the legacy `release_notes` value so the update service can serve rich notes to compatible System Updates screens while older clients continue to receive plain notes. Compatible clients read structured details from top-level fields, `details`, `release_details`, and update-server `meta.release_details` or `meta.details` payloads.

GitHub Actions no longer creates release packages or publishes update metadata, and `.github` workflows are intentionally absent from the CMS repository. Maintainers may still push git commits and tags for source history, but System Updates consume only update-server metadata and package artifacts. The package artifact URL used by installed CMS clients comes from `updates.webblocksui.com` metadata after native publishing, not from a GitHub release asset.

Maintainer commands:

```bash
composer release:prepare
composer release:publish-update -- --dry-run
composer release:publish-update
```

The publisher reads only `WEBBLOCKS_PUBLISHER_URL`, `WEBBLOCKS_PUBLISHER_TOKEN`, `WEBBLOCKS_PUBLISHER_PRODUCT`, and `WEBBLOCKS_PUBLISHER_CHANNEL`. Defaults are `https://publisher.webblocksui.com/api/updates/publish`, no token, `webblocks-cms`, and `stable`. Cached-config publish runs refresh those canonical publisher keys from the project `.env` so a locally configured token is detected without requiring shell exports. Legacy publisher aliases are intentionally unsupported. Dry-run validates inputs without uploading. A real publish without a token reports a controlled non-published state, exits unsuccessfully, and must not be treated as a release publication.

## Update Apply Flow

When an in-app System Update is applied successfully, WebBlocks CMS runs the post-install flow in this order:

- migration handling for the current install strategy
- cache clear steps
- update run recording
- installed version persistence

Normal System Updates apply published release packages. They do not automatically run core catalog seeding, `block-types:sync-core`, icon sync, slot type repair, page layout slot repair, or broad catalog repair. If a release requires a schema or data transformation, it must be handled as an explicit update migration for that release.

The cache clear steps include Laravel config, view, application cache, and route clears so updated package-owned Blade layouts and helpers are recompiled after file replacement. On live PHP-FPM installs with OPcache configured not to validate timestamps, reload the relevant PHP-FPM service after a successful update so PHP cannot keep serving pre-update package classes from memory.

For source-maintained maintenance checkouts, migration handling keeps the historical root `database/migrations` authority and runs `artisan migrate --force`. This path is selected only when the root Composer manifest has the maintenance-repository WebBlocks CMS autoload authority, including `WebBlocks\\Cms\\ => packages/webblocks-cms/src/`.

For package-native fresh Composer consumers installed with `webblocks:install`, System Update does not run the host Laravel application's root `database/migrations` directory. This remains true even though the transition updater installs package files into `packages/webblocks-cms`. Package directory presence alone is not a source-checkout signal. This prevents pending Laravel starter migrations such as `0001_01_01_000000_create_users_table.php` from colliding with CMS tables created by the package fresh-install schema. Package consumer updates only run dedicated package-owned update migrations from `packages/webblocks-cms/database/migrations/updates` when that directory contains PHP migration files; otherwise the updater records that host migrations were skipped and continues with cache clears and installed-version persistence. Package update migrations are also the place for safe existing-install schema repairs, such as adding missing parent keys required by full database backup/restore portability.

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

## Catalog Repair

Catalog repair and synchronization are explicit maintenance actions, separate from System Updates. Use:

```bash
php artisan webblocks:catalog-repair --dry-run --all
php artisan webblocks:catalog-repair --all
```

The command supports scoped maintenance with `--block-types`, `--slot-types`, `--page-layouts`, and `--icons`. Run with `--dry-run` first to report rows that would be created, updated, left unchanged, or skipped. The command is idempotent, preserves install-specific/custom catalog rows, and does not delete custom rows.

The lower-level block type sync remains available for compatibility:

```bash
php artisan block-types:sync-core
```

The block type repair path keeps the database-backed `block_types` catalog aligned with the shipped core CMS catalog on existing installs:

- missing core block types are created
- existing core block types are updated in place
- custom install-specific block types are preserved
- duplicate core rows are not created

This maintenance workflow closes the gap where an install needs catalog rows refreshed without making every release package apply perform broad database-backed catalog repair.

When the updater runs inside a git-backed installation clone that still points at the canonical CMS upstream, CMS now also disables `origin` push automatically after post-install commands so future accidental `git push` attempts fail clearly while normal fetch or pull access remains available.

## Related Docs

- [Operations](operations.md)
- [Installation](installation.md)
- [Development Workflow](../DEVELOPMENT.md)
