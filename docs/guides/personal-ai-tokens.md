---
guide: true
guide_slug: personal-ai-tokens
guide_series: H
guide_order: 39
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/personal-ai-tokens
cms_layout: docs
cms_title: 'Connect Your AI Safely'
card_description: Create a personal token so an AI can work within your role and selected sites.
card_thumbnail: 01-personal-ai-tokens.png
---

# Connect Your AI Safely

**Goal:** Give an AI access to CMS work without sharing your password or exceeding your permissions.
**Time:** 4 minutes
**You need:** An active Editor, Site admin, or Super admin CMS account

## Steps

1. Open your profile and select **Manage AI Tokens**.
2. Under **Create a personal token**, enter a name that identifies the AI or job.
3. Select only the sites the AI needs.
4. Review the capability groups. Leave publish and destructive actions off unless the job explicitly requires them and your role permits them.
5. Choose when the token expires.
6. Under **Network controls**, optionally enter an exact client IP or CIDR network and choose the request limit.

> **Screenshot** `01-personal-ai-tokens.png` — Personal AI Tokens form with sites, capabilities, expiry, and Network controls.
> Alt: Personal AI token creation form showing scoped site and permission controls.

7. Select **Create Token**.
8. Copy the full token or generated environment example immediately. The token is not shown again.
9. Give the copy-ready setup prompt to the intended AI. Its first request should be `GET /webadmin/api`.

## After Connecting

- Use **Edit** to change sites, capabilities, expiry, or network controls without rotating the secret.
- Use **Activity** to review the latest ten API requests and denied attempts.
- Use **Revoke** immediately when the job ends or the secret may be exposed.
- Use **Delete** only when you no longer need the token or its activity history.

Your AI is continuously checked against your current account, role, assigned sites, page workflow, token capabilities, and network policy. Losing a CMS permission also removes it from the AI on its next request.

**Next:** [Duplicate, Move, And Archive Pages](/guides/duplicate-move-archive)
