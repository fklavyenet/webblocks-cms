---
guide: true
guide_slug: buttons-and-cta
guide_series: C
guide_order: 13
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/buttons-and-cta
cms_layout: docs
cms_title: Buttons And CTA
card_description: A single button, or a full call-to-action panel with heading and actions.
card_thumbnail: 02-cta-form.png
---

# Buttons And CTA

**Goal:** Ask the reader to do something, with a button or a whole panel.
**Time:** 3 minutes
**You need:** A page and somewhere to send people

## Steps

1. For a single action, select **Add Block** and choose **Button Link**.
2. Fill in **Label** and **URL**. Set **Target** to `_blank` only when the link leaves your site, and pick **Variant**: `primary` for the main action, `secondary` for anything else.

> **Screenshot** `01-button-form.png` — The Button Link form with label, URL, target, and variant.
> Alt: Button Link block form showing the label, URL, target, and variant fields.

3. For a full panel, select **Add Block** and choose **CTA**.
4. Fill in **Eyebrow / Label**, **Heading**, and **Body Copy**, then the **Primary** and **Secondary CTA** label/URL pairs.
5. Optionally add a background image with **Background Position** and **Overlay**, and pick a **Variant**.
6. Select **Save New Block**.

> **Screenshot** `02-cta-form.png` — The CTA form with copy and both actions.
> Alt: CTA block form showing eyebrow, heading, body copy, and two call-to-action pairs.

> **Screenshot** `03-rendered.png` — The CTA panel rendered on the public page.
> Alt: Public page showing a call-to-action panel with a heading, body copy, and two buttons.

## Example

```text
Button Link
  Label:   Read the latest note
  URL:     /journal
  Variant: primary

CTA
  Eyebrow:    Work with us
  Heading:    Have a project in mind?
  Body:       We take on a handful of engagements each year. Tell us what
              you are building.
  Primary:    Start a conversation  →  /contact
  Secondary:  See our work          →  /work
```

## Button Or CTA?

**Button Link** is one action inside your normal flow of content — after a paragraph, at the end of a section.

**CTA** is a panel that interrupts the page to make an offer. It brings its own heading, copy, background, and up to two actions.

If you find yourself adding a heading and a paragraph just to introduce a Button Link, you wanted a CTA.

## Notes

- **One primary button per view.** If everything is primary, nothing is.
- Write the label as the action: `Start a conversation` beats `Click here` and `Submit`.
- A URL can be a site path (`/contact`), a full address, an anchor (`#pricing`), or `mailto:` and `tel:` targets.
- CTA actions, like Hero actions, are part of the block. You do not add Button Link blocks inside a CTA.

**Next:** [Quote And Alert](/guides/quote-and-alert)
