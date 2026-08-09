---
guide: true
guide_slug: build-a-gallery
guide_series: D
guide_order: 19
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/build-a-gallery
cms_layout: docs
cms_title: Build A Gallery
card_description: Several images as one grid, with captions and an optional lightbox.
card_thumbnail: 02-rendered.png
---

# Build A Gallery

**Goal:** Show a set of images together instead of stacking Image blocks.
**Time:** 3 minutes
**You need:** A page, and a few images already in the Media Library

## Steps

1. In the Main slot, select **Add Block**, then choose **Gallery**.
2. Select **Add Gallery Items** and pick your images. Pick them in the order you want them shown.
3. Set the presentation: **Columns**, **Gap**, **Aspect Ratio**, and **Captions**.
4. Give the **Viewer title** a name if you turn the lightbox on — it labels the enlarged view.
5. Leave **Enable lightbox viewer** on so visitors can open an image full size, and set **Overlay Mode** for how captions sit over the image.
6. Select **Save New Block**.

> **Screenshot** `01-form.png` — The Gallery form with items added and the presentation settings.
> Alt: Gallery block form showing selected gallery items, columns, aspect ratio, and caption settings.

> **Screenshot** `02-rendered.png` — The gallery rendered on the public page.
> Alt: Public page showing a three-column image gallery with captions under each image.

## Example

```text
Viewer title:   Inside the studio
Columns:        3
Aspect Ratio:   4/3
Captions:       below
Lightbox:       enabled
Items:          Atlas workspace · Atlas gallery · Atlas dashboard
```

## Notes

- **The Gallery block has no title or body copy.** Put a Header block above it and a Plain Text block under that if the set needs an introduction. The **Viewer title** is for the lightbox, not the page.
- Captions come from each image's caption in the Media Library, so fix them there and every gallery using that image improves — see [Alt Text, Captions, And Image Variants](/guides/image-details).
- A fixed **Aspect Ratio** is what makes a grid look tidy when the source images have different shapes. Set the focal point on any image that gets cropped badly.
- Two images are not a gallery. Two Image blocks, or a Columns layout, will read better.
- Reordering happens in the item list, not by re-picking. **Remove All** clears the set without touching the files themselves.

**Next:** [Add A Video Or Audio Block](/guides/video-and-audio)
