---
guide: true
guide_slug: image-details
guide_series: D
guide_order: 18
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/image-details
cms_layout: docs
cms_title: Alt Text, Captions, And Image Variants
card_description: The details that make an image usable — and the sizes the CMS makes for you.
card_thumbnail: 02-variants.png
---

# Alt Text, Captions, And Image Variants

**Goal:** Give an image the description it needs, and understand the sizes generated from it.
**Time:** 3 minutes
**You need:** An image in the Media Library

## Steps

1. Open **Media** and select the edit action on an image.
2. Fill in **Media Information**: **Title**, **Alt Text**, **Caption**, **Description**, and the **Folder** it belongs in.
3. Set the **Focal point** if the subject is off-centre. Crops keep that point in frame instead of cutting through it.

> **Screenshot** `01-media-edit.png` — The media edit screen with preview, usage, and the information fields.
> Alt: Media edit screen showing the image preview, usage panel, and metadata fields.

4. Scroll to **Image Variants**. The CMS derives **Thumbnail**, **Card**, **Content-small**, **Content**, and **Content-large** from your upload and serves whichever fits the visitor's screen.
5. If you replace the file or change the focal point, select **Regenerate variants**.

> **Screenshot** `02-variants.png` — The Image Variants panel listing the generated sizes.
> Alt: Image Variants panel listing thumbnail, card, and content sizes with a regenerate action.

## Alt Text, Caption, Description — Which Is Which

| Field | Who sees it | Write it for |
| --- | --- | --- |
| **Alt Text** | Screen readers, and anyone whose image fails to load | Someone who cannot see the picture |
| **Caption** | Every visitor, printed under the image | Context the picture does not carry by itself |
| **Description** | Only you and your colleagues, inside the admin | Finding the file again in six months |

## Notes

- Alt text set on the media record is the starting point. A block can override it, because the same photo can mean different things in different places.
- Alt text and caption are stored per language; the file itself is shared across all of them.
- Do not repeat the caption in the alt text. A screen reader reads both, and hearing the same sentence twice is worse than hearing it once.
- Variants are generated on upload. If images look soft, check what you uploaded before blaming the resizing — the CMS cannot add detail that was never there.

**Next:** [Build A Gallery](/guides/build-a-gallery)
