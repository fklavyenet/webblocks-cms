---
guide: true
guide_slug: add-an-image
guide_series: B
guide_order: 7
cms_site: docs-site
cms_locale: en
cms_path: /guides/add-an-image
cms_layout: docs
cms_title: Add An Image
card_description: Place a picture on a page from the Media Library, with alt text that describes it.
card_thumbnail: 02-media-picker.png
---

# Add An Image

**Goal:** Place a picture on a page, with alt text that describes it.
**Time:** 3 minutes
**You need:** A draft page and an image in the Media Library

## Steps

1. Open the page's **Main** slot, the same way as in [Add A Heading And A Paragraph](/guides/add-heading-and-text).
2. Select **Add Block**, then choose **Image**.

> **Screenshot** `01-image-block-form.png` — The Image block form before an asset is chosen.
> Alt: Image block form showing the Media Asset area, Alt Text, and Caption fields.

3. Select **Choose from Media**. The **Choose Image** dialog opens, with a search box and Folder and Kind filters.
4. Select **Select** on the image you want. To use a file that is not in the library yet, scroll to **Upload to Library** at the bottom of the dialog and upload it there.

> **Screenshot** `02-media-picker.png` — The Choose Image dialog listing the available images.
> Alt: Media Library picker dialog with a list of images and a Select button on each.

5. Fill in **Alt Text**. Describe what the picture shows, in one short sentence.
6. Add a **Caption** if the image needs a visible line under it. Leave it empty otherwise.
7. Select **Save New Block**.

> **Screenshot** `03-image-in-slot.png` — The Main slot showing the header, the paragraph, and the new image block.
> Alt: Slot block list with a header, a plain text block, and an image block.

## Example

```text
Image
  Alt Text: A preview of the Atlas Studio public page layout.
  Caption:  An early layout study for the Atlas Studio homepage.
```

Compare that alt text with the two common mistakes:

- `image1.jpg` — the file name, which describes nothing.
- `A photo` — true, and useless.

## Notes

- Alt text is read aloud to visitors using a screen reader and is shown when the image fails to load. Write it for someone who cannot see the picture.
- If the image is purely decorative and adds no information, leave the alt text empty rather than inventing a description.
- Upload the largest good-quality version you have. The CMS produces the smaller sizes it needs; it cannot invent detail that was never there.
- Alt text and caption are stored per language. The selected image itself is shared across every language of the page.

**Next:** [Publish A Page](/guides/publish-a-page)
