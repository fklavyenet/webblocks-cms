---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/routing-coexistence-review
cms_title: Routing and Coexistence Review
cms_layout: docs
cms_source_id: webblocks-cms:docs/routing-coexistence-review.md
---

# Routing and Coexistence Review

Implementation follow-up: [Public Routing](public-routing.md) documents the 1.89.0 fallback and optional global config mount contract. The findings below describe the pre-change investigation; proposed alternatives and historical coverage gaps are not the current runtime contract.

Review date: 2026-09-30. Status: technical findings and proposed architecture; not an accepted or implemented 2.x contract.

This review examines the package's current routing implementation. Its recommendation is host route precedence plus an optional, instance-wide public mount prefix. Existing installations should retain integrated URLs unless their operator explicitly chooses a prefix. A mandatory `/wb` migration is an alternative, not a requirement established by the code.

No application code, configuration, migrations, routes, or tests were changed during the investigation. Matching experiments used the installed framework in memory. A real consumer application was not booted or route-cached as part of those experiments.

## 1. Current Route Registration

`src/WebBlocksCmsServiceProvider.php::boot()` calls `bootRoutes()` directly. That method loads guarded route files in this order:

1. Install routes.
2. Authentication routes.
3. Diagnostic routes.
4. Admin and Internal Content API routes.
5. Public routes.
6. Enabled plugin admin routes.
7. Enabled plugin API routes.
8. Enabled plugin public and webhook routes.

`loadGuardedRouteFiles()` delegates to Laravel's `loadRoutesFrom()`. Loading depends on package configuration and, for some groups, existing named routes. `packagePublicRoutesShouldLoad()` reads `webblocks-cms.public.load_routes` and checks the global `home` route name, except for its status-route loading case. This is a coarse loading guard, not per-request ownership resolution.

| Source | Surface | Notes |
| --- | --- | --- |
| `routes/install.php` | `/install` | Installation notice; `web` and `install.complete` middleware |
| `routes/auth.php` | `/webadmin/login`, password reset, logout | Package-owned authentication names; guest/auth groups |
| `routes/diagnostics.php` | `/_webblocks-cms/diagnostics/package-status` | Optional diagnostic endpoint |
| `routes/admin.php` | `/webadmin/api` | Discovery and token/capability-protected content APIs |
| `routes/admin.php` | `/webadmin` | Browser administration |
| `routes/admin.php` | `/webadmin/pages/{page}/preview` | Separate group using `AllowPagePreviewAccess` |
| `routes/public.php` | Public content and supporting endpoints | Includes several different ownership categories |
| `PluginRouteRegistrar` | `/webadmin/plugins/...` | Plugin administration and runtime fallback |
| `PluginApiRouteRegistrar` | Plugin APIs | Separate registrar |
| `PluginPublicRouteRegistrar` | `/plugins/{handle}/...` | Public and webhook routes |

`routes/public.php` contains plugin assets under `/cms/plugins`, embedded application files under `/webblocks-applications`, optional runtime status, sitemap and robots endpoints, home and localized home, search and localized search, domain APIs under canonical and legacy API prefixes, contact/rating/comment writes, privacy consent, legacy page redirects, and general page routes. Prefixing this entire file would also move assets and APIs; it is not a safe first implementation.

### Host Application Lifecycle

The installed framework's `Illuminate\Foundation\Configuration\ApplicationBuilder::withRouting()` registers the application route provider during application booting. The framework `RouteServiceProvider::register()` loads host routes through its provider booted callback. CMS loads routes immediately in its own provider's `boot()`.

With the standard Laravel routing/package discovery lifecycle, CMS's general public routes can therefore enter the collection before host routes. Custom hosts can alter registration order; the package currently provides no order-independent ownership guarantee.

CMS also registers an application booted callback, but it protects the redirect-manager plugin catch-all. It does not lower the priority of CMS page routes.

## 2. Catch-All Verification

`routes/public.php` registers `/{locale}/{slug}` and `/{slug}` as ordinary GET routes. `src/Support/Pages/PagePath.php::routePattern()` excludes a fixed list of reserved first segments and ends in `.*`. The `slug` parameter therefore accepts slash-bearing paths, not only one segment.

`src/Models/Locale.php::routePattern()` returns the syntactic pattern `[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,2}`. It does not use the enabled locale records. Even `up` matches this pattern.

An in-memory experiment registered the current home/locale/page patterns in their current order, then added host GET routes. Both Laravel's normal matcher and a Symfony `CompiledUrlMatcher` built from Laravel's `RouteCollection::compile()` selected these routes:

| Request | Normal matcher | Compiled matcher |
| --- | --- | --- |
| `/up` | `localized.home` | `localized.home` |
| `/settings` | `pages.show` | `pages.show` |
| `/de` | `localized.home` | `localized.home` |
| `/en` | `localized.home` | `localized.home` |
| `/products` | `pages.show` | `pages.show` |
| `/products/42` | `pages.show` | `pages.show` |
| `/account/settings` | `pages.show` | `pages.show` |
| `/orders/42/items/7` | `pages.show` | `pages.show` |

The shadowing claim is confirmed when CMS patterns are registered first. `PageController` returns a 404 when content cannot be resolved; Laravel does not then try the host route.

Other broad patterns include `/p/{path}`, localized legacy paths, file routes inside dedicated prefixes, the plugin admin fallback, and an enabled redirect-manager plugin's root catch-all.

Compilation does not repair the demonstrated priority. The experiment verifies matcher behavior, not an end-to-end `artisan route:cache` consumer boot. Both cached and uncached real-consumer tests remain necessary.

## 3. Host Ownership and Resolver Precedence

The desired ownership model is feasible:

```text
Host application ordinary routes
CMS explicitly reserved endpoints
CMS public content resolution
Fallback / 404
```

Registration order alone is weaker than actual fallback semantics. Moving CMS page registration into `app->booted()` can improve standard ordering, but does not guarantee precedence against later route registration or arbitrary host catch-alls.

The installed Laravel `AbstractRouteCollection::matchAgainstRoutes()` postpones routes marked `isFallback` and returns a matching ordinary route first. Its `toSymfonyRouteCollection()` also appends fallback routes after ordinary routes for compilation. This is the strongest existing framework primitive for a lower-priority CMS content resolver.

Two limitations require an explicit integration contract:

- A host `Route::get('/{anything}', ...)` with a broad constraint is an ordinary route, not a Laravel fallback. It wins over a CMS fallback.
- Separate host and CMS fallback routes do not form a resolution chain automatically. Fallback ordering still matters.

The package should not guess which host routes are semantically catch-alls. Hosts should use a documented fallback integration point when they need their own final resolver. CMS should not convert a host controller's 404 into a content lookup, because that changes application error and authorization behavior.

## 4. Locale Paths

`LocaleResolver`, `PageRouteResolver`, and the public `PageController` implement locale resolution. Locale identity is stored separately from the translation's content path. A German translation with `path=/about` produces `/de/about` when German is not the default locale.

The default locale is resolved from global locale/system state. Site relationships determine which locales are enabled. Default-locale public URLs are prefixless; non-default locales receive their locale code as a prefix.

`/de` is a localized-home candidate. If English is the default, `/en` is not generated as a second default-home URL. The resolver rejects an explicit default-locale prefix; `PageController::home()` can subsequently try it as a default-locale content path such as `/en`.

Syntactic matching happens before enabled-locale checks. That is why `/up` can select `localized.home` even though it is not a configured language.

Host precedence protects an explicit host `/de`, but cannot display two different homepages at that same URL. A mount prefix, separate domain, or explicit ownership decision is needed. Owning `/de` alone should not reserve every `/de/...` content path for the host.

## 5. Multisite and Setting Scope

`src/Support/Sites/SiteResolver.php::resolve()` normalizes the request host, checks active `SiteDomain` records, checks legacy `Site.domain`, optionally falls back to the primary site, and otherwise throws an unknown-host 404.

Public route registration is shared and does not create a separate route group per site domain. The site is selected at request resolution time, after route registration.

An instance-wide prefix therefore fits the current architecture naturally. Site-specific prefixes would require domain/prefix route generation or a more involved runtime mount resolver, together with cache invalidation when database domain or prefix settings change.

All sites share the Laravel route collection, although domain-constrained host routes can create different conflicts on different domains. Site-specific prefixes are possible, but introduce complexity that is not necessary for the first implementation.

## 6. Optional Prefix Impact

| Area | Existing implementation or required work |
| --- | --- |
| Registration | Separate content mounts from APIs/assets/supporting endpoints in `public.php` |
| Page URLs | `PageRouteResolver::pathFor()` and `urlFor()` |
| Home/search | `homePath()`, `searchPath()`, `searchJsonPath()` |
| Navigation | Page links use the resolver; custom URLs remain stored strings |
| Internal block links | `Block::localizedPublicUrl()` and resolver parsing |
| Redirects | `legacyRedirectPath()` and plugin redirect integration |
| Canonical/OpenGraph | `PublicPagePresenter` uses the page resolver |
| Hreflang | `resources/views/partials/head-meta.blade.php` uses the resolver |
| Sitemap | `SitemapGenerator` entries and sitemap endpoint URL |
| Robots | Root-domain ownership must be explicit |
| Structured data | Audit URL-bearing custom/schema output; this review does not prove all output is centralized |
| Preview | Keep the admin preview endpoint; update public URLs rendered within it |
| Admin links | `Page::publicUrl()` consumers |
| API output | Audit model serialization and page/render payloads |
| Search | Stored index URLs require regeneration |
| Caches | Sitemap, route cache, and long-lived runtime state |
| Stored content/revisions | Literal relative and absolute links need diagnostics |
| Plugins/forms | Named routes and public endpoint namespace compatibility |

Domain-root `/robots.txt` should not simply become `/wb/robots.txt`. In coexistence, the host should own the root robots response and include the CMS sitemap through a documented integration.

## 7. Existing URL Centralization

A substantial URL abstraction already exists. `src/Models/Page.php` delegates `publicUrl()`, `canonicalUrl()`, and `publicPath()` to `PageRouteResolver`. Page-linked navigation, admin open-page actions, metadata, hreflang, sitemap, page lists, search indexing, and many block links use that path.

Remaining exceptions include custom navigation URLs, author-entered absolute links, raw HTML/rich text, named form routes, `url('/')` fallbacks, request-derived URLs, and string concatenation for the sitemap endpoint.

Preserve `PageRouteResolver`. Add a small public-mount abstraction within or beside it to combine mount, locale, and content paths, and to remove the mount from incoming links before resolution. A second competing page URL generator or wholesale rewrite is unnecessary.

## 8. Admin Settings and Cache Consistency

`SystemSettings` manages allowlisted database key/value settings through `SystemSetting`. `SystemSettingsController` reads and saves them. Package configuration currently supplies route-loading/env settings. No public mount setting exists in the current site or system flow.

| Configuration source | Assessment |
| --- | --- |
| Explicit config override, then DB, then default | Useful panel UX, but effective value and override must be visible |
| Config/env only | Smallest reliable initial design for literal route prefixes |
| DB only | Easy editing, but boot-time availability and route-cache synchronization need management |

A literal route prefix read from the database at boot becomes part of the cached route collection. Saving a new DB value alone can make outgoing URLs use the new prefix while cached incoming routes still expect the old prefix.

Literal-prefix activation requires rebuilding or clearing route cache. Env changes also require config-cache refresh. Long-lived application processes may need reload. A deployment is not inherently required for a DB change under ordinary request-per-boot execution, but coherent cache/runtime activation is.

The current settings update controller does not implement this protocol. `PluginRuntimeRefresher` provides cache-clearing examples, not a routing-settings activation transaction.

Start with config/env only. If panel management is later added, distinguish desired and effective settings and retain the previous effective configuration when activation fails. A fixed fallback that resolves mounts at request time can reduce route-cache coupling, but supporting named public endpoints and host fallback composition still requires design.

## 9. Existing Installation Compatibility

2.x can retain the existing URL format with `mode=integrated` and no prefix. Stored translation paths, locale identity, content, and page relationships can remain unchanged.

Correcting precedence will nevertheless change URLs that previously reached CMS only because CMS shadowed a host route. Preserving URL format does not mean preserving erroneous ownership. Upgrade diagnostics should identify these conflicts.

## 10. Direct 1.x to 2.x Upgrade

Existing mechanisms include Composer discovery, fresh/update migrations, published configuration/assets, the installer, `InstalledVersionStore`, and System Updates/Publisher integration.

`src/Support/System/Updates/UpdateMigrationRunner.php` runs package-native update migrations and deliberately skips host migrations. `InstallWebBlocksCmsCommand::publishPackageConfigIfMissing()` preserves an existing published config unless forced.

A safe direct upgrade should:

- Require the host's deliberate Composer constraint change where `^1` excludes 2.x.
- Default missing new routing keys to integrated behavior.
- Preserve published configuration instead of overwriting it wholesale.
- Use idempotent update migrations if database settings are introduced; config-only routing may require no schema change.
- Check published view overrides and plugin compatibility.
- Update affected assets and rebuild runtime caches.
- Advance persisted version state only after successful update completion.

Public URL migration must not run automatically. A long-lived parallel 1.x maintenance branch is not technically required by this design. Full major-version behavior of Composer constraints, Publisher, and update compatibility remains a release acceptance task; it was not executed end to end in this review.

## 11. Changing a Mount Later

Keep the existing separation:

```text
translation locale: de
stored path: /contact
public mount: /wb
resolved URL: /wb/de/contact
```

Do not write the mount into page slugs or translation paths. `PageRevisionManager` snapshots translation paths and content; storing deployment mounts there would couple revision restore and export/import to installation routing.

Mount changes require search URL regeneration, sitemap invalidation, route/runtime activation, literal-link diagnostics, and an explicitly selected redirect plan. They do not require rewriting page identity.

## 12. Redirect Ownership

Core supports legacy `/p/...` redirects. `PluginRouteRegistrar::protectCorePublicRoutesFromPluginCatchAlls()` protects registered route prefixes from the redirect-manager catch-all and attaches `ServeCmsPageBeforeRedirectCatchAll`.

This protection is specific to that plugin. It does not fix CMS page-route shadowing. Its first-segment reservation is also broader than exact URL ownership: reserving `products` can exclude an entire subtree from plugin redirects.

If `/de` becomes host-owned and CMS moves to `/wb/de`, CMS cannot also redirect `/de`. Redirect planning must examine exact old URLs against method/domain/constraint-aware host matching. Route diagnostics cannot predict a controller's eventual response.

## 13. Installation Experience

The current installer does not ask for integrated versus coexistence use. It attempts to remove an untouched Laravel welcome route, and warns when the host route file is custom.

Two installation choices are feasible:

- Manage the main website: integrated public paths.
- Run beside an existing Laravel product: a prefixed mount such as `/wb`.

Installer diagnostics can inspect registered routes, but a fixed list of `/de`, `/en`, or `/settings` checks is insufficient. Dynamic parameters, regex constraints, domains, methods, future pages, and later host changes all matter.

Provide an initial installer diagnostic plus a repeatable command/admin diagnostic. A clean installation-time result cannot guarantee that future content will never conflict.

## 14. Test Coverage

An important correction from the investigation: `tests/TestCase.php` sets `webblocks-cms.routes.public=false`, but the provider reads `webblocks-cms.public.load_routes`. The old test key does not establish that public routes are disabled. Coverage conclusions must use the actual provider key.

| Scenario | Evidence found |
| --- | --- |
| Host route wins | No direct ownership assertion found in the inspected tests |
| `/de`, `/settings`, `/products/{id}` conflicts | No direct coexistence matcher coverage found |
| Multi-segment host routes | No direct ownership coverage found |
| Public route middleware | `tests/Unit/CmsIdentificationHeaderTest.php` |
| Route cache | `tests/Support/check-current-consumer.sh` creates/clears cache, without endpoint ownership assertions |
| Multisite/domain selection | `tests/Feature/PublicSitemapTest.php` |
| Localized URLs and alternates | `PublicSitemapTest::request_host_selects_the_site_and_localized_urls_share_renderer_alternates()` |
| Sitemap/robots/cache | Published-state filtering, canonical sitemap, pagination and invalidation tests |
| Public prefix | Feature does not currently exist |
| Revisions under mount changes | Existing revision tests do not establish this scenario |

The central missing gate is a real consumer boot, both cached and uncached, that asserts which host/CMS controller or route owns each request.

## 15. Decision Matrix

| Criterion | A: Order only | B: Precedence + global optional prefix | C: Site-specific prefix | D: Mandatory `/wb` | E: Framework fallback + optional global mount |
| --- | --- | --- | --- | --- | --- |
| Host safety | Lifecycle-dependent | Good with an explicit precedence contract | Good, more moving parts | Strong separation; host catch-all limitation remains | Strong for ordinary host routes |
| Multisite | Close to current model | Natural fit | Flexible, more domain/cache complexity | Shared global mount | Natural fit |
| Locale root conflict | Host wins; CMS needs another address | Prefix separates it | Site-dependent separation | Separated | Optional mount separates it |
| Existing installs | URLs stay; conflicting behavior changes | Integrated mode preserves format | Defaults/migration needed | All public addresses change | Integrated mode preserves format |
| URL/SEO impact | Usually unchanged | Changes only on opt-in migration | Per-site changes | Broad migration | Changes only on opt-in migration |
| Complexity | Low, limited guarantee | Moderate | High | Moderate/high URL impact | Moderate; fallback integration matters |
| Route cache | Ordering must be tested | Literal mount needs rebuild | DB/domain synchronization is harder | Fixed mount is straightforward | Fixed fallback works well; endpoint design remains |
| Admin usability | No new setting | Activation workflow needed | More complex | No mount choice | Config first, controlled panel later |
| Maintenance | Lifecycle exceptions remain | Balanced | Higher burden | Simple contract, less flexibility | Framework semantics with documented limits |

## 16. Recommendation

### Current Problem

Ordinary CMS home, locale, and broad page routes can shadow host GET endpoints. Missing content produces a CMS 404 rather than a second route match.

### Root Cause

Boot-time registration of ordinary catch-all routes makes CMS content a normal route competitor. The locale pattern is broad and independent of enabled locale records.

### Recommended 2.x Routing Contract

Use Laravel fallback semantics for integrated content resolution. Preserve `PageRouteResolver`, add an optional public mount, explicitly reserve CMS system endpoints, and document host catch-all/fallback integration. Never resolve CMS content as recovery from a host controller's 404.

### Setting Scope

Instance-wide configuration first. Add panel-backed activation only with effective/desired state and cache/runtime consistency. Defer site-specific prefixes.

### Existing Installations

Default to integrated routing. Preserve content and translation paths. Report actual host conflicts before upgrade.

### URL Migration

Regenerate derived URLs/indexes and invalidate caches when an operator selects a mount. Do not automatically rewrite page paths, content links, or host-owned old URLs, and do not automatically enable a prefix during upgrade.

### Implementation Phases

1. Define ownership and add real consumer/cache regression coverage.
2. Move home/locale/page resolution to a fallback contract.
3. Add a small mount abstraction to the existing resolver.
4. Add optional global config prefix and adapt supporting endpoints/URL consumers.
5. Add index/cache refresh and old-link diagnostics.
6. Rehearse integrated upgrades and an explicit prefixed migration separately.
7. Add controlled panel activation only if needed.

The current code supports an optional mount without mandatory public URL migration. Integrated coexistence still needs the precedence fix; a prefix is an additional isolation tool, not a substitute for correct route ownership.
