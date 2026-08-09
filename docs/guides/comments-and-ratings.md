---
guide: true
guide_slug: comments-and-ratings
guide_series: I
guide_order: 41
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/comments-and-ratings
cms_layout: docs
cms_title: Comments And Ratings
card_description: Let readers respond, and review what they say before it appears.
card_thumbnail: 03-comments-moderation.png
---

# Comments And Ratings

**Goal:** Collect feedback on a page and moderate it.
**Time:** 4 minutes
**You need:** A published page

## Steps

1. In the Main slot, select **Add Block**, then choose **Rating**. Set the **Heading**, and decide whether visitors may change a vote (**Vote changes**) and whether the **Public summary** is shown.

> **Screenshot** `01-rating-form.png` — The Rating block settings.
> Alt: Rating block form showing heading, vote changes, and public summary settings.

2. Select **Add Block** again and choose **Comments**. Set whether the **Comment form** is enabled, whether **Approved comments** are shown, how the **Author display** works, and the **Sort order**.

> **Screenshot** `02-comments-form.png` — The Comments block settings.
> Alt: Comments block form showing form enabled, approved comments, author display, and sort order.

3. Publish the page. Both blocks only collect anything once visitors can reach them.
4. Open **Engagement** to see what came in.

> **Screenshot** `05-engagement.png` — The Engagement overview.
> Alt: Engagement overview showing pending comments and average rating.

5. In **Comments**, each row shows the text, which page it came from, a **spam score**, and the actions: **Approve**, **Reject**, **Spam**.

> **Screenshot** `03-comments-moderation.png` — Comments awaiting moderation.
> Alt: Comments moderation screen listing pending comments with approve, reject, and spam actions.

6. **Ratings** is a read-only tally — there is nothing to approve, only to look at.

> **Screenshot** `04-ratings.png` — The ratings list.
> Alt: Ratings screen listing submitted ratings by page.

## Comments Arrive Pending

Nothing a visitor writes appears on the site until someone approves it. A new comment lands as **pending**, and the page shows only approved ones.

That is the right default, and it means comments need an owner. A form nobody moderates is a form that quietly collects spam and shows nothing.

## Notes

- **One vote per visitor.** The CMS recognises a repeat visitor and does not count them twice; **Vote changes** decides whether they may revise the vote they already cast.
- The **Spam** score is a signal, not a verdict. Read the comment before acting on the number.
- **Reject** and **Spam** are different: reject is editorial, spam trains the filter. Use the one you mean.
- Turning the **Comment form** off leaves existing approved comments visible while stopping new ones — the graceful way to close a discussion.

**Next:** [Site Search](/guides/site-search)
