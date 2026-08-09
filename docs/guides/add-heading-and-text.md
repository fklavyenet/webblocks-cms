---
guide: true
guide_slug: add-heading-and-text
guide_series: B
guide_order: 6
cms_site: docs-site
cms_locale: en
cms_path: /guides/add-heading-and-text
cms_layout: docs
cms_title: Add A Heading And A Paragraph
card_description: Put your first words on a page using the Header and Plain Text blocks.
card_thumbnail: 02-block-picker.png
---

# Add A Heading And A Paragraph

**Goal:** Put a visible heading and a paragraph of text on a page.
**Time:** 3 minutes
**You need:** A draft page — see [Create A Page](/guides/create-a-page)

## Steps

1. Open the page from **Pages** and select **Edit page**.
2. Scroll down to the **Slots** card and select **Edit Blocks** on the **Main** slot.

> **Screenshot** `01-slots.png` — The Slots card listing Header, Main, Sidebar, and Footer with the Edit Blocks action.
> Alt: Page slots card with an Edit Blocks button on each slot.

3. Select **Add Block**. The block picker opens, grouped into Common, Layout, Content, Navigation, Advanced, and All. Choose **Header**.

> **Screenshot** `02-block-picker.png` — The Add a Block picker on the Common tab.
> Alt: Block picker showing the common block types including Header and Image.

4. Fill in the Header block:
   - **Text** — `Notes from the studio`
   - **Level** — `2`

   Select **Save New Block**.

5. Select **Add Block** again and choose **Plain Text**.
6. Write your paragraph in the **Text** field, then select **Save New Block**.

> **Screenshot** `03-blocks-list.png` — The Main slot with a Header block and a Plain Text block listed in order.
> Alt: Slot block list containing a header block followed by a plain text block.

## Example

```text
Header
  Text:  Notes from the studio
  Level: 2

Plain Text
  Text:  We publish short pieces about the work behind our projects — the
         drafts, the dead ends, and the decisions that made the final
         version. New notes appear here every few weeks.
```

## Notes

- **Level** is the heading's rank, not its size. A page should have one level 1 heading and use level 2 for the sections under it. Getting this right is what makes a page readable to screen readers and search engines.
- **Plain Text** is for plain paragraphs. If you need bold, links, or lists, use **Rich Text** instead.
- New blocks are saved as **published** by default. They still only reach visitors once the page itself is published.
- Blocks render in the order they are listed. Use the arrows in the block list to reorder them rather than deleting and recreating.

**Next:** [Add An Image](/guides/add-an-image)
