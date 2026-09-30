# Guide Sources

These Markdown files are the source of truth for the published guide pages.

Changes are reviewed here first, then applied to the CMS as staged updates through the repository-owned documentation sync command. The CMS page is the published projection, not an independent editing source. If the live page has drifted from its source, synchronization must stop and report the difference instead of overwriting it silently.

Every guide carries `cms_sync: true` and a stable `cms_source_id`. See [`docs/user-guides-plan.md`](../user-guides-plan.md) for the publishing contract.

The `webblocksui.com` sandbox is a separate content workspace for the main site and other site-owned properties. Guide sync must not read source content or media from that workspace, and sandbox tooling must not treat this directory as its own editable content store.

## Front Matter

```yaml
cms_sync: true
guide: true
guide_slug: create-a-page
guide_series: B
guide_order: 5
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/create-a-page
cms_title: Create A Page
cms_layout: docs
cms_source_id: webblocks-cms:docs/guides/create-a-page.md
card_description: Add a new page to a site and save it as a draft.
card_thumbnail: 00-card.png
```

`card_description` and `card_thumbnail` feed the card on the `/guides` index page.

## Screenshots

Screenshots live under `docs/guides/media/<guide-slug>/` and are embedded at their intended reading position with ordinary Markdown image syntax:

```markdown
![Pages list with the New Page button visible](media/create-a-page/01-pages-list.webp)
```

The sync command uploads or replaces the file in the CMS Media Library, then inserts a native Image block with the Markdown alt text. Because these assets ship in the Composer package, keep only documentation media, prefer efficient WebP or PNG files, and avoid redundant full-screen captures.

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
