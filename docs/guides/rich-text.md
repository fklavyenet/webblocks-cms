---
guide: true
guide_slug: rich-text
guide_series: C
guide_order: 11
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/rich-text
cms_layout: docs
cms_title: 'Rich Text: Formatting, Lists, And Links'
card_description: Bold, italic, links, and nested lists in one editable block.
card_thumbnail: 01-editor.png
---

# Rich Text: Formatting, Lists, And Links

**Goal:** Write a passage that needs more than plain sentences.
**Time:** 3 minutes
**You need:** A page with a Main slot you can edit

## Steps

1. In the Main slot, select **Add Block**, then choose **Rich Text**.
2. Type your text in the editor. The toolbar gives you **B**, *I*, strikethrough, inline `Code`, **Link**, bullet and numbered lists, indent and outdent, **Quote**, and **Clear**.
3. Select some text and use **Link** to turn it into a link.
4. Start a list by selecting the list button, or by typing `- ` or `1. ` at the start of a line. Inside a list, **Tab** and **Shift+Tab** change the level.
5. Select **Save New Block**.

> **Screenshot** `01-editor.png` — The Rich Text block form with the toolbar and formatted content.
> Alt: Rich Text block editor showing the formatting toolbar and a formatted paragraph with a list.

> **Screenshot** `02-rendered.png` — The same content rendered on the public page.
> Alt: Public page showing formatted rich text with bold, italic, and a bullet list.

## Example

```text
Every studio project leaves a trail: sketches that went nowhere, a palette we
argued about for a week, and the one decision that made the rest obvious.

We write those down here. Expect **short posts**, the occasional *correction*,
and links to the work itself.

- What we tried
- What we kept
- What we would do differently
```

## Shortcuts Worth Knowing

- **Ctrl/Cmd + B** bold, **Ctrl/Cmd + I** italic, **Ctrl/Cmd + K** link
- Type `> ` at the start of a line to begin a quote
- **Clear** strips formatting from the selection — useful after pasting from a word processor

## Notes

- Rich Text deliberately does not do headings. Use a **Header** block instead, so the page keeps a real heading structure that screen readers and search engines can follow.
- Formatting is limited on purpose: paragraphs, bold, italic, strikethrough, inline code, links, quotes, and nesting lists. Anything the editor refuses to keep was never going to render safely.
- Pasted markup is cleaned up, not trusted. If you need raw markup, that is the [HTML block](/guides/code-and-html) — and it has its own warnings.
- For a plain paragraph with no formatting at all, **Plain Text** is lighter.

**Next:** [Hero: Build An Opening Section](/guides/hero)
