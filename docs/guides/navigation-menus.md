---
guide: true
guide_slug: navigation-menus
guide_series: G
guide_order: 32
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/navigation-menus
cms_layout: docs
cms_title: Build A Navigation Menu
card_description: Add, group, and reorder the links visitors click.
card_thumbnail: 01-navigation.png
---

# Build A Navigation Menu

**Goal:** Put a page into a menu, and get the order right.
**Time:** 3 minutes
**You need:** Navigation access, and a page to link to

## Steps

1. Select **Navigation** in the sidebar. Pick the **Site** and the **Menu** you are editing — Primary, Footer, Mobile, Legal, or Docs.

> **Screenshot** `01-navigation.png` — The Navigation Items screen with the menu selector and current items.
> Alt: Navigation items screen showing the site and menu selectors above the list of links.

2. Select **Add Item**.
3. Fill in **Label / Title** — this is the text visitors read, and it does not have to match the page title.
4. Choose the **Link Source**: **Page** picks a page from this site and follows it if the page's address changes; **Custom URL** takes any address; **Group** makes a heading that holds other items.
5. Set **Parent Group** to nest it, **Target** if the link should open in a new tab, **Display** to hide an item without deleting it, and an optional **Icon**.

> **Screenshot** `02-add-item.png` — The Add Item dialog filled in.
> Alt: Add navigation item dialog with label, link source, URL, target, and display fields.

6. Select **Create**, then drag the handle on the left of each row to set the order.

> **Screenshot** `03-menu-after.png` — The menu with the new item in place.
> Alt: Navigation list showing the newly added item among the existing links.

## Example

```text
Label / Title: Studio journal
Link Source:   Custom URL
Custom URL:    /journal
Target:        _self
Display:       visible
```

## The Five Menus

The menu keys are fixed by the product: **Primary**, **Footer**, **Mobile**, **Legal**, **Docs**. You cannot add a sixth from the admin.

A menu is only a list of links. What renders it is a block — **Navbar Navigation** in a header, **Sidebar Navigation** in a sidebar — pointing at the menu key. Editing the menu changes every block bound to it.

## Notes

- **Prefer Page over Custom URL** for internal links. A page link survives a slug change; a hand-typed `/about` does not.
- **Display: hidden** is how you take a link out of circulation without losing its position and settings. Use it instead of deleting during a rebuild.
- A **Group** with no children renders as a dead heading. Add the items first, or hide the group.
- Menu changes are live the moment you save. There is no draft state for navigation — see [Update A Shared Slot Safely](/guides/update-a-shared-slot) for the same warning about headers and footers.

**Next:** [Build A Header As A Shared Slot](/guides/header-shared-slot)
