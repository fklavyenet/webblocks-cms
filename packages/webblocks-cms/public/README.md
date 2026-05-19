# Package Public Assets

This directory is the package boundary for WebBlocks CMS public assets.

Current transition status:

- active root `public/cms/...` assets remain the runtime compatibility path
- `public/cms/package-boundary.json` is the first package-owned publishable asset marker
- package asset publishing uses the `webblocks-cms-assets` tag and publishes to `public/vendor/webblocks-cms`
- install-owned `public/site/...` overrides remain outside this package boundary
- pinned WebBlocks UI CDN runtime behavior remains unchanged until a later focused package asset phase moves CMS-owned runtime assets
