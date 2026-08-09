---
guide: true
guide_slug: update-a-shared-slot
guide_series: G
guide_order: 35
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/update-a-shared-slot
cms_layout: docs
cms_title: Update A Shared Slot Safely
card_description: Editing a shared area changes every page at once. Here is how to do it without breaking things.
card_thumbnail: 01-usage.png
---

# Update A Shared Slot Safely

**Goal:** Change a header or footer without discovering the damage on the live site.
**Time:** 3 minutes
**You need:** An existing Shared Slot with pages assigned to it

## Read This First

**A Shared Slot has no draft.** A page can sit in draft while you work on it. A Shared Slot cannot: it is shared, and the pages using it are already published.

That means an edit here is live on every assigned page as soon as it saves. There is no preview step that covers you, and no publish button standing between you and the visitor.

## Steps

1. Open **Shared Slots** and select the one you are about to change.
2. Read the **Usage** panel first. It lists every page this slot renders on — that is your blast radius.

> **Screenshot** `01-usage.png` — The Shared Slot edit screen with its usage panel.
> Alt: Shared slot edit screen showing the usage panel listing the pages that use it.

3. Make the change in **Edit Blocks**.
4. Open one of the pages from the Usage list and check the result on the real page, at a narrow width as well as a wide one.
5. If something went wrong, open **Revision History**, find the snapshot from before your change, and restore it.

> **Screenshot** `02-revisions.png` — The Shared Slot revision history.
> Alt: Shared slot revision history listing snapshots with restore actions.

## What Is Risky, And What Is Not

| Change | Risk |
| --- | --- |
| Editing text in a block | Low — it is one string, and revisions have your back |
| Adding a block | Low, but check the narrow width; headers run out of room fast |
| **Reordering blocks** | Higher — it changes every page at once, immediately |
| **Deleting a block** | Highest — it disappears everywhere, and deletion needs the destructive permission for exactly that reason |

## Notes

- **Publishing a page does not publish its Shared Slots**, and vice versa. They are reviewed and published separately, on purpose.
- Revisions are per Shared Slot. Restoring one does not touch the pages that use it, because the pages never held that content.
- Changing a slot's **Handle** or **Slot** after pages are assigned is not a rename you should do casually. Create a new Shared Slot and reassign instead.
- If you need a change on one page only, that page should not be using the Shared Slot. Switch its slot source back to **Page Content** and give it its own blocks.
