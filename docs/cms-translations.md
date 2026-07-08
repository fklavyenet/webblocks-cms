# CMS Translations Architecture

WebBlocks CMS uses one product translation architecture for CMS-owned interface copy. The same resolver feeds admin UI copy, public system UI copy, and system block defaults. Editorial content translations remain separate in page, block, media, and navigation translation tables.

## Translation Domains

CMS-owned copy is grouped by domain:

- `admin`: CMS control panel labels, actions, headings, help text, modal copy, and admin navigation.
- `public`: visitor-facing CMS system UI such as search modal copy, pagination states, consent/privacy UI, public empty states, and generic controls.
- `blocks`: default system block labels and placeholders, such as Search Form defaults and Contact Form default visitor labels.
- `validation`: CMS-owned validation and user-correctable error messages.

Editorial content stays outside this layer. Page titles, block text, gallery item captions, navigation item labels, and explicit editor overrides continue to be stored in their existing translation-aware content models.

Admin search and listing labels that refer to editorial page identity must read page translation fields such as `name`, `slug`, and `path`; they must not query removed legacy `wbcms_pages.title` or `wbcms_pages.slug` columns. When admin listings render `$page->title` or `$page->slug` across many rows, eager-load page translations so accessors resolve without row-by-row translation queries.

## Locale Sources

Admin and public rendering intentionally resolve locale from different sources:

- Admin UI locale is preference driven. The resolver checks the authenticated user's profile locale first, then the system admin locale, then Laravel app locale, then `en`.
- Public system UI locale is rendered page driven. The resolver should use the public page translation locale, then the resolved public route locale, then the default CMS locale, then `en`.
- Commands, update screens, and background jobs may use the system admin locale or `en` when no user or rendered page locale exists.

This separation lets a German public site render German visitor copy while an operator still uses the admin panel in Turkish or English.

## Package File Catalog

Core product translations live in package files:

```text
packages/webblocks-cms/resources/lang/
  en/
    admin.php
    public.php
    blocks.php
    validation.php
  de/
    admin.php
    public.php
    blocks.php
    validation.php
  tr/
    admin.php
    public.php
    blocks.php
    validation.php
```

Files are the source of truth for product defaults because they are versioned, reviewable, safe to package, and available before database schema is guaranteed ready.

First-party and manually installed plugins may ship their own file catalogs under `resources/lang/{locale}` inside the plugin root. When an installed plugin has that directory, CMS registers it under the plugin handle namespace, such as `webblocks-commerce::admin.settings.title`. Plugin UI should resolve those keys through `CmsTranslator::plugin()` so it keeps the same explicit locale fallback model as core CMS copy and does not require `App::setLocale()`.

The current accepted admin translation audit debt is tracked separately in `packages/webblocks-cms/resources/translation-quality/admin-translation-audit-baseline.json`. That file is a quality-gate baseline, not a translation source. Do not add new records to it for new UI work; move the UI copy to structured translation keys instead.

## Resolver Contract

All CMS-owned copy should go through the CMS translator facade/helper instead of raw hard-coded strings:

```php
cms_trans('admin.pages.title', locale: $adminLocale)
cms_trans('public.search.title', locale: $publicLocale)
cms_trans('blocks.search_form.placeholder', locale: $publicLocale)
```

Fallback order:

1. Exact requested locale, such as `de-DE`.
2. Base language, such as `de`.
3. Configured CMS fallback locale when available.
4. `en`.
5. The translation key itself.

Placeholders use Laravel-style replacement names such as `:site`, `:query`, and `:count`.

Admin Blade files must not introduce new user-visible hard-coded English copy. New headings, descriptions, card titles, empty states, table headers, filters, modal labels, button labels, `aria-label`, `title`, placeholders, confirmation prompts, and status labels must be resolved through structured keys such as `admin.site_transfers.run_export` or shared keys such as `admin.common.actions`.

The legacy admin HTML localization bridge has been removed. Admin responses are no longer post-processed through `LocalizeAdminHtml`, and there is no reviewed `admin.html` fallback phrase map. Non-English admin screens must render from structured translation keys directly.

Use `php artisan webblocks:admin-translation-audit --locale=de --strict` or `--locale=tr --strict` to detect CMS-owned admin Blade copy that still appears as hard-coded user-visible English text. The audit automatically discovers admin Blade files under `packages/webblocks-cms/resources/views/admin/` so newly added admin windows, modals, lists, and partials are included without updating a manual file list. The legacy `--native-only` flag is kept for compatibility and now reports the same native-key readiness check.

Use the strict baseline gate before merging admin UI work:

```bash
composer test:admin-translations
```

This runs the audit for German and Turkish with `packages/webblocks-cms/resources/translation-quality/admin-translation-audit-baseline.json`. Existing debt remains visible, but new missing user-facing phrases outside the baseline fail the command. A passing baseline audit is still not a substitute for route-level render tests on migrated screens; every migrated admin screen should render in at least one non-English admin locale and assert that high-visibility English copy does not leak.

## Database Overrides

Database overrides are a later layer, not the first implementation step. When added, overrides should be additive and explicit:

```text
wbcms_translation_overrides
  key
  locale
  value
  scope
  site_id nullable
```

Override precedence should be:

1. Site-scoped override for public/system copy when rendering a site.
2. Install-scoped override.
3. Package file catalog fallback chain.

Admin overrides should be restricted to super admins and should not be required for normal package updates.

## Migration Strategy

Do not translate the whole product in one broad rewrite. Move surfaces incrementally:

1. Add package translation files and resolver.
2. Migrate public system UI with direct locale context, starting with Search modal and Search Form defaults.
3. Migrate high-visibility admin shell/sidebar/actions after admin locale preference exists.
4. Migrate validation and lower-level admin screens gradually.
5. Add optional database overrides after file-based defaults and tests are stable.

Every migrated surface should have a regression test for locale resolution and fallback. New admin screens should include a non-English render assertion for page headers, card headings, empty states, modal copy, and primary actions.

## Initial Implementation

The first file-based implementation includes:

- `CmsTranslator` with `admin()`, `public()`, and generic `get()` helpers.
- `cms_trans()` as a thin helper around `CmsTranslator::get()` for CMS-owned file keys.
- `CmsTranslator::plugin()` for plugin-owned file keys loaded from installed plugin `resources/lang` directories.
- `AdminLocaleResolver`, backed by user-level `users.admin_locale` preferences with install-wide `admin.locale` fallback.
- Admin shell/sidebar/topbar translation for high-visibility navigation and account/theme actions.
- Admin translation coverage audit command for finding low-coverage admin screens and common missing UI phrases.
- Dashboard and Engagement admin screens for high-visibility operator workflows.
- Auth and password reset screens, auth validation feedback, and CMS password reset email copy.
- Contact Form, Comments, and Rating validation feedback, including block-specific redirect anchors for public correction flows.
- Admin block type picker and Comments/Rating system block editor settings.
- Profile screen language preference for per-user admin panel language.
- Public Search modal, public Search page, header Search action, Search Form system defaults, Contact Form default visitor labels, and Comments/Rating engagement system copy.

The admin locale is intentionally separate from `system.default_locale`; changing an admin panel language preference does not change public site routing or page content language.
