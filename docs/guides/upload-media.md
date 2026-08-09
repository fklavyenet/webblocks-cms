---
guide: true
guide_slug: upload-media
guide_series: D
guide_order: 17
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/upload-media
cms_layout: docs
cms_title: Upload Files To The Media Library
card_description: Get images and documents into the library once, then reuse them anywhere.
card_thumbnail: 01-media-library.png
---

# Upload Files To The Media Library

**Goal:** Put a file into the Media Library with the details filled in.
**Time:** 2 minutes
**You need:** A file, and Media access

## Steps

1. Select **Media** in the sidebar. The library lists everything already uploaded, with filters for **Search**, **Kind**, **Usage**, and sorting.

> **Screenshot** `01-media-library.png` — The Media Library list with its filters.
> Alt: Media Library showing uploaded files with search, kind, and usage filters.

2. Select **Upload Media**.
3. Choose the **File**, then fill in what you know: **Folder**, **Title**, **Alt Text**, **Caption**, **Description**.
4. Select **Save**.

> **Screenshot** `02-upload-form.png` — The upload form with a document and its details filled in.
> Alt: Media upload form with file, folder, title, alt text, caption, and description fields.

5. The file appears at the top of the library, with its type, size, and a **Usage** badge showing where it is used.

> **Screenshot** `03-uploaded.png` — The library with the newly uploaded document.
> Alt: Media Library listing the newly uploaded PDF with its size and usage.

## Example

```text
File:        atlas-rate-card.pdf
Title:       Atlas Studio rate card 2026
Description: Public price list for the 2026 engagement types.
```

## Notes

- **Fill in Alt Text at upload time** for images. It travels with the file, so every block that uses it starts with a sensible default instead of nothing.
- **Title** is what you and your colleagues search for later. `atlas-rate-card.pdf` is a filename; `Atlas Studio rate card 2026` is a title.
- Upload the largest good-quality version of an image you have. The CMS derives the smaller sizes it needs — see [Alt Text, Captions, And Image Variants](/guides/image-details).
- **Fetch URL** pulls a file from an address instead of your disk. Same result, no download-then-upload dance.
- The library is shared across the whole installation, not per page. Upload once; use it in as many places as you like.

**Next:** [Alt Text, Captions, And Image Variants](/guides/image-details)
