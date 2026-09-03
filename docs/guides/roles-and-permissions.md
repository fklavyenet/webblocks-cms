---
guide: true
guide_slug: roles-and-permissions
guide_series: H
guide_order: 38
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/roles-and-permissions
cms_layout: docs
cms_title: 'Roles: Who Can Do What'
card_description: The three roles, what each one reaches, and how to add a colleague.
card_thumbnail: 02-add-user.png
---

# Roles: Who Can Do What

**Goal:** Add a colleague with the right role and the right sites.
**Time:** 3 minutes
**You need:** Super admin access

## Steps

1. Select **System → Users**. Filter by **Role** or **Status** to find someone.

> **Screenshot** `01-users.png` — The Users list with its filters.
> Alt: Users screen listing accounts with search, role, and status filters.

2. Select **Add User**.
3. Fill in **Name**, **Email**, **Password**, and **Confirm Password**.
4. Choose the **Role**: **Super admin**, **Site admin**, or **Editor**.
5. Tick the **sites** this person works on, and leave **Active account** on.

> **Screenshot** `02-add-user.png` — The Add User form with role and site access.
> Alt: Add user form showing name, email, password, role, and per-site access checkboxes.

6. Select **Create**.

## The Three Roles

| Role | Reaches |
| --- | --- |
| **Editor** | Content on their assigned sites: pages, blocks, media, navigation |
| **Site admin** | Everything an editor reaches, plus site-level settings for their sites |
| **Super admin** | The whole installation: sites, users, system settings, updates, backups |

Site access is separate from the role. An editor assigned to one site cannot see another site's pages at all, which is what makes a multisite installation safe to share.

## Notes

- **Give the smallest role that does the job.** Most people who "need admin" need Site admin, and most who need Site admin are editors who hit one blocked screen.
- A sidebar with fewer items is the role working. Before raising someone's role, check which single screen they actually needed.
- Publishing rights come with the role. If someone should write but not publish, Editor plus a review step is the arrangement — see [Draft, Review, And Publish](/guides/draft-review-publish).
- **Deactivating beats deleting** when someone leaves. Their name stays readable in revision history instead of becoming a mystery.
- Passwords are set here at creation. Nobody else should ever need to know one — that is what the reset flow is for.

**Next:** [Connect Your AI Safely](/guides/personal-ai-tokens)
