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

- migrations with `--force`
- core catalog seeding for shipped install-level catalogs
- core block type catalog sync with `ddev artisan block-types:sync-core`
- cache clear steps

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
