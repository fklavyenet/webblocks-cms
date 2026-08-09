---
guide: true
guide_slug: cluster-and-spacing
guide_series: E
guide_order: 25
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/cluster-and-spacing
cms_layout: docs
cms_title: Cluster And Spacing
card_description: Line a few things up in a row, and control the air between everything else.
card_thumbnail: 01-cluster-form.png
---

# Cluster And Spacing

**Goal:** Put two buttons side by side without a grid, and understand where spacing comes from.
**Time:** 3 minutes
**You need:** A page and a couple of small blocks

## Steps

1. Select **Add Block**, then choose **Cluster**.
2. Give it a **Name**, then set the arrangement: **Justify** (where the row sits), **Align** (how items line up vertically), **Gap** (`none` to `lg`), **Wrap**, and **Width**.

> **Screenshot** `01-cluster-form.png` — The Cluster form with its arrangement settings.
> Alt: Cluster block form showing name, width, justify, align, wrap, and gap.

3. On the Cluster's row, select **Add child block** and add the items — two Button Links, for example.
4. Save, and check the result at a narrow window as well as a wide one.

> **Screenshot** `02-rendered.png` — Two buttons sitting in a centred row.
> Alt: Public page showing two buttons side by side in a centred cluster.

## Example

```text
Cluster  Name: Page actions   Justify: center   Gap: sm   Wrap: nowrap
  Button Link  Start a conversation → /contact   (primary)
  Button Link  See our work         → /work      (secondary)
```

## Where Spacing Actually Comes From

Four blocks control the air on a page, and reaching for the wrong one is the usual cause of a layout that will not behave:

- **Section — Spacing.** The vertical space above and below a band of content. This is the big one.
- **Container — Flow.** `stack` spaces the container's children evenly; `none` leaves them as they are.
- **Grid — Gap.** The space between grid cells.
- **Cluster — Gap.** The space between items in a row.

If two paragraphs sit too close together, the answer is the Container's flow, not an empty Plain Text block.

## Notes

- **Never add an empty block for spacing.** It survives until someone edits the page on a phone, and it reads as an empty paragraph to a screen reader.
- **Wrap: nowrap** keeps a row on one line at every width, which is right for two buttons and wrong for six chips. When in doubt, let it wrap.
- Cluster is for a handful of small items in a row. For content of substance side by side, use [Columns or Grid](/guides/columns-and-grid).
- Cluster is also what sits inside navigation bars and card footers, which is why it shows up in Shared Slots.

**Next:** [Card, Feature Grid, And Stat Card](/guides/cards-and-stats)
