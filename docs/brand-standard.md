# CMS Brand Standard

This is the final WebBlocks CMS product brand standard for CMS-owned auth, admin, package, and root runtime assets. It exists to keep future work from reintroducing older image-switching, mask, or squircle app-icon experiments.

## Brand File Inventory

The CMS keeps the same final brand files in both runtime and package asset paths:

- `public/cms/brand/favicon.svg`
- `public/cms/brand/favicon-16x16.png`
- `public/cms/brand/favicon-32x32.png`
- `public/cms/brand/apple-touch-icon.png`
- `public/cms/brand/logo-mark.svg`
- `public/cms/brand/logo-mark-dark.svg`
- `public/cms/brand/logo-mark-on-accent.svg`
- `packages/webblocks-cms/public/cms/brand/favicon.svg`
- `packages/webblocks-cms/public/cms/brand/favicon-16x16.png`
- `packages/webblocks-cms/public/cms/brand/favicon-32x32.png`
- `packages/webblocks-cms/public/cms/brand/apple-touch-icon.png`
- `packages/webblocks-cms/public/cms/brand/logo-mark.svg`
- `packages/webblocks-cms/public/cms/brand/logo-mark-dark.svg`
- `packages/webblocks-cms/public/cms/brand/logo-mark-on-accent.svg`

The favicon and app icon files are fixed browser-chrome assets. The product mark files remain available for compatibility and packaging, but auth and admin sidebar rendering use the reusable inline SVG component instead of image markup.

## Reusable Brand Mark Component

The reusable CMS product mark component lives at:

`packages/webblocks-cms/resources/views/components/brand-mark.blade.php`

Use it through the package component namespace:

```blade
<x-webblocks-cms::brand-mark class="..." decorative="true" />
```

The component renders inline SVG, uses `stroke="currentColor"`, and intentionally omits `width` and `height` attributes from the outer product brand SVG. Size belongs in CSS through WebBlocks UI classes and CMS static CSS.

## Auth Shell Usage

CMS auth views use the package guest shell and inline brand component for both accent-panel and surface/form-header marks. Auth brand color is controlled through WebBlocks UI tokens:

- Accent surfaces use `color: var(--wb-accent-on)`.
- Surface contexts use `color: var(--wb-accent)` or an approved fallback when a token is unavailable.

Auth brand marks must not use `img`, `picture`, `source`, CSS masks, or media switching.

## Admin Sidebar Usage

The admin sidebar uses the same reusable inline SVG brand mark component. The sidebar must preserve the established WebBlocks UI class contract:

- `wb-sidebar-brand`
- `wb-sidebar-brand-logo`
- `wb-sidebar-brand-copy`
- `wb-sidebar-brand-note`

The sidebar brand mark color is controlled by WebBlocks UI tokens, using `var(--wb-accent)` or an approved fallback for surface/admin contexts.

## Color And Token Rules

Brand mark SVG paths inherit color through `currentColor`.

- Accent surfaces: `var(--wb-accent-on)`
- Surface/admin contexts: `var(--wb-accent)` or an approved fallback

Do not solve auth or sidebar color by swapping image files, adding dark/light `<source>` markup, adding CSS masks, or adding hard-coded dimensions to the inline SVG element.

## Do Not Reintroduce

Do not reintroduce any of these older CMS product brand approaches:

- `img` brand logos in auth or admin sidebar product chrome
- `picture` or `source` switching for auth or admin sidebar product brand marks
- CSS mask brand marks, including `wb-auth-brand-mark-mask`
- `width` or `height` attributes on auth or admin sidebar product brand SVG elements
- Squircle favicon or app-icon direction
- Obsolete files such as `logo.svg`, `app-icon.svg`, `logo-mark-mask.svg`, `logo-mark-inverse.svg`, `icon-192x192.png`, or `icon-512x512.png`

Focused tests enforce this standard for auth, admin sidebar, root brand assets, and package brand assets.
