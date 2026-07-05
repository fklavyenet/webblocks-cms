# CMS Translations Architecture

WebBlocks CMS uses one product translation architecture for CMS-owned interface copy. The same resolver feeds admin UI copy, public system UI copy, and system block defaults. Editorial content translations remain separate in page, block, media, and navigation translation tables.

## Translation Domains

CMS-owned copy is grouped by domain:

- `admin`: CMS control panel labels, actions, headings, help text, modal copy, and admin navigation.
- `public`: visitor-facing CMS system UI such as search modal copy, pagination states, consent/privacy UI, public empty states, and generic controls.
- `blocks`: default system block labels and placeholders, such as Search Form defaults and Contact Form default visitor labels.
- `validation`: CMS-owned validation and user-correctable error messages.

Editorial content stays outside this layer. Page titles, block text, gallery item captions, navigation item labels, and explicit editor overrides continue to be stored in their existing translation-aware content models.

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

## Resolver Contract

All CMS-owned copy should go through the CMS translator facade/helper instead of raw hard-coded strings once a surface is migrated:

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

Every migrated surface should have a regression test for locale resolution and fallback.

## Initial Implementation

The first file-based implementation includes:

- `CmsTranslator` with `admin()`, `public()`, and generic `get()` helpers.
- `AdminLocaleResolver`, backed by user-level `users.admin_locale` preferences with install-wide `admin.locale` fallback.
- Admin shell/sidebar/topbar translation for high-visibility navigation and account/theme actions.
- Profile screen language preference for per-user admin panel language.
- Public Search modal, public Search page, header Search action, Search Form system defaults, and Contact Form default visitor labels.

The admin locale is intentionally separate from `system.default_locale`; changing an admin panel language preference does not change public site routing or page content language.
