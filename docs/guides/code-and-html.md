---
guide: true
guide_slug: code-and-html
guide_series: C
guide_order: 16
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/code-and-html
cms_layout: docs
cms_title: Code And HTML Blocks
card_description: Show a code sample properly — and know when the HTML block is the wrong answer.
card_thumbnail: 01-code-form.png
---

# Code And HTML Blocks

**Goal:** Present a code sample, and understand the one block that can break a page.
**Time:** 3 minutes
**You need:** A page and something to show

## Steps

1. Select **Add Block**, then choose **Code**.
2. Fill in **Title**, and use **Filename / Language Label** for the small label above the sample — a filename reads better than a language name.
3. Paste the sample into **Code**, and set **Syntax Language** (`php`, `bash`, `html`, `js`, and so on).
4. Select **Save New Block**.

> **Screenshot** `01-code-form.png` — The Code form with title, filename label, sample, and language.
> Alt: Code block form showing the title, filename label, code sample, and syntax language.

5. For markup that no block can express, select **Add Block** and choose **HTML (Trusted)**.
6. Paste your markup into **Trusted HTML** and select **Save New Block**.

> **Screenshot** `02-html-form.png` — The HTML block with an embed snippet.
> Alt: Trusted HTML block form containing an embed snippet.

> **Screenshot** `03-rendered.png` — The code sample rendered on the public page.
> Alt: Public page showing a syntax-highlighted code sample with a filename label.

## Example

```text
Code
  Title:              Publishing a page from the terminal
  Filename / Label:   publish.sh
  Syntax Language:    bash
  Code:               curl -X POST "$CMS/webadmin/api/pages/42/publish" \
                        -H "Authorization: Bearer $TOKEN" \
                        -H "Content-Type: application/json" \
                        -d '{"include_page_owned_blocks": true}'
```

## Read This Before Using The HTML Block

The block is called **HTML (Trusted)** because the CMS stops checking at that point. What you paste is what renders.

That means a stray unclosed tag can break the layout of the whole page, pasted tracking code runs on every visit, and an embed from a site you do not control can change what it serves you later. None of it is versioned in a way an editor can reason about, and none of it adapts when the site's design changes.

Before you reach for it, check:

- Is there a real block for this? Video, audio, download, gallery, table, and contact form all exist.
- Is this an embed? Ask whether the provider is one you would be comfortable seeing in your site's markup a year from now.
- Would a developer rather add a proper block type? Often the answer is yes, and it takes an hour.

Use it when the answer to all three is no. Then keep the snippet as small as it can be.

## Notes

- **Code** is for showing code to a reader. It never executes anything, and it is the right block for every snippet in documentation.
- **Syntax Language** only affects highlighting. Leaving it empty renders the sample as plain text.
- The filename label is free text: `publish.sh`, `composer.json`, or `Terminal` all work.
- Long samples scroll horizontally rather than wrapping, so trim yours to the lines that matter.
