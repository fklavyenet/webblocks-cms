---
guide: true
guide_slug: publish-a-page
guide_series: B
guide_order: 9
cms_site: docs-site
cms_locale: en
cms_path: /guides/publish-a-page
cms_layout: docs
cms_title: Publish A Page
card_description: Preview your work, then make the page visible to visitors.
card_thumbnail: 01-preview.png
---

# Publish A Page

**Goal:** Make a draft page visible on the public site.
**Time:** 2 minutes
**You need:** A draft page with content on it, and permission to publish

## Steps

1. Open the page from **Pages** and select **Edit page**.
2. Select **Preview** at the top right to see the page as a visitor would. Preview works while the page is still a draft, and a banner reminds you the page is not public yet.

> **Screenshot** `01-preview.png` — The page preview showing the heading, paragraph, and image, with the preview-mode banner.
> Alt: Public preview of a draft page with a preview mode notice at the top.

3. Back on the edit screen, open the **Overview** tab. It shows the site, the domain, the current status, and the available actions.

> **Screenshot** `02-overview.png` — Overview showing Draft status with the Submit for Review and Publish actions.
> Alt: Page overview panel showing draft status and the publish actions.

4. Select **Publish**.
5. The status changes to **Published**. Select **View Page** to confirm the page is live at its public address.

> **Screenshot** `03-published.png` — Overview after publishing, showing Published status and the publication time.
> Alt: Page overview showing published status after the page was published.

## Draft Blocks Are The One Thing To Watch

Blocks you add are saved as **published** by default, so a normal page goes live complete. But a block can be set to **Draft** individually — in the block's **Status** field — and a draft block is left out of the public page even after the page is published.

When a page has blocks in that state, the Overview tab says so, and publishing opens a dialog with an extra option: **Also publish all unpublished page-owned blocks**. Tick it, or your page goes live missing exactly the parts you set aside.

Shared Slots are never included. The header and footer are shared across pages, so they are reviewed and published separately, on purpose.

## Notes

- Only published pages are visible to visitors. Draft and In Review pages are not reachable on the public site.
- If you do not see a Publish action, your role can prepare content but not publish it. Use **Submit for Review** and someone with publishing rights takes it from there.
- Made a mistake after publishing? Nothing is lost. Content changes are captured as revisions, and **Revision History** lets you look back and restore.

**Next:** [Rich Text: Formatting, Lists, And Links](/guides/rich-text)
