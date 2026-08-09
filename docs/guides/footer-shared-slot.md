---
guide: true
guide_slug: footer-shared-slot
guide_series: G
guide_order: 34
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/footer-shared-slot
cms_layout: docs
cms_title: Build A Footer As A Shared Slot
card_description: Create a reusable area from scratch and fill it with real blocks.
card_thumbnail: 02-footer-blocks.png
---

# Build A Footer As A Shared Slot

**Goal:** Create a new Shared Slot and build a footer inside it.
**Time:** 6 minutes
**You need:** Shared Slots access

## Steps

1. Select **Shared Slots**, then **New Shared Slot**.
2. Fill in the form:
   - **Site** — which site this belongs to. Shared Slots do not cross sites.
   - **Name** — what you will recognise in the list.
   - **Handle** — the stable identifier, lowercase with hyphens.
   - **Slot** — the slot type it can be assigned to. This is a **free-text field** and it takes the slot slug: `header`, `main`, `sidebar`, `footer`.
   - **Page Layout** — leave empty unless this slot is only for one layout.
   - **Status** — active.

> **Screenshot** `01-new-shared-slot.png` — The New Shared Slot form filled in.
> Alt: New shared slot form with site, name, handle, slot, page layout, and status.

3. Select **Create**, then **Edit Blocks**.
4. Build the footer the same way you build a page: a **Section** for the band, a **Container** inside it for width, then your content — a Header, some Plain Text, a Link List with the links.

> **Screenshot** `02-footer-blocks.png` — The footer Shared Slot's block tree.
> Alt: Shared slot block list showing a section, container, header, text, and link list.

5. Assign it to a page: open the page, select **Manage Source** on the Footer slot, choose **Shared Slot**, pick yours, and **Save Source**.

## Example

```text
Name:   Studio footer
Handle: studio-footer
Slot:   footer

Section  Footer band          (spacing: lg)
  Container  Footer content   (width: xl, flow: stack)
    Header      Atlas Studio
    Plain Text  Design systems and digital publishing, from a small studio
                in Berlin.
    Link List   Elsewhere
      Work → /work · Journal → /journal · Contact → /contact
```

## Notes

- **The Slot field is typed, not picked.** A typo means the Shared Slot will not be offered for any page slot, and nothing explains why. Use exactly `header`, `main`, `sidebar`, or `footer`.
- The same approach builds a reusable **CTA band**: make a Shared Slot for `main`, put a CTA block in it, and assign it to the pages that need the same offer.
- Not every layout renders every slot. A footer Shared Slot on a layout with no footer is invisible — see [Choose And Change A Page Layout](/guides/choose-a-page-layout).
- Keep the number of Shared Slots small. Two headers and two footers are a design system; nine of each is a mess nobody dares touch.

**Next:** [Update A Shared Slot Safely](/guides/update-a-shared-slot)
