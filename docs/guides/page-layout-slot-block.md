---
guide: true
guide_slug: page-layout-slot-block
guide_series: A
guide_order: 3
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/page-layout-slot-block
cms_layout: docs
cms_title: Page, Layout, Slot, Block
card_description: The four words the CMS uses for everything, in plain language.
card_thumbnail: 01-layout-slots.png
---

# Page, Layout, Slot, Block

**Goal:** Understand the four words that every other guide uses.
**Time:** 3 minutes
**You need:** Nothing — this one is reading, not clicking

## The Four Words

**Page** is the thing with an address. `/notes` is a page. It holds your content and decides whether visitors can see it yet.

**Layout** is the frame the page renders in. It decides whether the page has a header strip, a sidebar, a footer — the furniture, not the content. You pick one when you create the page and can change it later.

**Slot** is a named area the layout provides: Header, Main, Sidebar, Footer. Think of them as labelled boxes. The layout says which boxes exist; you decide what goes in them.

**Block** is one piece of content inside a slot: a heading, a paragraph, an image, a gallery, a contact form. Blocks stack in order, and some blocks hold other blocks.

Put together: **a page uses a layout, the layout provides slots, and you fill slots with blocks.**

## Steps

1. Open any page from **Pages** and select **Edit page**.
2. Open the **Layout Slots** tab. It compares the slots your layout defines with the slots this page actually has.

> **Screenshot** `01-layout-slots.png` — The Layout Slots tab comparing layout slots with page slots.
> Alt: Layout Slots tab showing which slots the page layout defines and which the page has.

3. Scroll to the **Slots** card. This is the same list in working form: each slot, how many blocks it holds, and the button to edit them.

> **Screenshot** `02-slots.png` — The Slots card with Header, Main, Sidebar, and Footer.
> Alt: Page slots card listing the four slots with an Edit Blocks action.

4. Select **Edit Blocks** on **Main**. Now you are looking at blocks: the actual content, in order.

> **Screenshot** `03-blocks.png` — The block list inside the Main slot.
> Alt: Block list showing a header block, a text block, and an image block in order.

## Why It Is Split This Way

Because the same content should not have to be rebuilt for every page, and the frame should not have to be rebuilt for every piece of content.

A **Shared Slot** takes this one step further: instead of filling a slot on one page, you fill it once and assign it to many pages. That is how a site header stays identical everywhere without anyone copying it around.

## Notes

- Changing the layout does not delete your blocks. Slots that the new layout does not define are kept, they just may not render.
- A block can hold other blocks. A Card holds a header, a body, and a footer region; a Grid holds cards. That nesting is how a page gets a real layout without writing HTML.

**Next:** [Your Profile And Language](/guides/your-profile-and-language)
