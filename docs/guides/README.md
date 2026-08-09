# Guide Drafts

These Markdown files are **drafts**, not the published copy.

They exist so guide wording can be reviewed in a pull request before anything is created on the documentation site. Once a guide is built in the CMS through the Internal Content API, the CMS page is authoritative: later corrections are made there, and these files are not re-synced over the live pages.

Do not add `cms_sync: true` front matter here. That flag belongs to the reference docs under `docs/`, which use the Markdown-to-CMS sync workflow. Guides use a different pipeline; see [`docs/user-guides-plan.md`](../user-guides-plan.md).

## Front Matter

```yaml
guide: true
guide_slug: create-a-page
guide_series: B
guide_order: 5
cms_site: docs-site
cms_locale: en
cms_path: /guides/create-a-page
cms_title: Create A Page
cms_layout: docs
card_description: Add a new page to a site and save it as a draft.
card_thumbnail: 00-card.png
```

`card_description` and `card_thumbnail` feed the card on the `/guides` index page.

## Screenshot Placeholders

Screenshots are not embedded as Markdown images, because the published page uses real Image blocks with a `media_id`. Mark the position and the intent instead:

```markdown
> **Screenshot** `01-pages-list.png` — Pages list with the New Page button visible.
> Alt: Pages list in the WebBlocks CMS admin panel.
```

The file name is relative to `webblocks-cms-videos/assets/screenshots/guides/<guide-slug>/`. The build step uploads that file, then inserts an Image block at that position with the given alt text.

Screenshots deliberately do **not** live in this repository. `docs/` is not `export-ignore`d, so anything under it ships inside the Composer package to every install; a guide series' worth of PNGs has no business there. They sit with the capture script in the video project instead, and the published copies live in the CMS Media Library.

## Verification Status

Step wording was written from the source views and the English admin language file. Every step still has to be walked through on the demo installation while capturing screenshots. Anything that does not match the real screen is a bug in the draft, not in the product — fix the draft.
