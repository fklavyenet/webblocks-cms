# Development Workflow

## Purpose

This document defines how WebBlocks CMS should be developed from source, how release versions relate to installed versions, and how local development differs from the package-based update flow used by installed sites.

The goal is to keep development, release tagging, published packages, and in-app updates aligned without confusing the updater about unreleased local source code.

## Local Development Rule

WebBlocks CMS is developed locally from source.

That means:

- the local working tree can contain unreleased changes
- the local database can be running against code that has not been tagged or published yet
- the local environment may be ahead of the last real release

This is expected and correct during active development.

## Static Asset Rule

WebBlocks CMS does not use a Vite, Laravel Vite plugin, Tailwind, npm, or Node build chain for CMS runtime assets.

CMS-owned CSS, JavaScript, and brand files are maintained as static product assets under `public/cms` and package `packages/webblocks-cms/public/cms`. WebBlocks UI assets are consumed through pinned published CDN/dist URLs from `WebBlocks\Cms\Support\WebBlocks`; do not compile WebBlocks UI source inside this repository.

Do not add `vite.config.*`, `tailwind.config.*`, `postcss.config.*`, `package.json`, lockfiles such as `package-lock.json`, npm build scripts, `@vite` Blade directives, Laravel Vite plugin dependencies, `public/build`, `public/hot`, or `node_modules` assumptions to CMS runtime, release, or update paths unless a future architecture decision explicitly changes this product boundary.

## Installed Version Rule

`system.installed_version` represents the last released or applied version of the CMS install.

It does not represent every local commit, every unfinished feature branch, or every source-level code change made during development.

Do not bump `system.installed_version` for ordinary development work.

## Why Dev May Show An Older Version

In local development, the codebase may move ahead while the installed version remains on the last real release.

For example:

- local code may include new migrations, views, routes, or admin behavior
- the admin may still show an installed version from the last release
- this does not mean the local environment is broken

This separation exists because the codebase is being developed from source, while the installed version should continue to represent the last real release boundary.

## System Updates In Development

The admin `System Updates` screen is for installed, released packages.

It is not the correct tool for applying ordinary local source changes.

During local development:

- do not use the admin `System Updates` button to apply your own source edits
- do not use the updater to simulate local code synchronization
- do not run `git pull` from the CMS updater workflow
- do not fake update availability by manually advancing installed version state during routine development

The updater flow must remain release-based and package-based.

System Updates apply published packages and required update migrations only. Broad catalog synchronization is explicit maintenance; use `php artisan webblocks:catalog-repair --dry-run --all` before applying `php artisan webblocks:catalog-repair --all` when an install needs shipped block type, slot type, page layout, or icon catalog repair.

## Package-Native Schema Changes

Runtime-required schema changes must support both install paths:

1. Fresh/package consumer installs through the normal or fresh schema migration path.
2. Existing package-native installs through a package update migration under `packages/webblocks-cms/database/migrations/updates`.

Fresh schema alone is not enough. If new code expects a table or column, the same release must include a package update migration for existing installs, or the update must fail safely before the new code path can raw-500. Ordinary System Updates must not require users to SSH into an install and run manual migrations after the update.

Admin, API, and runtime surfaces that depend on newly added schema should show controlled setup/update guidance when that schema is missing. Release validation for package-native schema additions must include a regression test similar to `CmsApiTokensUpdateMigrationTest`, plus the relevant admin/API/runtime tests for the changed surface.

## Core Vs Project Layer

WebBlocks CMS core and the Project Layer have different responsibilities.

- Core directories such as `app/`, `routes/`, `resources/`, and `config/` are for reusable CMS engine behavior.
- Site-specific code belongs in `project/`, database content, or instance configuration.
- Do not place update-sensitive instance logic directly into core directories.
- The Project Layer is install-local and update-safe, but it is not a reusable plugin packaging system.
- Reusable product or domain extensions should use the plugin system conventions in `docs/plugin-system.md`: kebab-case handles, handle-prefixed permissions and commands, plugin route namespaces, plugin-owned settings namespaces, plugin-owned table prefixes, compatibility metadata, and active-only contributions.
- WebBlocks UI Manager publish work belongs in `plugins/webblocks-ui-manager`, not CMS core runtime. Build it as a manual ZIP artifact for operator installs; keep dry-run/publish validation, filesystem writes, manifests, checksums, and run history in plugin support services rather than CMS core controllers or generic update code.
- Manual plugin setup is explicit. Enabling a plugin may activate route/menu registration, but plugin-owned screens must guard schema readiness and show setup-required guidance until plugin migrations complete. Use the plugin detail `Run Plugin Migrations` action for manifest-declared plugin migrations instead of running host migrations.
- Manual plugin uninstall is storage-owned and disabled-first. Do not drop plugin-owned tables, delete project files, delete CMS core files, or remove storage outside the configured plugin install root as part of ordinary uninstall behavior.

## Update-Safe Customization Rule

- Core updates may change the Project Layer loader or kernel.
- Core updates must not overwrite `.env`, `storage/`, or `project/`.
- Site-specific behavior should be implemented through `project/Providers`, `project/Routes`, `project/config`, and `project/resources/views`.
- If customization is instance-specific, keep it out of core `app/`, `routes/`, `resources/`, and `config/`.

## Release Synchronization Rule

The development environment version must only be synchronized when an actual release is created.

That means the correct time to align local development with a release version is when:

- the release is prepared
- the version stored in config or code is updated
- the release tag is created and pushed
- the release becomes real through the published release flow

Only then may the local development install be synchronized to that released version.

## Repository Boundary Rule

Only the real WebBlocks CMS maintenance checkout may publish `main` updates, tags, or releases to `github.com/fklavyenet/webblocks-cms`.

- installed site working copies such as local customer, staging, or project clones are downstream consumers only
- those installation working copies should set `origin` push to `DISABLED` with `git remote set-url --push origin DISABLED`
- maintainers must prepare releases from the maintenance repository after confirming they are not inside an installed site checkout

## Recommended Release Flow

Recommended sequence:

1. finish the feature or fix in source
2. run tests and required verification steps locally
3. update `README.md` for any meaningful behavior, setup, route, usage, admin, command, or release-note change
4. update `CHANGELOG.md`
5. update `App\Support\WebBlocks::VERSION`
6. run `composer release:prepare` to generate the package ZIP, SHA-256 checksum, and update-server payload locally
7. create and push the real git commit/tag as source history
8. run `composer release:publish-update -- --dry-run`
9. run `composer release:publish-update` to publish the artifact and metadata to the configured update server
10. only after the release is real, synchronize the dev environment installed version if needed

Release package creation and update publishing are native/local maintainer steps. GitHub Actions does not own release notes, package creation, update-server publishing, or any fallback publishing path, and `.github` workflows are intentionally absent. Git tags may still be pushed for source history, but update publishing does not depend on GitHub releases, GitHub asset URLs, the GitHub API, or the `gh` CLI.

## Coding Standards

Use `AGENTS.md` as the compact AI and project working contract for routine implementation work.

Formatting sources of truth:

- `.editorconfig` defines baseline whitespace, line ending, encoding, and indentation rules
- `pint.json` defines the repository PHP formatting rules through Laravel Pint
- `scripts/check-php-indentation.php` enforces the project-specific 2-space PHP indentation rule that Pint does not currently catch

Standard development commands:

- `composer format` applies Pint fixes
- `composer format:test` checks Pint formatting without modifying files and runs the PHP indentation guard
- `php artisan test --filter=...`
- `php artisan test`

Prefer native `composer` and `php artisan` commands in examples and routine workflows, and keep formatting or standards changes focused instead of mass-reformatting unrelated files.

When AI or automation writes PHP files, verify the actual file contents for 2-space indentation instead of assuming Pint alone proves indentation compliance. The repository-wide formatting baseline is `composer format:test`, which runs Pint for non-indentation style and the custom indentation guard across maintained PHP and Blade roots. Pint's indentation-specific fixers are disabled so `scripts/check-php-indentation.php` remains authoritative for the 2-space PHP indentation rule.

## Risk-Based Validation

Use the smallest validation that can credibly cover the change while still protecting release quality.

During feature implementation:

- run the smallest relevant focused tests that cover the changed behavior
- prefer specific test classes or method filters over broad filters such as `--filter=Page` when possible
- use `--stop-on-failure` during iteration so failures surface quickly
- do not repeatedly run the full suite during implementation unless the change genuinely needs that breadth to make progress

Before merge:

- rerun the focused tests that cover the changed behavior
- increase focused coverage for migrations, system updates, backup or restore flows, revisions, permissions, routing, portability, or other cross-cutting changes

Before tagging a release:

- start with the risk-based composer scripts documented in `docs/testing-strategy.md`
- use `composer test:release-fast` as the default gate for small package-native hotfixes
- use `composer test:package`, `composer test:update`, `composer test:install`, `composer test:artifacts`, or `composer test:admin-smoke` when the changed surface matches that risk area
- do not include the retired root-managed bridge path in normal package-native release validation; run `composer test:legacy-bridge` only for deliberate archival bridge audits or pre-package-native recovery investigation
- run `composer test:full` before major releases, broad content/admin changes, schema-wide changes, or whenever focused failures indicate broader risk
- if a broad run fails, rerun the specific failing test once to distinguish a real regression from a flaky or unrelated failure, then report the result clearly before proceeding

Documentation-only changes:

- no automated test run is normally required when only Markdown or workflow guidance changes and no executable behavior changes
- a lightweight documentation check such as a careful diff review is acceptable for docs-only edits
- if the documentation changes include commands, paths, or workflow steps, verify those references against the current repository files instead of running unrelated broad test suites

Example commands:

```bash
composer test:release-fast
composer test:package
composer test:update
composer test:install
composer test:artifacts
composer test:admin-smoke
php artisan test --filter=PageBuilderExperienceTest --stop-on-failure
php artisan test --filter=SharedSlotAdminManagementTest --stop-on-failure
php artisan test --filter=PageIndex --stop-on-failure
composer test:full
```

Before creating a release:

1. update `App\Support\WebBlocks::VERSION`
2. ensure the git tag matches that version, for example `v1.0.5`

Never reintroduce `APP_VERSION` into `.env` or `.env.example`.

## AI / Agent Workflow Rules

Agents working in this repository should follow these rules:

- use `AGENTS.md` as the compact working contract before adding task-specific prompt detail
- treat local development as source-first, not updater-first
- assume unreleased code may exist in the working tree
- do not use the admin updater to apply local work
- do not manually bump `system.installed_version` for normal feature work
- do not treat a newer source commit as a released version
- do not use `git pull` as part of the CMS updater flow in development
- use tests, migrations, seeders, route checks, and browser or curl verification to validate local work
- only synchronize development installed-version state when a real release has been created

## Documentation Rule

After every meaningful feature or behavior change, update `README.md` so it reflects current:

- behavior
- setup
- routes
- usage
- admin screens
- commands
- release and update notes where relevant

Do not postpone documentation until much later if the behavior has already changed.

## Safety Checklist Before Tagging

Before creating a release tag, confirm:

- tests pass
- migrations are safe for existing installs
- `README.md` is updated
- `CHANGELOG.md` is updated
- release notes are prepared
- `App\Support\WebBlocks::VERSION` is updated for the release
- the release tag matches `App\Support\WebBlocks::VERSION`
- `composer release:prepare` generated the release artifact and publisher payload
- `composer release:publish-update -- --dry-run` validates artifact, checksum, metadata, endpoint, and token configuration
- release publishing uses package-owned release identity defaults and only `WEBBLOCKS_PUBLISHER_TOKEN` as the normal publish environment secret
- update metadata is compatible with the intended minimum client version
- no local or runtime files are included in the release
- `project/` is treated as an install-local preserved path and is not overwritten by updater package application

## Post-Release Verification

After release tagging and publication, confirm:

- the tag is pushed
- `composer release:publish-update` published the package to the WebBlocks Publisher API
- the update server latest metadata reports the published product, channel, version, checksum, and artifact URL
- an installed test site detects the new release in the CMS update screen
- the development environment `system.installed_version` is synchronized to the release version only after the release is real
