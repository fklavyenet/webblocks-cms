### Improvements

* Added site-specific public asset hooks for single-site installs

  * Public pages now optionally load `public/site/css/site.css`
  * Public pages now optionally load `public/site/js/site.js`
  * Added starter site asset files as the canonical place for install-specific public branding and behavior

* Improved media library UX on the admin index screen

  * Added list/grid view toggle with query-string persistence
  * Improved preview thumbnails, metadata density, compact actions, copy URL, and usage details
  * Added preview modal quality-of-life fixes, including backdrop close and preview-context return flow
  * Added unused/used filtering and clearer folder chip behavior

* Improved editorial list density in Pages and Contact Messages

  * Pages list is more compact and no longer wastes space on less useful columns
  * Contact Messages list now supports compact filters and cleaner action presentation
  * Fixed admin overlay drawer rendering so hidden drawers do not leave a visual shadow on list screens

### Notes

* WebBlocks CMS still follows a single-site-per-install convention
* Site-specific public branding should go into `public/site/...`, while shared CMS/core public templates remain generic
* Published releases from this tag are intended to be consumed by the in-app CMS updater
