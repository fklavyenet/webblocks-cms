# Reference Documentation Media

This directory owns media used by Markdown sources directly under `docs/`.

- Keep only documentation assets that must be versioned with the product.
- Use descriptive, stable, lowercase file names.
- Reference files with paths relative to the source. For example, an `example.webp` file here is addressed as `media/example.webp` from a top-level document.
- The documentation sync command uploads or replaces these files in the CMS Media Library and renders native Image blocks. A Markdown image path is never a public site URL.
- Guide screenshots belong in `docs/guides/media/<guide-slug>/`.
- Marketing, blog, home-page, and other site-owned media do not belong here.

Binary assets in this directory ship with the Composer package. Keep them necessary and reasonably sized.
