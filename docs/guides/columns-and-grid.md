---
guide: true
guide_slug: columns-and-grid
guide_series: E
guide_order: 24
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/columns-and-grid
cms_layout: docs
cms_title: Columns And Grid
card_description: Put things side by side — with copy of its own, or as a bare arrangement.
card_thumbnail: 04-rendered.png
---

# Columns And Grid

**Goal:** Arrange several pieces of content side by side.
**Time:** 4 minutes
**You need:** A page, and three things worth showing together

## Steps

1. Select **Add Block**, then choose **Columns**.
2. Fill in **Columns Title**, **Columns Subtitle**, and **Intro Text** — Columns brings its own heading, which is what separates it from Grid.
3. Pick a **Columns Variant**: `cards` frames each column, `plain` leaves them bare, `stats` suits short numbers.

> **Screenshot** `01-columns-form.png` — The Columns form with title, subtitle, intro, and variant.
> Alt: Columns block form showing the title, subtitle, intro text, and variant selector.

4. On the Columns row, select **Add child block** and choose **Column Item**. Fill in **Column Title**, **Column Text**, and optionally **Optional Link** and **Badge label**. Repeat for each column.

> **Screenshot** `02-column-item-form.png` — A Column Item with its title and text.
> Alt: Column Item form showing column title, optional link, column text, and badge label.

5. For a bare arrangement, select **Add Block** and choose **Grid** instead. Set **Columns** (2, 3, or 4) and **Gap**.

> **Screenshot** `03-grid-form.png` — The Grid form with column count and gap.
> Alt: Grid block form showing the admin name, column count, and gap settings.

6. Add whatever you like as children of the Grid — Cards are the usual choice.

> **Screenshot** `04-rendered.png` — Columns and a grid of cards rendered on the public page.
> Alt: Public page showing a three-column set with intro copy above a grid of cards.

## Columns Or Grid?

**Columns** is a content block. It has a title, a subtitle, an intro, and a fixed idea of what goes inside: Column Items.

**Grid** is a layout block. No copy of its own, no opinion about its children — just a number of columns and a gap.

Use Columns when the set needs an introduction. Use Grid when you are arranging blocks that already speak for themselves.

## Notes

- Both collapse to a single column on a phone. That is the point, and it is why a table is the wrong tool for side-by-side layout.
- Four columns is usually two columns with extra steps. On a laptop each one is narrow; on a phone they are just a long list.
- **Alternate media/text sections** on Grid flips image and text sides down the page, for the classic alternating layout. **First Section Layout** decides which side starts.
- Grid does not care what is inside it, so an accidental Grid of Headers renders exactly as badly as it sounds. Give it Cards, or content that can stand alone.

**Next:** [Card, Feature Grid, And Stat Card](/guides/cards-and-stats)
