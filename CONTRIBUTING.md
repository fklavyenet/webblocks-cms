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

Keep changes focused, follow the existing two-space PHP style, add tests for behavior changes, and update user documentation in the independent `webblocks-cms-docs` repository when installation or supported behavior changes. User documentation and documentation assets do not belong in this package. The runtime AI authoring inventory is a product contract in `resources/contracts/inventory.md`.

Runtime code lives in `src/`; package configuration, migrations, assets, views, translations, routes, and stubs live in their matching root directories. Package-owned assets ship under `public/cms`. Do not introduce an outer `artisan`, `app/`, `project/`, `plugins/`, nested package path, Node build chain, or dependency on the private maintenance harness. Schema needed by runtime code must support fresh installs and package-native update migrations.

The package CI exercises the source tree, exported distribution, current Laravel consumer, and Laravel `13.0.*` resolution floor. Its reusable checks live under `tests/Support/` and clean their temporary directories.

## Runtime inventory review

`resources/contracts/inventory.md` is the product-owned source of truth for AI authoring. Update it in the same product change as supported block fields, variants, enums, media, children, render behavior, editor behavior, API permissions, or plugin lifecycle changes. The copy in the documentation repository is a generated version snapshot; never fix product behavior by editing that copy first.

The review record fingerprints runtime sources, schemas, views, translations, and public CMS assets. Added, removed, or changed files invalidate it, as does a product version change. `composer test:docs` checks this record in CI and pre-push. When the documentation checkout is available, pre-push also verifies its snapshot against this product. Release preparation requires both checks before building; set `WEBBLOCKS_CMS_DOCS_ROOT` for a different documentation checkout. The independent artifact builder validates the selected Git tree. Installed packages and isolated product CI do not require the documentation repository.

1. Inspect the source diff, update affected prose and supported values, and run focused behavior tests.
2. Record the completed review explicitly:

   ```sh
   composer inventory:review -- --reviewed --note="Describe the reviewed authoring changes"
   composer test:inventory
   ```

3. If the monitored code changed but the contract remains accurate, an unchanged inventory is accepted only with an explicit reviewed explanation: add `--no-authoring-impact="Explain why the existing contract remains accurate"`. Cosmetic fixes and unrelated runtime changes still need this decision; do not refresh checksums automatically in CI or release scripts.
4. Refresh and check the generated snapshot using the documentation repository's `tools/inventory-snapshot.php`. Commit documentation separately, with its product version and content fingerprints. Keep public CMS publication as a separately approved operation.

The mechanical snapshot also records the published catalog, child rules, renderer-root ownership, API write policy, and mobile-media support from the real product helpers. PHPUnit compares it against those helpers. Fingerprints require a new review; they do not prove the meaning of prose. The contributor and reviewer remain responsible for factual descriptions and review explanations. Keep secrets and machine-specific paths out of review notes.
