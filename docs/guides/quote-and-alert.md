---
guide: true
guide_slug: quote-and-alert
guide_series: C
guide_order: 14
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/quote-and-alert
cms_layout: docs
cms_title: Quote And Alert
card_description: Pull out someone's words, or flag something the reader must not miss.
card_thumbnail: 01-quote-form.png
---

# Quote And Alert

**Goal:** Set a quotation apart, and give a notice the visual weight it needs.
**Time:** 3 minutes
**You need:** A page, and something worth quoting or announcing

## Steps

1. Select **Add Block**, then choose **Quote**.
2. Put the words in **Quote Text**, the person in **Author**, and where they are from in **Source**.
3. Pick a **Quote Variant**: `default` for a pull quote inside an article, `testimonial` for a customer voice on a marketing page.

> **Screenshot** `01-quote-form.png` — The Quote form with text, author, source, and variant.
> Alt: Quote block form showing the quote text, author, source, and variant fields.

4. Select **Add Block**, then choose **Alert**.
5. Fill in **Title** and **Content**, then pick a **Variant**: `info`, `success`, `warning`, or `danger`.
6. Select **Save New Block**.

> **Screenshot** `02-alert-form.png` — The Alert form with title, content, and variant.
> Alt: Alert block form showing title, content, and the variant selector.

> **Screenshot** `03-rendered.png` — Both blocks rendered on the public page.
> Alt: Public page showing a testimonial quote followed by an information alert.

## Example

```text
Quote
  Quote Text: They shipped the first working version before we had finished
              arguing about the brief.
  Author:     Dana Reeves
  Source:     Head of Product, Northwind
  Variant:    testimonial

Alert
  Title:   Studio closed 24–31 December
  Content: Messages sent over the break are answered in the first week
           of January.
  Variant: info
```

## Choosing An Alert Variant

| Variant | Use it for |
| --- | --- |
| `info` | Neutral context — opening hours, a note about scope |
| `success` | Something went right, or a change has landed |
| `warning` | Act carefully; there is a consequence |
| `danger` | Something is broken, unsafe, or about to be lost |

## Notes

- Colour is not the message. The **Title** has to make sense in black and white, because for some readers it will be.
- An Alert on every page trains people to skip alerts. Keep them for the sentence you would repeat out loud.
- Quote is for someone else's words. Your own emphasis belongs in [Rich Text](/guides/rich-text) or a Header.
- Rich Text also has a quote button, which is the right tool for a quotation inside a paragraph flow. The Quote block is for a quotation that stands alone.

**Next:** [Link Lists And Tables](/guides/link-lists-and-tables)
