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

## WebBlocks UI Assets

WebBlocks UI assets remain loaded from CDN in the CMS public layout.

Those CDN assets are part of the UI project and must not be edited inside the CMS repository.
