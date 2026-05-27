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
6. create and push the real git tag
7. allow GitHub Actions to generate release notes, build the release package, and publish the release after the tag is pushed
8. verify the published release is visible to the update infrastructure when update-server publishing is configured and available
9. only after the release is real, synchronize the dev environment installed version if needed

GitHub Actions owns release note generation and release package creation. There are no local release helper scripts.

## Coding Standards

Use `AGENTS.md` as the compact AI and project working contract for routine implementation work.

Formatting sources of truth:

- `.editorconfig` defines baseline whitespace, line ending, encoding, and indentation rules
- `pint.json` defines the repository PHP formatting rules through Laravel Pint
- `scripts/check-php-indentation.php` enforces the project-specific 2-space PHP indentation rule that Pint does not currently catch

Standard development commands:

- `ddev composer format` applies Pint fixes
- `ddev composer format:test` checks Pint formatting without modifying files and runs the PHP indentation guard
- `ddev artisan test --filter=...`
- `ddev artisan test`

Prefer DDEV-first commands in examples and routine workflows, and keep formatting or standards changes focused instead of mass-reformatting unrelated files.

When AI or automation writes PHP files, verify the actual file contents for 2-space indentation instead of assuming Pint alone proves indentation compliance. The repository-wide formatting baseline is `ddev composer format:test`, which runs Pint for non-indentation style and the custom indentation guard across maintained PHP and Blade roots. Pint's indentation-specific fixers are disabled so `scripts/check-php-indentation.php` remains authoritative for the 2-space PHP indentation rule.

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
- use `ddev composer test:release-fast` as the default gate for small package-native hotfixes
- use `ddev composer test:package`, `ddev composer test:update`, `ddev composer test:install`, `ddev composer test:artifacts`, or `ddev composer test:admin-smoke` when the changed surface matches that risk area
- do not include the retired root-managed bridge path in normal package-native release validation; run `ddev composer test:legacy-bridge` only for deliberate archival bridge audits or pre-package-native recovery investigation
- run `ddev composer test:full` before major releases, broad content/admin changes, schema-wide changes, or whenever focused failures indicate broader risk
- if a broad run fails, rerun the specific failing test once to distinguish a real regression from a flaky or unrelated failure, then report the result clearly before proceeding

Documentation-only changes:

- no automated test run is normally required when only Markdown or workflow guidance changes and no executable behavior changes
- a lightweight documentation check such as a careful diff review is acceptable for docs-only edits
- if the documentation changes include commands, paths, or workflow steps, verify those references against the current repository files instead of running unrelated broad test suites

Example commands:

```bash
ddev composer test:release-fast
ddev composer test:package
ddev composer test:update
ddev composer test:install
ddev composer test:artifacts
ddev composer test:admin-smoke
ddev artisan test --filter=PageBuilderExperienceTest --stop-on-failure
ddev artisan test --filter=SharedSlotAdminManagementTest --stop-on-failure
ddev artisan test --filter=PageIndex --stop-on-failure
ddev composer test:full
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
- update metadata is compatible with the intended minimum client version
- no local or runtime files are included in the release
- `project/` is treated as an install-local preserved path and is not overwritten by updater package application

## Post-Release Verification

After release tagging and publication, confirm:

- the tag is pushed
- the GitHub release workflow completed successfully
- WebBlocks Publisher received the release metadata
- an installed test site detects the new release in the CMS update screen
- the development environment `system.installed_version` is synchronized to the release version only after the release is real
