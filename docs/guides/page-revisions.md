---
guide: true
guide_slug: page-revisions
guide_series: H
guide_order: 37
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/page-revisions
cms_layout: docs
cms_title: 'Revisions: See History And Restore'
card_description: Find out what changed, who changed it, and put it back.
card_thumbnail: 01-version-history.png
---

# Revisions: See History And Restore

**Goal:** Compare a saved page version, preview it safely, and restore it only after approval.
**Time:** 4 minutes
**You need:** A page that has been edited at least once

## Steps

1. Open the page and select **Version History**.
2. Work down the list, newest first. Each row tells you **when**, **what changed**, and **who** did it — with the source (Admin or API) and event type.
3. Open the saved version you want to inspect. Compare its page fields, translations, slots, and blocks with the current page.

> **Screenshot** `01-version-history.png` — The revision history with workflow and block events.
> Alt: Page version history listing saved versions with author, source, and review actions.

> **Screenshot** `02-version-review.png` — A selected saved version compared with the current page before any restore action.
> Alt: Version review showing readiness and field-level differences for a saved page version.

4. Select **Prepare Restore Preview**. The CMS creates a private restore candidate; the current page is still unchanged.
5. Open the candidate preview and check the rendered page at both wide and narrow widths.

> **Screenshot** `03-restore-candidate.png` — The version review after the private restore candidate has been prepared.
> Alt: Version review showing a prepared restore candidate that can be previewed, applied, or discarded.

6. Return to the candidate and select **Apply Restore**. If the preview is wrong, select **Discard Candidate** instead.
7. Re-open **Version History** and confirm the pre-restore and post-restore safety versions.

## Why Restoration Has Two Steps

Preparing a candidate is deliberately separate from applying it. It gives you a rendered preview without changing the current page, and the CMS rejects the candidate if the page changes after it was prepared. Prepare a fresh candidate from the version you intend to restore when that happens.

## What Gets Recorded

Revisions are captured automatically. You will see entries for page creation, content and block changes, translation and slot edits, and every workflow move — including `draft to in_review` and back.

The **Audit** column names the person and the source. An edit made through the Internal Content API says so, which is how you tell a colleague's work from a tool's.

## Notes

- **This is page-level history, not a backup.** It restores content on one page. It does not replace system backups or site export packages, and it will not help you if the database is gone.
- Shared Slots have their own separate history. Restoring a page does not touch the header it renders — see [Update A Shared Slot Safely](/guides/update-a-shared-slot).
- Preparing a restore candidate does not publish or change the page. Applying it keeps the page's current workflow state.
- Only one active restore candidate is kept for a page. Discard an unwanted candidate before preparing another.
- The history grows with every edit, so scan the **Revision** column rather than the timestamps. "Block created" and "Workflow updated" find the moment faster than the clock does.

**Next:** [Roles: Who Can Do What](/guides/roles-and-permissions)
