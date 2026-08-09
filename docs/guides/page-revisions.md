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
card_thumbnail: 01-revisions.png
---

# Revisions: See History And Restore

**Goal:** Read a page's history and restore an earlier state.
**Time:** 3 minutes
**You need:** A page that has been edited at least once

## Steps

1. Open the page and select **Revision History**.
2. Read the summary at the top: the site, the current workflow state, and how many revisions exist.
3. Work down the list, newest first. Each row tells you **when**, **what changed**, and **who** did it — with the source (Admin or API) and the event type.

> **Screenshot** `01-revisions.png` — The revision history with workflow and block events.
> Alt: Page revision history listing workflow and block changes with author and restore actions.

4. Found the state you want back? Select **Restore** on that row.
5. Check the page afterwards. Restoring is itself a change, so it appears at the top of the history — which means you can undo the undo.

## What Gets Recorded

Revisions are captured automatically. You will see entries for page creation, content and block changes, translation and slot edits, and every workflow move — including `draft to in_review` and back.

The **Audit** column names the person and the source. An edit made through the Internal Content API says so, which is how you tell a colleague's work from a tool's.

## Notes

- **This is page-level history, not a backup.** It restores content on one page. It does not replace system backups or site export packages, and it will not help you if the database is gone.
- Shared Slots have their own separate history. Restoring a page does not touch the header it renders — see [Update A Shared Slot Safely](/guides/update-a-shared-slot).
- Restoring does not publish. A restored published page stays published; a restored draft stays a draft.
- The history grows with every edit, so scan the **Revision** column rather than the timestamps. "Block created" and "Workflow updated" find the moment faster than the clock does.

**Next:** [Roles: Who Can Do What](/guides/roles-and-permissions)
