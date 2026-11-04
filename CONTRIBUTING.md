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

Ordinary SQLite in-memory tests reuse immutable fresh-schema fixtures within one PHP process, keyed by complete configuration, loaded providers and the migration/runtime/dependency source fingerprint. A bounded 32-entry pool restores schema and seed rows into a separate connection for every case; no transaction wrapper is added. Custom package-provider overrides and migration listeners, migration/restore opt-outs, bridge schemas, nonempty databases, other engines and `RefreshDatabase` retain real migrations. Migration and restore tests set `protected bool $cacheFreshDatabaseSchema = false`. Set `WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_SCOPE=class` for the former class-local cache boundary, or `WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_CACHE=0` for fully uncached setup. Process isolation remains independent; no disk fixture or database connection is shared.

Set `WEBBLOCKS_CMS_TEST_PROFILE` to a local output filename to append JSONL timings without changing the test selection. The records contain test identifiers, process IDs, durations and configuration fingerprints, never configuration values. `full_setup`, `test_body` and `full_teardown` bracket the inherited lifecycle including subclass hooks; `setup` and `teardown` measure the shared Testbench portion. Migration misses, bypasses, snapshot capture and hits are nested measurements, not additional wall time. Use a fresh output filename for each run and compare the same tests with `WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_CACHE=0`. Profiling and its output are test-only and excluded from distribution.

The standalone PHP Node/npm guard runs before the long tests in pre-push and CI, without Composer dependencies. It checks Git-tracked and non-ignored untracked files for package manifests/lockfiles, Node configuration, CommonJS tools, literal Node/npm commands, declared CI/container runtimes and Node/bare-package JavaScript imports. It ignores installed Git-ignored dependencies and prose/comments; native browser JavaScript, relative/URL module imports and package exclusion lists remain valid. Findings name files and lines without printing command arguments. This is a static dependency guard, not an interpreter for dynamically constructed commands.

## Admin form composition

Admin templates must not use HTML `fieldset` or `legend`. Use WebBlocks UI cards
or a `role="group"` container with a visible heading referenced by
`aria-labelledby`. Multi-section workspaces use the shipped `wb-tabs` /
`data-wb-tabs` contract, with separate information, settings and action panels
where useful. Validation and action redirects must retain the relevant panel;
links to settings must open the panel containing their destination.

Admin status, progress and metadata summaries must not use a full-width vertical
stack that places each label and value on separate lines with large gaps. This
includes unstyled or widely spaced `dl` / `dt` / `dd` lists and equivalent card,
paragraph or grid compositions. Short records must remain compact and easy to
scan. Prefer native WebBlocks UI tables: row headings and values for one record,
column headings for repeated records. A compact status/action strip, progress
indicator or aligned label/value rows may be used when they better fit the task.
Cards may group a section; do not turn every property into a separate card.
On narrow screens, keep each label adjacent to its value and preserve semantic
headings. This is a presentation rule, not a blanket ban on semantic `dl` markup.
Put long technical identifiers in an optional details area; preserve access to
the full value without forcing horizontal page overflow. Review existing
summaries against this rule whenever their screen is changed.

CMS and first-party plugin admin feedback uses persistent native `wb-alert`
messages in page content or next to the action. Do not use toast notifications or
hide feedback automatically. Success and failure must remain visible and be
announced with an appropriate status/alert live region.

Multi-step manual work uses the shared native `wb-modal` progress dialog: explain
that the tab must remain open, show truthful counts (indeterminate until known),
a compact summary and elapsed time for this screen. Keep completion/errors until
an explicit close or results link. Native close controls, Escape and backdrop
requests pause at a step boundary; confirm the saved pause outcome before closing.
A connection error must stop further requests without inventing a successful pause.
Progress GET requests are passive and authorized independently.

Release push runs the same pre-push gates once for explicitly named branch/tag refs and verifies both remotes. Its Composer alias disables only the outer 300-second Composer timeout; a PHP runner bounds the complete push/verification flow to 900 seconds. Default SSH transport uses keepalive while the full suite runs; explicit operator `GIT_SSH_COMMAND` or `GIT_SSH` settings are preserved. This does not skip checks or change global Composer/SSH configuration.

The package compatibility fixture uses a disposable Git index and object directory with the checkout's immutable objects as read-only alternates. It includes reviewed untracked runtime sources and tracked edits/deletions, builds the real archive and exercises the installed bridge updater. It does not copy the entire checkout or change its index, object store, branches or tags. Distinct Composer and Git export checks are retained.

## Optional browser fixtures

The browser fixtures use PHP's built-in server and the real Blade fragments exported by their PHP tests. From the package root, start one fixture:

```sh
php -S 127.0.0.1:8089 tests/Browser/parent-block-fixture.php
php -S 127.0.0.1:8089 tests/Browser/dirty-overlay-fixture.php
php -S 127.0.0.1:8089 tests/Browser/editor-save.php
php -S 127.0.0.1:8089 tests/Browser/asset-picker-fixture.php
php -S 127.0.0.1:8089 tests/Browser/rich-text-editor.php
php -S 127.0.0.1:8089 tests/Browser/update-progress-fixture.php
php -S 127.0.0.1:8089 tests/Browser/consent-fixture.php
php -S 127.0.0.1:8089 tests/Browser/consent-admin-fixture.php
php -S 127.0.0.1:8089 tests/Browser/system-picker-fixture.php
php -S 127.0.0.1:8089 tests/Browser/two-factor-policy-fixture.php
```

Open `http://127.0.0.1:8089/` in a new tab of the already-open Chrome. These tools never start a browser process. Each reload regenerates synthetic fixture data through PHPUnit; temporary exports are removed after rendering. The routers serve only fixture assets and synthetic picker thumbnails, and reject form submissions. Stop the server with Ctrl-C and close only the test tab when done. Node.js, npm and Playwright are not required.

PHP tests cover localized parent choices/order/selection in full and fragment editors, inert picker rows and initial media selections, rich-text field/form ownership, shared dialogs, toolbar controls and server-side rich-text sanitization. They do not execute browser JavaScript. Preserve the following browser checks during an authorized visual QA session:

The update-progress fixture uses the real rendered modal, shared progress script and bundled WebBlocks UI. It intercepts all update/status requests with synthetic responses; its read-only server rejects mutations. The default case tests delayed server admission, monotonic elapsed time, fixed modal height, unavailable/foreign status, interruption/recheck and verified completion. Query cases `resume`, `client`, `legacy`, `auth` and `rejected` cover an existing operation, client-owned admission, older product views and admission failures. Each case reports PASS or FAIL on the page. These browser checks are optional and separate from the PHP-only CI suite.

The consent fixtures use real public/admin fragments and the shipped UI. Public cases cover first visit, `?choice=accepted`, `?choice=rejected`, and `?choice=custom&storage-blocked=1`, with local synthetic embeds/scripts and intercepted consent writes. They check reopening without consent changes, saved and unsaved preferences, one-time content loads, withdrawal, failure handling and script reload requests. The admin fixture checks tabs, retained panel state, cookie rows and the service form action without submitting a mutation. Its `/general`, `/texts`, `/general-edit` and `/texts-edit` cases cover shared filter/list cards, icon actions, effective translations and dirty-close protection for individual field editors. Both report PASS/FAIL. The public fixture also covers the native Cookie Settings block, generated menu URL and explicit `?direct=1`/`#cookie-settings` entries. The System picker fixture exports the real picker with synthetic catalog data for tab, eligibility and badge checks.

The two-factor-policy fixture renders both real Security cards in the bundled admin shell and intercepts email-code requests with synthetic responses. It checks 5/7 columns from 1200 px, full-width stacking below that breakpoint, independent form ownership, horizontal overflow, delivery, duplicate prevention, resend cooldown, offline/session/mail/throttle errors and retry while retaining the password, code and selected roles without navigation or a policy submission. It sends no email and changes no live account.

The editor-save fixture renders the actual page settings, existing and new Rich Text blocks, new pages, and translation/SEO forms and executes the recovery/save script with controlled transport responses. It checks offline/timeout, 401/403/419, validation, server and malformed-success failures, retained values and retry controls, refreshed CSRF, typing during checkpoint requests, duplicate Save, explicit empty galleries and paused recovery. It also checks completed-save retry without duplicate creation, extra edits after a lost receipt, fresh creation intents, malformed context receipts and context changes during pending recovery. It performs no CMS writes and reports PASS/FAIL in the existing Chrome.

The dirty-overlay fixture checks the real close guard on a rendered block form: untouched forms, asynchronous recovery metadata, tab changes, real edits, fragment reinitialization, hidden content fields and reverted edits. It reports PASS or FAIL without saving content or contacting recovery endpoints.

- Parent choices: repeated branch labels, hierarchy and the current parent remain identifiable in the Block Info tab.
- Media picker: note the scroll position, materialized row count and image request count; search, replace the primary image, then add/remove gallery items and reopen the picker to check retained selections.
- Rich text: paste headings, paragraphs, nested lists and table cells; confirm readable copy and safe markup. Check keyboard and toolbar undo/redo after blur, code formatting and selection restoration, link create/edit/remove, grouped typing, redo branching and IME composition.
- Rich-text focus mode: check word count, nested link dialogs, form submission values, Escape returning to the host modal, independent fields, mobile layout/overflow and browser errors.

## Plugin public screen acceptance

Visitor-facing plugin System Pages must start with meaningful, translated native CMS
blocks declared through `PluginDefinition::systemPages` in PHP. Preparing a design
must create ordinary editable block/translation rows. JSON templates, encoded JSON
content and a separate plugin-owned static page-content store are not accepted.
Preserve the site's existing page editor, layouts, Shared Slots, revisions and
publication workflow. An empty technical preview placeholder alone is insufficient.

Acceptance checks must cover first preparation, meaningful read-only preview,
editing the native blocks, explicit publication, repeated preparation without
replacement, and preservation of existing designs. Keep record-specific forms,
signed links, authorization and transaction state in feature controllers; never run
those actions or expose live customer/payment/token data for a design preview.
Do not repeat immutable static headings after the editable Main content. Confirm
fallback rendering on compatible older CMS and disabled/unconfigured designs.
See the canonical plugin development guide for the recipe contract.

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


For scheduled features, provide an explicit manual panel path wherever the work
can be safely bounded. A normal manual installation must work without cron or a
queue worker. Start sequential POST steps only after an authorized operator
request; keep committed progress when navigation stops further requests. Avoid
web subprocesses that install operating-system services. Optional independent
execution needs feature-specific hosting instructions and actual runtime
verification. Preserve existing consented billing and saved policies; never
silently enable new destructive or sending automation. Mark unsupported large
workloads clearly and provide the existing CLI/hosting path.
