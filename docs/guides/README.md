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

## Maintenance And Recapture

The repeatable capture environment is the separate `webblocks-cms-showcase`
consumer application. Pin it to the exact published CMS release, reset its
disposable database, and use its host-owned deterministic fixtures. Do not use
a local path package for a published-product screenshot.

Every guide or docs update must answer all of these before publication:

- Did an admin label, control, workflow, permission, layout, or visible summary change?
- Does each existing screenshot still describe the current screen accurately?
- Does the showcase seeder produce every state needed for the capture after a clean reset?
- Was the capture made at 1440x900, 2x scale, English, light theme, and the documented role?
- Is the generated version/locale manifest present and do its hashes match the reviewed PNGs?
- Are all names, domains, emails, messages, and tokens fictional and non-sensitive?
- Were approved files uploaded to the `Guides` Media Library folder with useful alt text?
- If the guide appears as a hand-built card on an index page, was its card header image added and publicly verified too?
- Do the guide title and section headings carry explicit `h1`/`h2` variants so the standard intro divider and Goal/Time/You need panel render correctly?
- Was the live page changed through a staged update, previewed, explicitly promoted, and publicly verified?

Recapture only changed screens, but verify every reused screenshot. When the old
image is materially wrong and a safe replacement is not ready, remove it rather
than publishing misleading UI.
