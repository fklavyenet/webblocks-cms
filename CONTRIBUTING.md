# Contributing

Contributions to the WebBlocks CMS package are welcome. By participating, you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

Search [existing issues](https://github.com/fklavyenet/webblocks-cms/issues) before reporting a bug or proposing a feature. Security reports must follow [SECURITY.md](SECURITY.md), never a public issue.

## Setup

Requirements are PHP 8.4, Composer 2, and SQLite support for tests.

```bash
git clone https://github.com/fklavyenet/webblocks-cms.git
cd webblocks-cms
composer install --no-interaction --prefer-dist
composer validate --strict
composer check-platform-reqs
composer format:test
composer test
```

This checkout is a reusable package, not a runnable Laravel application. Use a separate temporary Laravel host when a change needs full consumer verification. Do not add Node, Vite, Tailwind, npm, host application files, project-specific code, plugins, secrets, or generated dependencies to package source.

Keep changes focused, follow the existing two-space PHP style, add tests for behavior changes, and update user documentation when installation or supported behavior changes.

Runtime code lives in `src/`; package configuration, migrations, assets, views, translations, routes, and stubs live in their matching root directories. Package-owned assets ship under `public/cms`. Do not introduce an outer `artisan`, `app/`, `project/`, `plugins/`, nested package path, Node build chain, or dependency on the private maintenance harness. Schema needed by runtime code must support fresh installs and package-native update migrations.

The package CI exercises the source tree, exported distribution, current Laravel consumer, and Laravel `13.0.*` resolution floor. Its reusable checks live under `tests/Support/` and clean their temporary directories.
