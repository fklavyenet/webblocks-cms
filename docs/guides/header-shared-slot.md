---
guide: true
guide_slug: header-shared-slot
guide_series: G
guide_order: 33
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/header-shared-slot
cms_layout: docs
cms_title: Build A Header As A Shared Slot
card_description: Build the header once, then point every page at it.
card_thumbnail: 02-header-blocks.png
---

# Build A Header As A Shared Slot

**Goal:** Have one header that every page uses, instead of a copy per page.
**Time:** 5 minutes
**You need:** Shared Slots access, and a navigation menu with some links in it

## Steps

1. Select **Shared Slots** in the sidebar. This lists the reusable areas for the current site, with the pages each one serves.

> **Screenshot** `01-shared-slots.png` — The Shared Slots list.
> Alt: Shared Slots screen listing reusable areas and their usage.

2. Open the header Shared Slot and select **Edit Blocks**. A Shared Slot holds blocks exactly like a page slot does.
3. A workable header is a **Navbar** holding a **Container**, holding a **Cluster**, holding a **Navbar Brand**, a **Navbar Navigation** bound to a menu key, and **Header Actions**.

> **Screenshot** `02-header-blocks.png` — The header Shared Slot's block tree.
> Alt: Shared slot block list showing a navbar with brand, navigation, and header actions nested inside.

4. To put it on a page, open the page, find the slot in the **Slots** card, and select **Manage Source**.
5. Choose **Shared Slot**, pick the one you built, and select **Save Source**.

> **Screenshot** `03-assign-source.png` — The Manage Source dialog with its three options.
> Alt: Manage source dialog offering page content, shared slot, or disabled for a slot.

## The Three Sources

Every page slot renders one of three things, and **Manage Source** is where you choose:

- **Page Content** — this page's own blocks. The default.
- **Shared Slot** — blocks that live somewhere else and are shared with other pages.
- **Disabled** — nothing renders here on this page.

Switching source **preserves the page's own blocks**. Point a slot at a Shared Slot, change your mind, switch back, and your blocks are still there.

## Notes

- **Navbar Brand and Navbar Navigation only work inside a navbar.** They are not offered outside one, which is the CMS stopping you from building a header that cannot render.
- **Navbar Navigation does not hold links** — it points at a menu key and renders whatever [that menu](/guides/navigation-menus) contains.
- One Shared Slot serves one slot type. A slot built for `header` cannot be assigned to a footer.
- **Header Actions** carries the search, theme, and language controls. Its toggles are settings on the block, not separate blocks.

**Next:** [Build A Footer As A Shared Slot](/guides/footer-shared-slot)
