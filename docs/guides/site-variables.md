---
guide: true
guide_slug: site-variables
guide_series: J
guide_order: 47
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/site-variables
cms_layout: docs
cms_title: Site Variables
card_description: Write a value once, use it across the site, change it in one place.
card_thumbnail: 01-variables.png
---

# Site Variables

**Goal:** Stop retyping the same email address, phone number, or address on every page.
**Time:** 3 minutes
**You need:** Site settings access

## Steps

1. Open **Sites**, edit the site, and go to the **Variables** tab.

> **Screenshot** `01-variables.png` — The Variables tab with two variables defined.
> Alt: Site variables tab listing reusable public tokens with their keys and values.

2. Select **Add Variable**.
3. Fill in the **Key** — it is normalised to lowercase `snake_case` — and the **Value**. **Label** is an optional admin-facing name so colleagues know what it is for.
4. Leave **Enabled** ticked, set a **Sort Order** if you care about the list order, and select **Save variable**.

> **Screenshot** `02-add-variable.png` — The Add Site Variable dialog.
> Alt: Add site variable dialog showing the public token, label, key, value, and enabled fields.

5. Use it in content by writing the token: `{{ site.support_email }}`.

## Example

```text
Label: Support email
Key:   support_email
Value: hello@atlas-studio.test
Token: {{ site.support_email }}

Label: Office address
Key:   office_address
Value: Prinzessinnenstrasse 20, 10969 Berlin
Token: {{ site.office_address }}
```

## How Replacement Works

Tokens are replaced **only during public rendering and search indexing**. In the admin the raw token text stays exactly as you typed it, so you always see what is stored rather than a value pretending to be content.

Only **enabled** variables are replaced. An unknown or misspelled token is left alone rather than blanked — so a typo shows up as `{{ site.suport_email }}` on the page instead of an empty space you never notice.

## Notes

- **The value is plain text.** HTML in a variable is not executed, which is deliberate: a variable is a value, not a way to inject markup.
- Variables are **per site**. Two sites with the same support address need it defined twice — the trade for keeping sites independent.
- Good candidates: contact details, opening hours, a company registration number, a repeated brand phrase. Anything you would otherwise search and replace across twenty pages.
- Bad candidates: anything secret. These are public tokens rendered on public pages.
- Disabling a variable leaves the token visible in the output. If you want it gone, remove it from the content too.
