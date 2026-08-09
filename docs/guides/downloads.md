---
guide: true
guide_slug: downloads
guide_series: D
guide_order: 21
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/downloads
cms_layout: docs
cms_title: Offer A Downloadable File
card_description: A clear download action for a PDF or document, instead of a bare link.
card_thumbnail: 01-form.png
---

# Offer A Downloadable File

**Goal:** Give visitors a file to take away, with the label doing the explaining.
**Time:** 2 minutes
**You need:** A page, and a document in the Media Library

## Steps

1. In the Main slot, select **Add Block**, then choose **Download**.
2. Write the **Download Label** as the action: what the visitor gets, not the filename.
3. Use **Helper Text** for the practical details — format, length, anything that affects whether they bother.
4. Select **Choose from Media** and pick the document.
5. Pick a **Variant** if you want the action to sit quieter or louder on the page.
6. Select **Save New Block**.

> **Screenshot** `01-form.png` — The Download form with label, helper text, and the chosen document.
> Alt: Download block form showing the download label, helper text, and selected document.

> **Screenshot** `02-rendered.png` — The download rendered on the public page.
> Alt: Public page showing a download action with its label and helper text.

## Example

```text
Download Label: Download the 2026 rate card
Helper Text:    PDF, one page. Prices exclude VAT.
Document:       Atlas Studio rate card 2026
```

## Download Or File?

Both exist, and they are not the same thing.

**Download** is an action. It expects a document from your library and presents it as something to take away.

**File** is a reference. It also accepts an **External file URL**, so it can point at something hosted elsewhere.

If the file is yours and the point is "take this", use Download.

## Notes

- **Say what it is in the label.** `Download the 2026 rate card` tells the visitor what they get; `rate-card-v3-FINAL.pdf` tells them about your folder structure.
- The helper text is where format and size belong. People decide differently about a one-page PDF and a 40 MB deck.
- Replacing the file in the Media Library updates every Download block using it. That is the point of the library — do not upload version two as a new file and edit each page.
- A document nobody can open is not a download. Prefer PDF over formats that need a particular application.

**Next:** [Organise Media In Folders](/guides/media-folders)
