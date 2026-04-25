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

## Release Synchronization Rule

The development environment version must only be synchronized when an actual release is created.

That means the correct time to align local development with a release version is when:

- the release is prepared
- the version stored in config or code is updated
- the release tag is created and pushed
- the release becomes real through the published release flow

Only then may the local development install be synchronized to that released version.

## Recommended Release Flow

Recommended sequence:

1. finish the feature or fix in source
2. run tests and required verification steps locally
3. update `README.md` for any meaningful behavior, setup, route, usage, admin, command, or release-note change
4. update `CHANGELOG.md`
5. prepare release notes
6. update `App\Support\WebBlocks::VERSION`
7. create and push the real git tag
8. allow GitHub release and package publication to complete
9. verify the published release is visible to the update infrastructure
10. only after the release is real, synchronize the dev environment installed version if needed

Before creating a release:

1. update `App\Support\WebBlocks::VERSION`
2. ensure the git tag matches that version, for example `v1.0.4`

Never reintroduce `APP_VERSION` into `.env` or `.env.example`.

## AI / Agent Workflow Rules

Agents working in this repository should follow these rules:

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

## Post-Release Verification

After release tagging and publication, confirm:

- the tag is pushed
- the GitHub release workflow completed successfully
- WebBlocks Publisher received the release metadata
- an installed test site detects the new release in the CMS update screen
- the development environment `system.installed_version` is synchronized to the release version only after the release is real
