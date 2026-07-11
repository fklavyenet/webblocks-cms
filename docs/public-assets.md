---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/public-assets
cms_title: Public Assets
cms_layout: docs
cms_source_id: webblocks-cms:docs/public-assets.md
---

# Public Assets

## CMS Core Public Assets

WebBlocks CMS core public assets live under:

- `public/cms/css/`
- `public/cms/js/`
- `public/cms/brand/`

These paths are for CMS-owned runtime behavior and styling that should ship with the product itself.

CMS core assets are static package/runtime assets. The CMS runtime and release package must not require Vite, the Laravel Vite plugin, Tailwind, npm, Node, `public/build`, `public/hot`, package lockfiles, or `@vite` Blade directives. When CMS-owned CSS or JavaScript changes, update the tracked files under `public/cms` and package `packages/webblocks-cms/public/cms` directly.

The `/cms/...` URL namespace is reserved for these static CMS assets. It must not be reused as a CMS admin route prefix, alias, or redirect. The canonical CMS admin namespace is `/webadmin/...`, including `/webadmin/login` when package-owned CMS auth routes are active.

Public page paths are canonical Page Translation paths such as `/contact` or `/docs/internal-content-api`. They must not claim `/cms/...`; that namespace remains static assets only. The old `/p/...` public page shape is a legacy redirect/alias and is not used for new canonical URLs.

This separation protects common Nginx `try_files` deployments. A request to `/cms/` can match the physical `public/cms/` directory before Laravel receives the request, so CMS admin routing must not depend on `/cms`. The final admin-prefix design avoids the collision entirely and does not use a `public/cms/index.php` front-controller handoff; that file must remain absent from both install-root `public/cms/` and package `packages/webblocks-cms/public/cms/` assets.

## Site Handle Convention

Site handles are filesystem-safe identifiers used for site-scoped public asset folders.

- Handles are lowercase.
- Handles are ASCII-safe where possible.
- Spaces, dots, slashes, underscores, and repeated punctuation normalize to a single hyphen.
- Only `a-z`, `0-9`, and `-` remain.
- Repeated hyphens collapse to one, and leading or trailing hyphens are trimmed.
- Example normalizations:
  - `ui.webblocksui.com` -> `ui-webblocksui-com`
  - `WebBlocks UI` -> `webblocks-ui`
  - `Docs Site` -> `docs-site`

Hyphen is the canonical separator for site handles and `public/site/{site_handle}/...` folders.

## Install-Level And Site Override Assets

Install-specific or site-specific public overrides live under the resolved site handle:

- `public/site/{site_handle}/css/site.css`
- `public/site/{site_handle}/js/site.js`

These files are override space for the current install and should not be used for CMS core behavior.

When present, `public/site/{site_handle}/css/site.css` renders in the public `<head>` for the currently resolved public site. The rendered URL includes a content-hash `?v=` query so browser caches refresh after admin or API writes.

When present, `public/site/{site_handle}/js/site.js` renders in the public `<head>` with `defer` for the currently resolved public site. The rendered URL includes a content-hash `?v=` query so browser caches refresh after admin or API writes.

When Site Export / Import runs with file inclusion enabled, these two canonical site-level override files are packaged when present and restored under the final imported site handle. Missing `site.css` or `site.js` files are skipped cleanly. Export / Import does not package arbitrary `public/site/{site_handle}/...` trees through this site-level path.

`public/storage` is separate from `public/site/...`. It is the Laravel public storage symlink created by `storage:link` for files under `storage/app/public`, and should not be treated as CMS asset space or site-override asset space.

The canonical override convention requires a site handle segment. Handle-less site override paths are not canonical and should not be used for new runtime behavior.

`Sites -> Edit Site -> Assets` manages the two canonical site-level override files directly from the CMS admin. Trusted API/operator tools can use `GET /webadmin/api/sites/{site}/assets/{css|js}` with `site-assets.read` and `PUT /webadmin/api/sites/{site}/assets/{css|js}` with `site-assets.write` for the same files. This is a physical-file workflow only: the public renderer still loads `/site/{site_handle}/css/site.css` and `/site/{site_handle}/js/site.js` when those files exist, and CMS does not provide a database fallback route for missing files. The admin and API writers create the missing canonical directories on first save, compare a submitted checksum against the current file before writing, reject stale edits instead of overwriting concurrent changes, and store the previous file contents under `storage/app/cms/site-assets/{site_id}/revisions/{css|js}/` before replacing an existing file.

`site.css` is a site composition layer, not a replacement theme engine. Keep CSS token-first and mode-aware: prefer CMS public theme custom properties, inherited `wb-*` component styling, and semantic site custom properties over raw light/dark colors. Use native block settings and Media Library relationships for content, background media, icon tones, and layout roles before adding CSS. If custom colors are unavoidable, define light and dark values through semantic variables tied to active mode selectors or public theme tokens.

Site asset responses include writable-readiness metadata so admin screens and AI/operator tools can see whether CMS can create or update the physical file before attempting a write. New, updated, and cloned sites attempt to prepare the canonical `css` and `js` directories automatically. When hosting ownership or permissions still prevent directory creation, admin and API writes return controlled validation feedback that names the blocked `public/site/{site_handle}` path instead of returning a raw server error. Operators may still need to fix host-level ownership or group write permissions when PHP cannot write under `public/site`, but this is reported as a setup/readiness issue rather than a broken content operation.

CMS-owned guest and email support styles now live under `public/cms/css/guest.css` and `public/cms/css/email.css`.

CMS-owned product brand assets such as default CMS favicons and compatibility mark files ship from package `public/cms/brand/` and are installed, published, or synced into install-root `public/cms/brand/`. These are product identity assets for the CMS shell, not site-specific Media Library branding and not `public/site/...` overrides. The canonical product brand set is `logo-mark.svg`, `logo-mark-dark.svg`, `logo-mark-on-accent.svg`, `favicon.svg`, `favicon-32x32.png`, `favicon-16x16.png`, and `apple-touch-icon.png`. Package-owned CMS auth screens and the admin sidebar render the mark through an inline SVG component that inherits `currentColor`.

## Page Assets

Page-scoped CSS and JS files can now be referenced relationally from `page_assets`.

- V1 accepts only local `/site/...` paths such as `/site/webblocks-ui/pages/playground/page.css` or `/site/webblocks-ui/pages/playground/page.js`
- Canonical page asset paths are:
  - `public/site/{site_handle}/pages/{page_slug}/page.css`
  - `public/site/{site_handle}/pages/{page_slug}/page.js`
- CSS page assets render in the public `<head>`
- JS page assets render in the public `<head>` with `defer`
- Only the owning public page renders its configured page assets
- Page Assets are not rendered in admin layouts
- Page Assets are stored in `page_assets`, not in `pages.settings`
- When site Export / Import includes media files, referenced `/site/...` physical files are also packaged and restored

## Plugin Public Assets

Enabled plugins may declare public asset contributions through `PluginPublicAsset` registry objects. These hooks are for explicit, attributable plugin assets and are separate from CMS core assets, site override assets, and page-scoped assets.

- plugin asset handles must be dot-namespaced with the plugin handle, such as `analytics-tools.public-css`
- plugin CSS can be contributed to the public `<head>`
- plugin JS can be contributed to the public `<head>` with `defer`, `async`, or `type="module"` where declared
- plugin JS can be contributed to the public body end when late loading is appropriate
- disabled and incompatible plugin assets are not collected or rendered
- plugin assets must still be published by the plugin under a plugin-owned static namespace

The hook does not install plugin packages, publish files, stream assets through Laravel, create remote discovery, or create marketplace behavior.

The internal/operator WebBlocks UI Manager plugin uses a separate CDN artifact convention rather than the public page asset hook. It is not bundled in ordinary CMS installs and is available only after manual ZIP upload and explicit enablement:

- release artifact targets are versioned under `public/cdn/webblocks-ui/{version}/...`
- release manifests are local metadata for prepared artifacts and include SHA-256 checksums
- dry-run publish validates source paths, expected dist files, checksums, manifest consistency, target path safety, and idempotency without writing files
- apply publish writes only to the configured local/project-owned static target after validation passes
- existing files with matching checksums are skipped, while checksum mismatches block the publish
- the workflow does not deploy to an external production CDN or change CMS core WebBlocks UI consumption URLs

## Public Asset Convention

- CSS stays in the public `<head>`
- Named public JS stays in the public `<head>` with `defer`
- Legacy named JS rows stored with `body_end` are still accepted, but public named JS is normalized to `<head defer>` output
- Public block renderers must not emit inline scripts
- CMS-owned public JS belongs under `public/cms/js/`
- CMS-owned public CSS belongs under `public/cms/css/`
- plugin-owned public assets must use plugin-owned handles and static paths, and must be registered through the plugin asset contribution hooks instead of modifying the CMS public layout directly
- Site-level override JS belongs under `public/site/{site_handle}/js/site.js`
- Site-level override CSS belongs under `public/site/{site_handle}/css/site.css`
- The public page shell owns the single shared `#wb-overlay-root.wb-overlay-root` mount for shipped WebBlocks UI modal-backed behaviors such as gallery viewers and the public search modal
- Public partials and trusted HTML content must contribute overlay children to that canonical root instead of rendering competing roots such as `#wb-public-overlay-root`, `#public-overlay-root`, or `#overlay-root`
- The shared public dialog layer must not be rendered with `hidden`; WebBlocks UI `v2.7.18` reuses that layer for modal, gallery viewer, and toast targets and only toggles visibility on the active backdrop/modal or toast state, not on a reused layer wrapper
- `public/cms/css/public.css` is the CMS-owned public stylesheet for structural public rendering tweaks, public icon tone hooks, and site-level Public Theme preset token mappings. It is loaded after WebBlocks UI CSS and before site-level override CSS, so package-owned preset tokens apply by default while operator-owned site CSS can still override site-specific presentation when deliberately added.
- CMS core only ships public JS when WebBlocks UI does not already cover the behavior; `public-search-modal.js` remains CMS-owned, while Header Actions mode behavior relies on shipped WebBlocks UI `data-wb-*` behavior without an extra CMS runtime. Public preset/accent controls stay suppressed while site-level Public Theme presets own public theme selection.

## Site Branding Assets

Public favicon and social sharing artwork are now selected from the shared Media library on each Site.

- favicon output uses the current resolved site's `favicon_media_id` when that media item has a public URL
- Open Graph fallback imagery uses the current resolved site's `social_image_media_id` when available
- these are site-scoped metadata assets, not CMS product-brand assets
- page translation `og_image_media_id` can override the site social image for one locale when that media item has a public URL
- page-level SEO assets affect public metadata only and do not change CMS admin branding
- install-level Project Identity does not affect public favicon, public metadata, or site-scoped social sharing assets

## WebBlocks UI Assets

WebBlocks UI assets remain loaded from CDN in the CMS public layout.

CMS-owned default CDN references are pinned to WebBlocks UI `v2.7.18` for the public and admin runtime CSS, icons CSS, runtime JS, the default icon manifest sync source, and the WebBlocks UI AI contract referenced by repo-local AI instructions. Production layout output uses the canonical jsDelivr tag URL format with the standard dist artifacts `webblocks-ui.css`, `webblocks-icons.css`, and `webblocks-ui.js` while minification hardening is deferred. Browser-facing CSS and JavaScript must not use `raw.githubusercontent.com` fallbacks because Chrome can block those responses through ORB or MIME handling.

Those CDN assets are part of the UI project and must not be edited or compiled inside the CMS repository. When installed on an operator site, WebBlocks UI Manager records release, artifact, manifest, checksum, and publish-run metadata as plugin-owned behavior and can publish validated files into a configured local/project-owned static CDN target. CMS core still consumes the existing pinned CDN URLs until a separate explicit migration changes that behavior.

## Admin JavaScript Scope

The package admin layout keeps global JavaScript intentionally small: pinned WebBlocks UI `webblocks-ui.js` plus the shared CMS admin core asset at `public/cms/js/admin/core.js`. Feature-specific admin behavior must be loaded through the `admin-scripts` stack by the view or partial that renders the matching DOM hooks.

Examples of page-scoped feature assets include asset picker panels, media copy buttons, sortable builder rows, inline and structured builder editors, page-builder modals, slot block delete modals, page slot source modals, Edit Page asset controls, Gallery item editing, Rich Text editing, and admin password visibility toggles. These remain CMS-owned static files under `public/cms/js/admin/` with matching package source copies under `packages/webblocks-cms/public/cms/js/admin/` where applicable. CMS admin assets do not use Vite, npm, Tailwind, `public/build`, hot files, or any frontend build chain.
