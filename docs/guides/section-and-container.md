---
guide: true
guide_slug: section-and-container
guide_series: E
guide_order: 23
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/section-and-container
cms_layout: docs
cms_title: 'Section And Container: Control Page Width'
card_description: The two wrappers that decide how wide your content sits and how much air it gets.
card_thumbnail: 03-rendered.png
---

# Section And Container: Control Page Width

**Goal:** Stop text running the full width of a large screen, and give a band of content its own breathing room.
**Time:** 3 minutes
**You need:** A page with a Main slot

## Steps

1. In the Main slot, select **Add Block**, then choose **Section**.
2. Give it a **Name**. This is an admin-only label so you can tell sections apart in the block list — visitors never see it.
3. Set **Spacing** for the vertical air around the band, and add a background image with **Background Position** and **Overlay** if the section needs one.

> **Screenshot** `01-section-form.png` — The Section form with its name, spacing, and background settings.
> Alt: Section block form showing name, background position, overlay, and spacing.

4. On the Section's row in the block list, select **Add child block** and choose **Container**.
5. Set **Width** — `sm` through `xl`, or `full` — and **Flow**. `stack` spaces the children apart evenly; `none` leaves them alone.

> **Screenshot** `02-container-form.png` — The Container form with width and flow.
> Alt: Container block form showing the admin name, width, and flow settings.

6. Add your content blocks as children of the Container.

> **Screenshot** `03-rendered.png` — The section rendered, with content held to a readable width.
> Alt: Public page showing a heading and paragraph constrained to a readable measure.

## Example

```text
Section    Name: Intro section     Spacing: lg
  Container  Name: Readable width    Width: md    Flow: stack
    Header     How we run a project
    Plain Text Three phases, twelve weeks, and a handover that does not
               need us afterwards.
```

## Which Does What

**Section** is the horizontal band. It runs edge to edge, carries the background, and owns the space above and below.

**Container** sits inside it and decides how wide the content is allowed to be.

That split is why a full-width photograph can sit behind a column of text that is still comfortable to read.

## Notes

- **Long lines are hard to read.** Around 60–75 characters is the comfortable range, which is what the narrower widths are for. `full` is for imagery and layout, not paragraphs.
- The **Name** field on layout blocks is admin-only. Use it — a block list of five Sections called "Section" helps nobody.
- A Section without a Container is legal and sometimes right, but then your text is as wide as the screen.
- Nesting is done with **Add child block** on the parent's row, not by adding a top-level block and hoping.

**Next:** [Columns And Grid](/guides/columns-and-grid)
