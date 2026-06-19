# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

- Document the internal CMS admin/auth UI standards under `ai/standards` and move internal audit notes out of public `docs/`.
- Exclude internal AI audit/worklog paths from release exports while keeping public documentation focused on user and developer guidance.

## 1.32.142

- Harden package `webblocks:install` for existing Laravel hosts by detecting partial CMS schemas before fresh migrations and reporting CMS tables, row counts, migration rows, and known foreign key conflicts.
- Add explicit `webblocks:install --repair-partial` recovery for empty partial CMS tables, while refusing automatic repair for any non-empty CMS table.
- Guard the package fresh-install schema creation and avoid the historical `system_update_runs_triggered_by_user_id_foreign` MySQL constraint-name collision.

## 1.32.141

- Release the Page Converter MVP under Pages with paste/upload HTML analysis, signed conversion plan review, and verified draft-only page creation.
- Support conservative conversion into text/content blocks, code/table/quote/html fallback, button links, callout/alert, section, content header, hero, CTA, explicit card regions/children, and clear `<details>` accordions.
- Keep the MVP non-destructive: no remote fetching, crawling, media import, navigation/shared-slot creation, overwrite, publish, ZIP, or batch import behavior.

## 1.32.140

- Align Export / Import row action cells with the standard compact WebBlocks UI admin table action pattern.

## 1.32.139

- Fix MySQL/MariaDB backup dumps so option-file-sensitive database passwords remain intact when the pre-update backup runs `mysqldump`.

## 1.32.138

- Move the Site Transfer import review form above package counts and show package counts in a compact admin table so validated packages can be acted on sooner.

## 1.32.137

- Remove old Publisher/update server, product, and channel environment overrides from CMS update checks and maintainer publishing.
- Make package-owned `ReleaseDefaults` the only source for the release server, product key, channel, and update/publish API paths.
- Keep `WEBBLOCKS_PUBLISHER_TOKEN` as the only normal publish environment secret.

## 1.32.136

- Move CMS update and publisher identity defaults into package product code so installed sites no longer need normal update server, product, or channel environment keys.
- Keep legacy update and publisher identity overrides available for the transition release, while maintainer publishing normally only requires `WEBBLOCKS_PUBLISHER_TOKEN`.

## 1.32.135

- Add a transition verification release after moving CMS update publishing and installed update consumption to `publisher.webblocksui.com`.
- No functional runtime changes beyond release/version metadata.

## 1.32.134

- Move installed CMS update checks to `publisher.webblocksui.com` so publishing and update consumption use the same canonical Publisher service.
- Keep maintainers publishing to `https://publisher.webblocksui.com/api/updates/publish` while installed sites read latest metadata from `https://publisher.webblocksui.com/api/updates/latest`.

## 1.32.133

- Standardize CMS release publishing on `publisher.webblocksui.com` as the canonical Publisher endpoint.
- Keep the canonical Publisher environment key set as the only supported publish configuration during the transition.

## 1.32.132

- Keep the base admin layout JavaScript minimal by loading only pinned WebBlocks UI and shared CMS admin core globally, with picker, builder, rich-text, gallery, media-copy, page-assets, and password-toggle behavior loaded from page-scoped static admin assets.
