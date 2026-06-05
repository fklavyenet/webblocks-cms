# Public Assets

## CMS Core Public Assets

WebBlocks CMS core public assets live under:

- `public/cms/css/`
- `public/cms/js/`
- `public/cms/brand/`

These paths are for CMS-owned runtime behavior and styling that should ship with the product itself.

CMS core assets are static package/runtime assets. The CMS runtime and release package must not require Vite, the Laravel Vite plugin, Tailwind, npm, Node, `public/build`, `public/hot`, package lockfiles, or `@vite` Blade directives. When CMS-owned CSS or JavaScript changes, update the tracked files under `public/cms` and package `packages/webblocks-cms/public/cms` directly.

The `/cms/...` URL namespace is reserved for these static CMS assets. It must not be reused as a CMS admin route prefix, alias, or redirect. The canonical CMS admin namespace is `/webadmin/...`, including `/webadmin/login` when package-owned CMS auth routes are active.

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

When present, `public/site/{site_handle}/css/site.css` renders in the public `<head>` for the currently resolved public site.

When present, `public/site/{site_handle}/js/site.js` renders in the public `<head>` with `defer` for the currently resolved public site.

When Site Export / Import runs with file inclusion enabled, these two canonical site-level override files are packaged when present and restored under the final imported site handle. Missing `site.css` or `site.js` files are skipped cleanly. Export / Import does not package arbitrary `public/site/{site_handle}/...` trees through this site-level path.

`public/storage` is separate from `public/site/...`. It is the Laravel public storage symlink created by `storage:link` for files under `storage/app/public`, and should not be treated as CMS asset space or site-override asset space.

The canonical override convention requires a site handle segment. Handle-less site override paths are not canonical and should not be used for new runtime behavior.

CMS-owned guest and email support styles now live under `public/cms/css/guest.css` and `public/cms/css/email.css`.

CMS-owned product brand assets such as the admin sidebar logo and default CMS favicons ship from package `public/cms/brand/` and are installed, published, or synced into install-root `public/cms/brand/`. These are product identity assets for the CMS shell, not site-specific Media Library branding and not `public/site/...` overrides. The canonical product brand set is `logo.svg`, `logo-mark.svg`, `logo-mark-dark.svg`, `logo-mark-on-accent.svg`, `logo-mark-inverse.svg`, `favicon.svg`, `favicon-32x32.png`, `favicon-16x16.png`, `apple-touch-icon.png`, `icon-192x192.png`, and `icon-512x512.png`. The PNG outputs are generated from the CMS mark so browser-tab, touch, and app icons show the actual logo shape instead of flat-color placeholders. The product brand set includes normal, dark-surface, on-accent/inverse, and high-contrast favicon/browser-tab variants so CMS auth, admin, guest, and install/product shells can select contrast-safe assets directly instead of relying on page CSS filters.

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
- The shared public dialog layer must not be rendered with `hidden`; WebBlocks UI `v2.7.12` reuses that layer for modal, gallery viewer, and toast targets and only toggles visibility on the active backdrop/modal or toast state, not on a reused layer wrapper
- CMS core only ships public JS when WebBlocks UI does not already cover the behavior; `public-search-modal.js` remains CMS-owned, while Header Actions mode, preset, accent, and dropdown behavior now rely on shipped WebBlocks UI `data-wb-*` behavior without an extra CMS runtime

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

CMS-owned default CDN references are pinned to WebBlocks UI `v2.7.12` for the public and admin runtime CSS, icons CSS, runtime JS, and the default icon manifest sync source. Production layout output uses the canonical jsDelivr tag URL format with the standard dist artifacts `webblocks-ui.css`, `webblocks-icons.css`, and `webblocks-ui.js` while minification hardening is deferred. Browser-facing CSS and JavaScript must not use `raw.githubusercontent.com` fallbacks because Chrome can block those responses through ORB or MIME handling.

Those CDN assets are part of the UI project and must not be edited or compiled inside the CMS repository. When installed on an operator site, WebBlocks UI Manager records release, artifact, manifest, checksum, and publish-run metadata as plugin-owned behavior and can publish validated files into a configured local/project-owned static CDN target. CMS core still consumes the existing pinned CDN URLs until a separate explicit migration changes that behavior.
