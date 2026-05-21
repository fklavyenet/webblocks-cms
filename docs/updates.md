# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.
- Fresh Composer consumers should install first with `composer require fklavyenet/webblocks-cms` and `ddev artisan webblocks:install` before using the normal release-based update flow.

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
