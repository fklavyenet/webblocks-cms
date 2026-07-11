# Package Development

The public repository owns reusable CMS package code, package-native tests, Composer metadata, user documentation, and package CI. A separate maintenance harness owns full Laravel integration, release/Publisher tooling, project and plugin workspaces, Docker/deployment support, and live/operator procedures.

Runtime code lives in `src/`; package configuration, migrations/seeders, assets, views/translations, routes, and stubs live in their matching root directories. Do not introduce dependencies on an outer `artisan`, `app/`, `project/`, `plugins/`, or a nested `packages/webblocks-cms` path.

Package-owned static assets ship under `public/cms`; the package has no Vite, Tailwind, npm, or Node build chain. Schema required by new runtime code must support both fresh installation and package-native update migrations.

Use the Composer scripts documented in [README.md](README.md). CI helper scripts create temporary directories and clean them through traps. They must not contact Publisher, production installations, or external application APIs.

Source checkouts contain contributor files such as tests, CI, and this guide. Composer/Publisher distributions deliberately exclude those source-only files through `.gitattributes`.
