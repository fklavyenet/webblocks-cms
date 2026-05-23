# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.
- Fresh Composer consumers should install first with `composer require fklavyenet/webblocks-cms` and `ddev artisan webblocks:install` before using the normal release-based update flow.
- Pre-package-native installs, including `1.31.53`, cannot directly consume package-rooted release ZIPs. They must first install a root-managed bridge release whose archive root still contains `artisan` and root `composer.json`, and whose contents install the package-native updater.
- The current bridge publication target is `1.32.33`. Do not direct `1.31.53` clients to `1.32.32`; that version was auto-published as a package-rooted update-service artifact and is not safe for old root-managed validators.

## Operational Expectations

- Run updates from published releases only.
- Keep install-specific files in preserved paths such as `.env`, `storage/`, and `project/`.
- Treat development and release workflows separately.
- Release packages contain reusable CMS core code only and must not ship install-specific `project/` content.
- Update-time preserved paths do not change the release package boundary: `project/` stays local to the install and outside the published artifact.
- Installed CMS working copies are update consumers, not upstream publishers. If an installation has a git `origin`, keep fetch access if needed but disable push with `git remote set-url --push origin DISABLED`.

## Update Apply Flow

When an in-app System Update is applied successfully, WebBlocks CMS runs the post-install flow in this order:

- migration handling for the current install strategy
- core catalog seeding for shipped install-level catalogs
- core block type catalog sync with `ddev artisan block-types:sync-core`
- cache clear steps

For source-maintained maintenance checkouts, migration handling keeps the historical root `database/migrations` authority and runs `artisan migrate --force`. This path is selected only when the root Composer manifest has the maintenance-repository WebBlocks CMS autoload authority, including `WebBlocks\\Cms\\ => packages/webblocks-cms/src/`.

For package-native fresh Composer consumers installed with `webblocks:install`, System Update does not run the host Laravel application's root `database/migrations` directory. This remains true even though the transition updater installs package files into `packages/webblocks-cms`. Package directory presence alone is not a source-checkout signal. This prevents pending Laravel starter migrations such as `0001_01_01_000000_create_users_table.php` from colliding with CMS tables created by the package fresh-install schema. Package consumer updates only run dedicated package-owned update migrations from `packages/webblocks-cms/database/migrations/updates` when that directory contains PHP migration files; otherwise the updater records that host migrations were skipped and continues with catalog seeding, block type sync, cache clears, and installed-version persistence. Package update migrations are also the place for safe existing-install schema repairs, such as adding missing parent keys required by full database backup/restore portability.

During the package transition, some Composer consumers load `WebBlocks\Cms\` from `vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src` while the in-app updater also maintains an install-root `packages/webblocks-cms` copy. System Update now replaces both safe CMS package runtime roots when that Composer autoload shape is detected, so a successful package-native update cannot leave stale active vendor controllers behind while only refreshing the root transition copy.

## Bridge From 1.31 Root-Managed Updates

The `1.31.53` updater validates the historical root-managed archive contract: `artisan` and `composer.json` must exist at the archive root, or inside a single wrapper directory. Package-rooted artifacts such as `1.32.31` intentionally do not contain root `artisan`, so those old clients fail before apply with `Package validation failed because composer.json and artisan were not found at the archive root.`

The safe bridge strategy is two-step:

- Publish or republish a bridge release artifact in the old root-managed shape, built from a bridge-capable source that still has the legacy `App\Support\System\Updates\*` wrappers and already validates strict package-rooted `fklavyenet/webblocks-cms` archives. For the current bridge, use `v1.32.30` as the source ref.
- Publish package-rooted releases with `minimum_client_version` set to `1.32.18` or newer, so old clients are not offered the latest package-rooted artifact before the bridge.
- After the bridge is applied, the installed updater can validate and apply the strict package-rooted `fklavyenet/webblocks-cms` artifact shape used by modern releases.

Use `scripts/build-root-managed-bridge-archive.sh VERSION [OUTPUT_DIR] [GIT_REF]` from a bridge-capable tag to create the old-shape bridge ZIP; for example, `scripts/build-root-managed-bridge-archive.sh 1.32.33 dist v1.32.30`. The builder intentionally excludes install-owned paths such as `.env`, `storage/`, `project/`, `public/site/`, `public/storage`, and root `config/`; package-owned defaults under `packages/webblocks-cms/config` remain part of the package runtime.

For the current recovery release, publish the `1.32.33` root-managed bridge artifact only for old root-managed clients. Bridge-capable clients such as `1.32.18+` must receive a package-rooted release instead; if the update service cannot target artifacts by client capability, do not leave the bridge as the global current stable release. The intended old-client path is `1.31.53 -> 1.32.33 bridge -> 1.32.34+ package-rooted`, while already bridge-capable installs such as `1.32.30` must skip the bridge and update directly to a package-rooted `1.32.34+` release.

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
