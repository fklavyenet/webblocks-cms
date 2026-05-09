# Public Assets

## CMS Core Public Assets

WebBlocks CMS core public assets live under:

- `public/assets/webblocks-cms/js/`
- `public/assets/webblocks-cms/css/`

These paths are for CMS-owned runtime behavior and styling that should ship with the product itself.

## Install-Level Override Assets

Install-specific or site-specific public overrides remain under:

- `public/site/css/site.css`
- `public/site/js/site.js`

These files are override space for the current install and should not be used for CMS core behavior.

## Page Assets

Page-scoped CSS and JS files can now be referenced relationally from `page_assets`.

- V1 accepts only local `/site/...` paths such as `/site/webblocksui/playground/playground.css` or `/site/webblocksui/playground/playground.js`
- CSS page assets render in the public `<head>`
- JS page assets render near the public body-end script area
- Only the owning public page renders its configured page assets
- Page Assets are not rendered in admin layouts
- Page Assets are stored in `page_assets`, not in `pages.settings`
- When site Export / Import includes media or assets, referenced `/site/...` physical files are also packaged and restored

## Site Branding Assets

Public favicon and social sharing artwork are now selected from the shared Media library on each Site.

- favicon output uses the current resolved site's `favicon_asset_id` when that asset has a public URL
- Open Graph fallback imagery uses the current resolved site's `social_image_asset_id` when available
- these are site-scoped metadata assets, not CMS product-brand assets
- page translation `og_image_asset_id` can override the site social image for one locale when that asset has a public URL
- page-level SEO assets affect public metadata only and do not change CMS admin branding
- install-level Project Identity does not affect public favicon, public metadata, or site-scoped social sharing assets

## WebBlocks UI Assets

WebBlocks UI assets remain loaded from CDN in the CMS public layout.

Those CDN assets are part of the UI project and must not be edited inside the CMS repository.
