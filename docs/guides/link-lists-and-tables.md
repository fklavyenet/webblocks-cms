---
guide: true
guide_slug: link-lists-and-tables
guide_series: C
guide_order: 15
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/link-lists-and-tables
cms_layout: docs
cms_title: Link Lists And Tables
card_description: A tidy list of links with descriptions, and real tabular data.
card_thumbnail: 04-rendered.png
---

# Link Lists And Tables

**Goal:** Build a readable index of links, and put real data in a real table.
**Time:** 5 minutes
**You need:** A page, a handful of links, and some rows of data

## Steps

1. Select **Add Block**, then choose **Link List**.
2. Fill in the optional intro: **Optional Intro Title**, **Optional Intro Description**, **Optional Intro Meta**.
3. Choose **Row Layout** (`index` or `stacked`), **List Frame** (`joined` or `cards`), and **Thumbnail Size** if your rows will carry images.
4. Select **Save New Block**.

> **Screenshot** `01-link-list-form.png` — The Link List form with intro copy and layout settings.
> Alt: Link List block form showing intro fields, row layout, list frame, and thumbnail size.

5. Now add the rows. Select **Add Block**, choose **Link List Item**, and set **Parent Block** to your Link List.
6. Fill in **Link Title** and **URL**, plus **Optional Meta** (a date works well), **Badge label**, and **Optional Description**. Repeat for each row.

> **Screenshot** `03-link-list-item-form.png` — A Link List Item form with its parent set to the Link List.
> Alt: Link List Item form showing the parent block selector, title, URL, meta, and description.

7. For tabular data, select **Add Block** and choose **Table**. Fill in **Table Title**, pick a **Table Style**, and type the rows: **one row per line, cells separated by a vertical bar**.

> **Screenshot** `02-table-form.png` — The Table form with the pipe-separated rows.
> Alt: Table block form showing the title, style selector, and rows separated by vertical bars.

> **Screenshot** `04-rendered.png` — The link list and table rendered on the public page.
> Alt: Public page showing a list of linked notes with dates and descriptions, above a data table.

## Example

```text
Link List
  Intro Title:       Recent notes
  Intro Description: The last few pieces we published.
  Row Layout:        index
  List Frame:        joined

  Item  Choosing a type scale we can live with  →  /journal/type-scale
        12 March 2026 · Three candidates, one survivor, and the ratio that decided it.

Table
  Title: Project timeline
  Style: header-row

  Phase     | Runs for | Delivered
  Discovery | 2 weeks  | Research notes, scope
  Design    | 4 weeks  | Layouts, design system
  Build     | 6 weeks  | Working site, handover
```

## Notes

- You can paste a range straight from a spreadsheet into **Table Rows** and it fills the grid. Press **Enter** to jump to the next row.
- `header-row` treats your first line as column headings. Use `plain` only when the first row is data like any other.
- A table is for data with rows and columns. A list of links is not data — that is a Link List, and it will read better and behave properly on a phone.
- Do not use a table to place things side by side. Layout blocks such as Columns and Grid are for that, and tables collapse badly on small screens.
- A short bullet list inside a paragraph belongs in [Rich Text](/guides/rich-text). Link List earns its place when each row has a link plus supporting detail.

**Next:** [Code And HTML Blocks](/guides/code-and-html)
