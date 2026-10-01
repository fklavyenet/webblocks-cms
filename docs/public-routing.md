---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/public-routing
cms_title: Public Routing
cms_layout: docs
cms_source_id: webblocks-cms:docs/public-routing.md
---

# Public Routing

Since 1.89.0, CMS homepage, localized homepage, page, and legacy page routes use Laravel's real fallback semantics. Ordinary host application routes win over CMS content regardless of registration order. A CMS installation must not replace the host's health endpoint, product routes, or settings screens.

## Integrated Mode

The default is integrated routing, including when an older published config has no mount key:

```text
/
/about
/de
/de/about
/blog/article
```

A matching ordinary host GET route owns that request. For example, a host `/de` homepage wins over the CMS German homepage, while `/de/about` can still resolve CMS content if no ordinary host route matches it. An unrestricted host `/products/{id}` also matches `/products/webblocks-cms`; apply an appropriate host parameter constraint if that address should remain CMS content.

A host controller's 404 is final. CMS does not retry that request as a page lookup.

## Mounted Mode

Set the deployment environment value:

```dotenv
WEBBLOCKS_CMS_PUBLIC_MOUNT=wb
```

The corresponding config key is `webblocks-cms.public.mount`. It is global to the CMS instance, shared by every site domain, and is neither a Site setting nor an editable System Settings value. No panel routing screen or database setting is involved.

```text
/wb
/wb/about
/wb/de
/wb/de/about
/wb/blog/article
```

Use another safe lowercase path, such as `content` or `docs/site`, if desired. Leading/trailing slashes and outer whitespace normalize to the same mount: `wb`, `/wb`, `wb/`, and ` /wb/ ` are equivalent. Null, empty, and slash-only values select integrated mode. Unsafe paths, query/fragment syntax, and reserved endpoint prefixes fail configuration validation instead of silently changing ownership.

The default locale stays prefixless inside the mount. With English as default, the English page is `/wb/about`, not `/wb/en/about`. Site and enabled-locale selection continue to happen from the hostname and locale at request time.

## Endpoint Ownership

| Surface | Integrated | Mounted with `wb` |
| --- | --- | --- |
| Homepage and public pages | `/`, `/about`, `/de/about` | `/wb`, `/wb/about`, `/wb/de/about` |
| Search and JSON search | `/search`, `/de/search.json` | `/wb/search`, `/wb/de/search.json` |
| Sitemap and pagination | `/sitemap.xml`, `/sitemap/1.xml` | `/wb/sitemap.xml`, `/wb/sitemap/1.xml` |
| Legacy page redirects | `/p/about`, `/de/p/about` | `/wb/p/about`, `/wb/de/p/about` |
| Robots | CMS `/robots.txt` | Host owns root `/robots.txt`; CMS does not register `/wb/robots.txt` |
| Browser admin and preview | `/webadmin/...` | Unchanged |
| Internal and legacy domain APIs | `/webadmin/api/...`, `/admin-api/...` | Unchanged |
| CMS/plugin assets | `/cms/...` | Unchanged |
| Embedded application files | `/webblocks-applications/...` | Unchanged |
| Contact, rating, comments, consent | Existing root endpoints | Unchanged |
| Plugin public/webhook endpoints | `/plugins/{handle}/...` | Unchanged |
| Install and runtime diagnostics | Existing dedicated endpoints | Unchanged |

Search and sitemap remain explicitly owned CMS endpoints; content and legacy page routes are fallbacks. Reserve the chosen mount and the fixed CMS endpoint areas in the host's routing contract. In mounted mode, include the CMS sitemap URL in the host's root robots response when indexing is wanted.

Legacy redirects apply only inside the active content mount. Enabling a mount does not add automatic root `/p/...` or old-page redirects that could claim host addresses.

## Host Catch-All and Fallback Limits

A host normal catch-all is an ordinary route and wins over CMS fallback, including inside a configured mount. CMS does not heuristically reclassify it. Constrain that host route to leave the CMS area available.

Two independent Laravel fallback routes do not automatically chain. A host that needs a separate final fallback must deliberately compose its routing with CMS resolution or choose non-overlapping route constraints. A CMS fallback can return 404; Laravel does not then try another fallback.

Named page routes are retained where possible. If an ordinary host route already uses a CMS page fallback name, the CMS fallback receives `webblocks.public.` before that name so route caching does not contain duplicate names. In mounted mode with a host route named `home`, CMS home is `webblocks.public.home`. Use `Page::publicUrl()`, `publicPath()`, or `PageRouteResolver` for content links rather than assuming the global `home` name belongs to CMS.

## Content Identity and URL Generation

The mount is deployment information, not content identity:

```text
locale = de
translation path = /contact
mount = wb
public URL = /wb/de/contact
```

Translation paths, revision snapshots, and site export/import paths do not acquire the mount. `PageRouteResolver` remains the URL authority for page links, metadata, canonical/OpenGraph, hreflang, sitemap, search, and preview/public render links.

Page-linked navigation adapts automatically. Custom navigation URLs and absolute links in rich text are not migrated. Existing block links that already resolve through CMS's localized page-link resolver follow that resolver's mount-aware output; their stored values do not change. Links to host endpoints remain host links when they do not resolve CMS content.

## Deployment and Migration

Changing the mount is a public URL migration. Back up and inventory the site's links, integrations, and indexed URLs first. Rebuild Laravel config and route caches using the new deployment configuration and reload long-lived application processes where applicable. Do not update only URL generation while leaving old cached routes active.

Rebuild stored search URLs after the deployment change:

```bash
php artisan search:rebuild
```

Search results also resolve their outgoing URLs from page identity so an old stored index URL does not leak the previous mount. Rebuilding keeps URL-based search matching aligned with the new addresses. Sitemap cache fingerprints include the effective public origin/mount.

Select old-to-new redirects explicitly. An old CMS `/de` address cannot also redirect when the host now owns `/de`. This release does not automatically rewrite stored content or install a root redirect catch-all.

Mounted installation leaves the host's default Laravel welcome route intact. Integrated installation retains the existing narrowly scoped untouched-welcome cleanup.

## Validation

`tests/Feature/PublicRouteOwnershipTest.php`, the feature/unit `PublicMountTest` classes, and the existing sitemap tests cover package behavior. `tests/Support/check-current-consumer.sh` additionally seeds public content into a real installed Laravel consumer and checks HTTP ownership in integrated and mounted modes, before and after `artisan route:cache`. The consumer includes Laravel's actual `/up` health route and a host homepage named `home`.

The earlier [Routing and Coexistence Review](routing-coexistence-review.md) remains a historical investigation and decision matrix; this document describes the implemented contract.
