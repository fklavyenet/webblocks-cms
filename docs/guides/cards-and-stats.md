---
guide: true
guide_slug: cards-and-stats
guide_series: E
guide_order: 26
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/cards-and-stats
cms_layout: docs
cms_title: Card, Feature Grid, And Stat Card
card_description: Three ways to package a small unit of content — and when each one fits.
card_thumbnail: 05-rendered.png
---

# Card, Feature Grid, And Stat Card

**Goal:** Build a card properly, and know which of the three card-like blocks you actually want.
**Time:** 5 minutes
**You need:** A page, ideally with a Grid to put cards in

## Steps

1. Select **Add Block**, choose **Card**, give it a **Name** and a **Card style** (`flat`, `muted`, `highlight`, `accent`).

> **Screenshot** `01-card-form.png` — The Card form with its name and style.
> Alt: Card block form showing the admin name, card style, and background settings.

2. **A Card on its own renders nothing.** On the Card's row, select **Add child block** and add **Card Header**, **Card Body**, and **Card Footer**. The regions have no fields of their own — they are containers.
3. Add content inside each region: a Header in the header, a Plain Text in the body, a Button Link in the footer.

> **Screenshot** `04-block-tree.png` — The block list showing the nested card structure.
> Alt: Block list showing cards with their header, body, and footer regions nested inside.

4. For a set of short feature statements, select **Add Block** and choose **Feature Grid**, fill in its title and intro, then add **Feature Item** children.

> **Screenshot** `02-feature-grid-form.png` — The Feature Grid form.
> Alt: Feature Grid form showing title, subtitle, and intro text.

5. For a number worth showing off, select **Add Block** and choose **Stat Card**: **Value** is the number, **Eyebrow / Label** the small line above it, **Description** the sentence under.

> **Screenshot** `03-stat-card-form.png` — The Stat Card form.
> Alt: Stat Card form showing eyebrow, value, description, and optional URL.

> **Screenshot** `05-rendered.png` — Cards, feature items, and stats rendered on the public page.
> Alt: Public page showing a grid of cards above a feature grid.

## Example

```text
Grid  Columns: 3  Gap: 6
  Card  Design systems  (style: default)
    Card Header  → Header      Design systems
    Card Body    → Plain Text  A component library your team can extend
                               without us.
    Card Footer  → Button Link Read more → /services

Stat Card   Eyebrow: Since   Value: 2019
            Description: Working together as a studio.
```

## Which One?

| Block | Use it when |
| --- | --- |
| **Card** | The unit needs real content — a heading, a paragraph, an image, an action. You compose it. |
| **Feature Grid** | You have three to six short "we do this" statements and want them uniform without composing each one. |
| **Stat Card** | The content *is* a number. One value, one label, one sentence. |

## Notes

- **Card regions are not offered in the top-level block picker.** They only exist inside a Card, which is why **Add child block** on the Card row is the only way in.
- You do not need all three regions. A Card with only a body is fine; a Card with nothing is invisible.
- Cards inside Cards do not nest meaningfully. If you want a card grid, put Cards inside a [Grid](/guides/columns-and-grid).
- Feature Grid is the quicker path and the less flexible one. When you start fighting it, you wanted Cards.
- Keep card copy at similar lengths. One card with three sentences next to two with five words looks broken even when it is not.

**Next:** [Cluster And Spacing](/guides/cluster-and-spacing)
