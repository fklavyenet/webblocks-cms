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
php tests/Support/check-node-dependencies.php
composer check-platform-reqs
composer format:test
composer test
```

This checkout is a reusable package, not a runnable Laravel application. Use a separate temporary Laravel host when a change needs full consumer verification. Do not add Node, Vite, Tailwind, npm, host application files, project-specific code, plugins, secrets, or generated dependencies to package source.

Keep changes focused, follow the existing two-space PHP style, add tests for behavior changes, and update user documentation in the independent `webblocks-cms-docs` repository when installation or supported behavior changes. User documentation and documentation assets do not belong in this package. The runtime AI authoring inventory is a product contract in `resources/contracts/inventory.md`.

Runtime code lives in `src/`; package configuration, migrations, assets, views, translations, routes, and stubs live in their matching root directories. Package-owned assets ship under `public/cms`. Do not introduce an outer `artisan`, `app/`, `project/`, `plugins/`, nested package path, Node build chain, or dependency on the private maintenance harness. Schema needed by runtime code must support fresh installs and package-native update migrations.

The package CI exercises the source tree, exported distribution, current Laravel consumer, and Laravel `13.0.*` resolution floor. Its reusable checks live under `tests/Support/` and clean their temporary directories.

PHPUnit checks the rendered System Updates screen and serves its shared progress asset through the real route. These checks run in CI and the local pre-push hook without Node.js. They do not execute browser JavaScript.

The standalone PHP Node/npm guard runs before the long tests in pre-push and CI, without Composer dependencies. It checks Git-tracked and non-ignored untracked files for package manifests/lockfiles, Node configuration, CommonJS tools, literal Node/npm commands, declared CI/container runtimes and Node/bare-package JavaScript imports. It ignores installed Git-ignored dependencies and prose/comments; native browser JavaScript, relative/URL module imports and package exclusion lists remain valid. Findings name files and lines without printing command arguments. This is a static dependency guard, not an interpreter for dynamically constructed commands.

## Optional browser fixtures

The browser fixtures use PHP's built-in server and the real Blade fragments exported by their PHP tests. From the package root, start one fixture:

```sh
php -S 127.0.0.1:8089 tests/Browser/parent-block-fixture.php
php -S 127.0.0.1:8089 tests/Browser/asset-picker-fixture.php
php -S 127.0.0.1:8089 tests/Browser/rich-text-editor.php
```

Open `http://127.0.0.1:8089/` in a new tab of the already-open Chrome. These tools never start a browser process. Each reload regenerates synthetic fixture data through PHPUnit; temporary exports are removed after rendering. The routers serve only fixture assets and synthetic picker thumbnails, and reject form submissions. Stop the server with Ctrl-C and close only the test tab when done. Node.js, npm and Playwright are not required.

PHP tests cover localized parent choices/order/selection in full and fragment editors, inert picker rows and initial media selections, rich-text field/form ownership, shared dialogs, toolbar controls and server-side rich-text sanitization. They do not execute browser JavaScript. Preserve the following browser checks during an authorized visual QA session:

- Parent choices: repeated branch labels, hierarchy and the current parent remain identifiable in the Block Info tab.
- Media picker: note the scroll position, materialized row count and image request count; search, replace the primary image, then add/remove gallery items and reopen the picker to check retained selections.
- Rich text: paste headings, paragraphs, nested lists and table cells; confirm readable copy and safe markup. Check keyboard and toolbar undo/redo after blur, code formatting and selection restoration, link create/edit/remove, grouped typing, redo branching and IME composition.
- Rich-text focus mode: check word count, nested link dialogs, form submission values, Escape returning to the host modal, independent fields, mobile layout/overflow and browser errors.

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
