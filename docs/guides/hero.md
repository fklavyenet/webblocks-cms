---
guide: true
guide_slug: hero
guide_series: C
guide_order: 12
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/hero
cms_layout: docs
cms_title: 'Hero: Build An Opening Section'
card_description: The banner at the top of a page — title, intro, buttons, and a background image.
card_thumbnail: 01-form.png
---

# Hero: Build An Opening Section

**Goal:** Give a page a proper opening instead of starting cold with a heading.
**Time:** 4 minutes
**You need:** A page, and ideally one wide image in the Media Library

## Steps

1. In the Main slot, select **Add Block**, then choose **Hero**.
2. Fill in the copy: **Eyebrow / Label** is the small line above the title, **Title** is the headline, and **Subtitle / Intro** is the sentence under it.
3. Add up to two actions: **Primary CTA Label** and **Primary CTA URL**, then the secondary pair if you need it.
4. Choose a **Background** image if you have one, and set **Background Position** and **Overlay** so the text stays readable.
5. Pick a **Variant** (`default`, `muted`, `accent`, `soft`) and a **Layout** (`left`, `centered`, `split`).
6. Set **Title Tag** to `h1` when the Hero carries the page's main heading — which is usually the point of a Hero.
7. Select **Save New Block**.

> **Screenshot** `01-form.png` — The Hero block form with copy, both CTAs, and the presentation settings.
> Alt: Hero block form showing eyebrow, title, intro, CTA fields, variant, and layout.

> **Screenshot** `02-rendered.png` — The Hero rendered on the public page.
> Alt: Public page showing a centred hero with a title, intro sentence, and two buttons.

## Example

```text
Eyebrow / Label:      Studio journal
Title:                Notes from the studio
Subtitle / Intro:     Short posts about the work behind our projects — drafts,
                      dead ends, and the decisions that made the final version.
Primary CTA:          Read the latest  →  /journal
Secondary CTA:        About the studio  →  /about
Variant / Layout:     accent / centered
Title Tag:            h1
```

## Notes

- **One Hero per page, at the top.** A second Hero halfway down is a [CTA block](/guides/buttons-and-cta) wearing the wrong costume.
- The CTA buttons are part of the Hero, not separate blocks you add. Filling in a label and URL creates them; clearing both removes them.
- **Overlay** exists so text survives a busy photograph. If the title is hard to read, raise the overlay before you go looking for a different image.
- Set **Title Tag** to `h2` if the page already has an `h1` elsewhere. Two `h1` elements on one page is a structure error, not a style choice.

**Next:** [Buttons And CTA](/guides/buttons-and-cta)
