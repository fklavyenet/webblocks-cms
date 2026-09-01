---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/updates
cms_title: Updates
cms_layout: docs
cms_source_id: webblocks-cms:docs/updates.md
---

# Updates

Updates in WebBlocks CMS are release-based and package-based.

## Shared Client Source

CMS ships the shared Publisher Client runtime as synchronized source under
`src/Support/Updates/Client`; it does not require the private Client Composer
package. CMS-owned adapters keep database run history, installed-version
persistence, package-only migrations, catalog repair, and full CMS backups.
Generated Client files are not edited directly. The private Client checkout is
the source of truth and updates all registered copies with
`composer embed:sync-all`; `composer embed:check-all` detects drift through the
package-root `.publisher-client.json` manifest.

## Core Rules

- The installed version reflects the last real release applied to the install.
- Ordinary source development does not change the installed version.
- The in-app updater applies published release packages, not local working-tree changes.
- Fresh Composer consumers should install first with `composer require fklavyenet/webblocks-cms` and `php artisan webblocks:install` before using the normal release-based update flow.
- Current package-native installs consume package-rooted release ZIPs directly.
- Package-native System Updates apply the package artifact to the canonical Composer package root, `vendor/fklavyenet/webblocks-cms`, so Composer update and System Update produce the same installed package layout.
- Historically, pre-package-native installs such as `1.31.53` could not directly consume package-rooted release ZIPs and required the old-shape `1.32.33` root-managed bridge first. That bridge path is now retired from routine release validation because there are no remaining old root-managed installs to support in normal gates.

## Operational Expectations

- Run updates from published releases only.
- Keep install-specific files in preserved paths such as `.env`, `storage/`, and `project/`.
- Treat development and release workflows separately.
- In-app System Updates always target the canonical Composer package root, `vendor/fklavyenet/webblocks-cms`. Source-maintained maintenance checkouts update through git/Composer instead of the in-app updater; the retired source-maintained apply mode and its `WEBBLOCKS_UPDATES_MIGRATION_STRATEGY` setting are ignored as of `1.41.0`.
- Release packages contain reusable CMS core code only and must not ship install-specific `project/` content.
- Update-time preserved paths do not change the release package boundary: `project/` stays local to the install and outside the published artifact.
- Installed CMS working copies are update consumers, not upstream publishers. If an installation has a git `origin`, keep fetch access if needed but disable push with `git remote set-url --push origin DISABLED`.
- A System Update is recorded as successful only after the applied package runtime reports the target version from the canonical `WebBlocks\Cms\Support\WebBlocks` version source. If the applied code still reports an older or unexpected version, the apply is treated as failed and the updater automatically restores the pre-update backup; the run is recorded with the `restored` status when the restore succeeds, or `failed` with both error trails when it does not.

## Release Details

The System Updates screen is a single status card with two states. When the install is up to date, it shows a quiet up-to-date summary with the installed version and last-checked time. When a compatible update is available, it shows the installed-to-target version path, a folded `What's new` area with a per-version changelog accordion, a one-click `Update to X` action with a backup note (automatic pre-update backup, automatic restore on failure), and a server-backup advisory line that links to the Backups screen for a fresh full backup before major updates. The visible summary compares the running CMS code version with the latest published release; the stored installed version remains an install-history/update-persistence value only.

Super admins get an async update indicator shell in the admin top navbar. It is hidden by default, reads `/webadmin/system/updates/indicator` in the background, and becomes visible only when a newer trusted release is available. Normal admin page rendering is not blocked by the update server, and up-to-date installs do not show a persistent update shortcut.

Preflight readiness is a short, explicit check list surfaced on the screen: database connection, ZIP and sodium extensions, PHP/Composer/process execution, application-root and workspace write access, and free disk space. Each check is strictly pass or fail, and the update action is available only when every check passes and no update run holds the lock. Retained update history renders as a closed accordion; each run opens its log in a modal, and destructive row actions stay out of the UI.

Update run records are automatically pruned after update checks and apply flows. The default retention keeps the latest five runs, while the latest failed run is preserved until a newer successful run exists. The main admin screen lists retained runs without delete controls. Terminal operators can inspect retained runs with `php artisan webblocks:updates:runs`, `php artisan webblocks:updates:runs --last`, and `php artisan webblocks:updates:runs --failed`; controlled pruning is available with `php artisan webblocks:updates:prune-runs --keep=5`.

The `Release` details render structured metadata from fields such as `title`, `summary`, `highlights`, `fixes`, `compatibility_notes`, `migration_notes`, `asset_notes`, `operator_notes`, and `technical_notes`. The CMS renders those fields as escaped plain text. The normalized release array also carries `changelog_entries`: a cumulative newest-first list of per-version entries between the installed and target versions, built from update-server `changelog`, `releases`, or `changelog_entries` payload lists when present. When the server provides no cumulative list, the client degrades gracefully to a single entry for the latest release, so the `What's new` accordion always has content.

The legacy `release_notes` string remains supported for older release payloads. If no release notes are present, the screen says `No release notes were provided for this release.` The updater does not infer changes from version numbers.

Release metadata is prepared locally with `composer release:prepare` and published with `composer release:publish-update`. The publish wrapper verifies GitHub CLI authentication before the irreversible Publisher write, publishes to the Publisher service, then creates or updates the matching GitHub Release and marks it latest. The prepare step requires a matching `CHANGELOG.md` section for the release version, uses its operator-facing bullet points for the legacy `release_notes` value and structured `summary`/`highlights`, and adds release-specific technical metadata such as source reference, checksum, and minimum update client version. The native publisher sends those structured release detail fields in top-level and nested detail payload shapes so the update service can serve rich notes to compatible System Updates screens while older clients continue to receive plain notes. Compatible clients read structured details from top-level fields, `details`, `release_details`, and update-server `meta.release_details` or `meta.details` payloads.

GitHub Actions does not create release packages or publish update metadata; the repository's `.github` workflows run tests and lint only. The local release wrapper owns the GitHub Release record so the repository's Latest release stays aligned with the tagged stable release. System Updates continue to consume only Publisher metadata and package artifacts. `publisher.webblocksui.com` is the canonical service for both publishing and update consumption: maintainers publish to `https://publisher.webblocksui.com/api/updates/publish`, installed CMS sites read latest metadata from `https://publisher.webblocksui.com/api/updates/latest`, and metadata artifact URLs should point to `https://publisher.webblocksui.com/downloads/...` package downloads. Installed CMS sites do not configure Publisher/update server, product, or channel environment keys in normal `.env` files because CMS product code owns the default release server, product key, stable channel, latest path, and publish path through `ReleaseDefaults`. The former `updates.webblocksui.com` bridge is historical only and should not be used as an active configuration path.

Maintainer commands:

```bash
composer release:prepare
composer release:publish-update -- --dry-run
composer release:publish-update
```

Maintainer publishing uses `WEBBLOCKS_PUBLISHER_TOKEN` and, for signed releases, `WEBBLOCKS_PUBLISHER_SIGNING_KEY`. Installed CMS update checks use product defaults for `https://publisher.webblocksui.com`, product `webblocks-cms`, channel `stable`, and read path `/api/updates/latest`; maintainer publishing uses the same product-owned identity and publish path `/api/updates/publish`. The Composer publishing wrapper reads only these two Publisher secrets from the package project's `.env` and exports them to the isolated Testbench publishing process, while cached-config publish runs also refresh the same keys from the project `.env`; no manual shell export is required. Dry-run validates the signing key as well as artifact inputs without uploading. A real publish without a token reports a controlled non-published state, exits unsuccessfully, and must not be treated as a release publication.

## Anonymous Adoption Telemetry

Update checks include privacy-preserving product adoption telemetry so Publisher analytics can distinguish package downloads from active anonymous installations. Downloads count artifact retrieval. Active anonymous installations count unique update-check installation IDs that report back over time.

Telemetry is enabled by default and can be disabled with:

```env
WEBBLOCKS_TELEMETRY=false
```

When telemetry is enabled, the CMS creates a random local `installation_id` the first time an update check needs it and stores it in install-level system settings. The ID is generated randomly, contains no site or personal information, remains stable for the existing install, and may change if the install is recreated or storage/database settings are reset.

The update check sends only these telemetry fields:

- `product_key`
- `installed_version`
- `channel`
- `installation_id`
- `telemetry_schema_version`

The CMS update check does not send domain names, full URLs, admin emails, server paths, database names, license owner data, user counts, tokens, secrets, or arbitrary environment/config values. When `WEBBLOCKS_TELEMETRY=false`, the update check still requests release metadata with product, channel, and installed version, but it does not create or send an `installation_id`.

## Update Apply Flow

An in-app System Update is a single one-click run: download and verify the release package, take an automatic pre-update backup, apply the package files, then run the post-install flow in this order:

- package update migrations when present
- cache clear steps
- shipped catalog sync (`webblocks:catalog-repair --all`)
- update run recording
- installed version persistence

If the apply or its verification fails, the updater automatically restores the pre-update backup. A failed-then-restored run is recorded with the `restored` status; when the automatic restore also fails, the run is recorded as `failed` with both error trails in the run log, and the pre-update backup remains available on the Backups screen for manual restore (or `php artisan system:backup:restore` on the CLI).

After a successful update, the updater applies the configured automatic backup cleanup policy and records the deleted count and freed bytes in the update log. Cleanup failures do not roll back an otherwise successful update; they are recorded in the update output and application log for operator review. The default policy retains pre-update backups for 14 days and always keeps the latest five successful pre-update backups; restore-safety backups remain for 30 days and content-apply restore points for 7 days. Pre-update backups matched to failed or automatically restored update runs are protected for 90 days. Manual, uploaded, and running backups are never eligible. Configure or preview the policy under `Maintenance → Cleanup`; see [Operations](operations.md#automatic-backup-cleanup) for scheduler, CLI, and Internal API details.

Normal System Updates apply published release packages and, after the cache clear steps, run `webblocks:catalog-repair --all` as a subprocess against the freshly installed code. This keeps the database-backed block type, plugin block type, slot type, page layout, and icon catalogs aligned with the shipped catalog so a release can add catalog rows (for example the engagement Rating and Comments block types) without an operator having to run a manual command afterward. The sync runs after cache clears so the subprocess boots with a rebuilt service manifest and can discover newly registered package commands. It is idempotent and preserves install-specific/custom catalog rows.

The catalog sync is best-effort: because it runs after files and migrations have already succeeded, a catalog sync failure does not fail the update run. Instead the update log records that the sync did not complete and that `php artisan webblocks:catalog-repair --all` can be re-run manually. Schema changes still belong in explicit update migrations for the release; the catalog sync only repairs shipped catalog data rows, not schema.

The cache clear steps include Laravel config, view, application cache, and route clears so updated package-owned Blade layouts and helpers are recompiled after file replacement. On live PHP-FPM installs with OPcache configured not to validate timestamps, reload the relevant PHP-FPM service after a successful update so PHP cannot keep serving pre-update package classes from memory.

While the update runs, the System Updates screen shows a non-dismissible interstitial modal with the installed-to-target version path, using the shared WebBlocks UI loading primitive. Because the applied code can restart PHP workers mid-request, the interstitial does not depend on the original request completing: it fires the update, then polls the update indicator route every 1.5 seconds until the application answers again, with a 3-minute safety net that returns to the admin regardless. Any answered poll (including a redirect to login) reloads the screen so the operator lands on the updated install.

System Update never runs the host Laravel application's root `database/migrations` directory. This prevents pending Laravel starter migrations such as `0001_01_01_000000_create_users_table.php` from colliding with CMS tables created by the package fresh-install schema. Updates apply the release artifact to `vendor/fklavyenet/webblocks-cms` and only run dedicated package-owned update migrations from `vendor/fklavyenet/webblocks-cms/database/migrations/updates` when that directory contains PHP migration files; otherwise the updater records that migrations were skipped and continues with cache clears and installed-version persistence. Package update migrations are also the place for safe existing-install schema repairs, such as adding missing parent keys required by full database backup/restore portability.

## Package-Native Schema Update Rule

Any WebBlocks CMS schema change that is required by runtime code must support both install paths:

1. Fresh/package consumer installs: update the normal or fresh schema migration path.
2. Existing package-native installs updated through System Updates: add a package update migration under package `database/migrations/updates`; installed consumers run it from `vendor/fklavyenet/webblocks-cms/database/migrations/updates`.

Fresh schema alone is not enough. If new runtime code expects a table or column, the release must include an update migration for existing installs or the update must fail safely before the new code path can raw-500. Package-native System Updates must not require ordinary users to SSH into a site and run manual migrations after a successful update.

A successful package-native System Update means the applied code, required schema, cache clears, and post-apply version/schema readiness are aligned. Admin, API, and runtime pages that depend on newly added schema should show controlled setup/update guidance for missing schema instead of exposing raw framework/database errors. The 1.32.146 to 1.32.147 API token incident is the reference failure mode: `cms_api_tokens` existed only in the normal migration path, package-native QuizTem updated the code, and `System -> API Tokens` raw-500ed until 1.32.147 added a package update migration and graceful readiness handling.

Engagement schema follows the same contract. Package-native updates must automatically ensure the runtime-owned `wbcms_comment_entries` and `wbcms_content_ratings` tables; administrators must not need to run host `artisan migrate` commands. The repair migration is idempotent: it preserves and renames legacy unprefixed engagement tables when present, creates missing prefixed tables otherwise, and intentionally preserves engagement data on rollback.

Schema-change release reports must explicitly answer:

- fresh schema path updated: yes/no
- package update migration added: yes/no
- update migration regression test added: yes/no
- graceful missing-schema behavior needed/added: yes/no

During the package transition, some installs may still have a stale install-root `packages/webblocks-cms` copy or an old nested vendor transition copy. These paths are legacy transition artifacts, not the active package-native source of truth. Package-native System Update now replaces the canonical Composer package root at `vendor/fklavyenet/webblocks-cms` and verifies the target version from that package root. It does not keep `packages/webblocks-cms` as a second updated runtime copy.

Older installs can have a repo-shaped Composer vendor directory at `vendor/fklavyenet/webblocks-cms` with root files such as `artisan`, `app/`, `bootstrap/`, `packages/webblocks-cms/`, `plugins/`, or `tests/`. System Update normalizes that vendor directory by replacing it with the flat package-rooted artifact. The resulting package root contains package files such as `composer.json`, `src/`, `docs/`, `routes/`, `resources/`, `database/`, `public/`, `config/`, and `stubs/` directly under `vendor/fklavyenet/webblocks-cms`. Package-native updates normalize Composer's installed package metadata before regenerating optimized autoload files, then verify that generated Composer autoload metadata resolves `WebBlocks\Cms\` from `vendor/fklavyenet/webblocks-cms/src`, not the legacy nested `vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src`. If stale nested paths remain, the update run fails instead of reporting success with a broken admin runtime.

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

System Updates run `webblocks:catalog-repair --all` automatically as part of the post-install flow (see [Update Apply Flow](#update-apply-flow)). The same commands remain available as manual maintenance actions for repairing catalog rows between releases or after a best-effort update sync did not complete. Use:

```bash
php artisan webblocks:catalog-repair --dry-run --all
php artisan webblocks:catalog-repair --all
```

The command supports scoped maintenance with `--block-types`, `--plugin-block-types`, `--slot-types`, `--page-layouts`, and `--icons`. Run with `--dry-run` first to report rows that would be created, updated, left unchanged, or skipped. The command is idempotent, preserves install-specific/custom catalog rows, and does not delete custom rows.

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
- [Contributing](../CONTRIBUTING.md)
