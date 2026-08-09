# WebBlocks CMS User Guides Plan

This is the plan for a task-oriented "How do I ...?" guide series for WebBlocks CMS, published under a dedicated subfolder of the public documentation site.

It is a planning document. It does not add a runtime feature, endpoint, command, or migration.

## Why A Separate Series

The repository already ships reference documentation under `docs/`. Those files answer *what a feature is and how it behaves*. They are written for operators, integrators, and AI tooling, and they are mostly prose.

The guide series answers a different question: *how do I complete one concrete task, right now, with my hands on the screen*. It is written for editors and site owners who never read a contract document.

Both stay separate on purpose:

| | Reference docs (`/docs/*`) | Guides (`/guides/*`) |
| --- | --- | --- |
| Question | How does it work? | How do I do it? |
| Audience | Operator, integrator, AI tooling | Editor, site owner |
| Shape | Prose, contracts, field tables | Numbered steps, screenshots, sample content |
| Length | As long as correctness needs | 1 screen, 3-7 steps, 2-4 screenshots |
| Source | `docs/*.md` | `docs/guides/*.md` |

Guides cross-link *down* to reference docs for detail. Reference docs do not depend on guides.

## Publication Target

Publish under `/guides/` on the public documentation site, not under `/docs/`.

- `/guides` - index page, a card grid grouped by series
- `/guides/create-a-page` - one guide, one task
- The existing `docs` page layout is reused, including the "On this page" rail
- Every guide ends with one explicit **Next** link, so the series reads as a path and not as a pile

Slugs are verb-first and stable: `create-a-page`, `add-heading-and-text`, `add-an-image`. Never number the slug; ordering belongs to the index and to the Next link, so guides can be reordered without breaking URLs.

## Guide Anatomy

Every guide uses the same skeleton. This is what makes the series feel like one product and not a wiki.

```markdown
# Add An Image To A Page

**Goal:** Place an image inside a page section with correct alt text.
**Time:** 2 minutes
**You need:** Editor access to a site, one image file

## Steps

1. ...            <- one action per step, starting with a verb
2. ...

![...](screenshot)  <- 2-4 screenshots, placed at the step they illustrate

## Example

...                 <- copy-paste-ready sample content

## Notes

...                 <- 1-3 short warnings or tips, optional

**Next:** [Build A Gallery](/guides/build-a-gallery)
```

Hard rules:

- One task per guide. If a guide needs two goals, it is two guides.
- Maximum 7 steps. More than that means the task should be split.
- Every step is one clickable action, named exactly as the admin UI names it.
- Every guide carries real, usable sample content, not `Lorem ipsum`.
- No screenshot without a step it belongs to, and no step chain longer than 3 without a screenshot.

## Curriculum

Roughly 45 guides in 10 series. Ship in phases; do not write all of them before the first phase is reviewed.

### Series A - Get Oriented

1. Sign in to the admin panel
2. The admin panel in 2 minutes (what lives where)
3. Page, Layout, Slot, Block: the model in plain language
4. Change your own language and profile

### Series B - Your First Page

5. Create a new page
6. Add a heading and a paragraph
7. Add an image
8. Preview the page
9. Publish the page
10. Change the page address (slug and path)

### Series C - Text And Structure Blocks

11. Rich Text: formatting, lists, and links
12. Hero: build an opening section
13. Buttons and CTA
14. Quote, Callout, and Alert
15. Lists, Link Lists, and Table
16. Code and HTML blocks (and when not to use them)

### Series D - Images And Media

17. Upload files to the Media Library
18. Alt text, captions, and image variants
19. Build a gallery
20. Add a video or audio block
21. Offer a downloadable file
22. Organize media in folders

### Series E - Page Layout

23. Section and Container: control page width
24. Columns and Grid
25. Cluster and spacing
26. Card, Feature Grid, Stat Card
27. Choose and change a page layout

### Series F - Structured Content

28. FAQ and Accordion
29. Tabs
30. Slider
31. Table of contents and Breadcrumb

### Series G - Navigation And Reusable Areas

32. Build a navigation menu
33. Build a header as a Shared Slot
34. Build a footer or CTA as a Shared Slot
35. Update a Shared Slot safely

### Series H - Working As A Team

36. Draft, review, and publish
37. Revisions: see history and restore
38. Roles: who can do what
39. Duplicate, move, and archive pages

### Series I - Visitors

40. Add a contact form and read messages
41. Comments and ratings moderation
42. Site search
43. SEO fields, social image, and favicon

### Series J - Multiple Sites And Languages

44. Add a language to a site
45. Translate a page
46. Manage more than one site
47. Site variables

Operator and developer topics (backups, transfer, updates, API tokens, plugin install, Laravel integration) stay in reference docs and in the video series. They are not part of this guide series; the audience is different.

### Phasing

- **Phase 1 (pilot):** guides 1, 5, 6, 7, 9 - exactly what was asked for, end to end, plus the index page. Review the format before writing more.
- **Phase 2:** rest of Series A and B, Series C, Series D.
- **Phase 3:** Series E, F, G.
- **Phase 4:** Series H, I, J.

## Screenshots

Screenshots are the expensive part and the reason this plan needs discipline.

### Source Installation

Reuse the sanitized demo installation and the fictional brand already defined for the video series at `webblocks-cms-videos/demo-cms` (site: *Atlas Studio*, admin: *Alex Morgan*, domain: `atlas-studio.test`). Guides and videos then show the same content, which makes the two series reinforce each other.

Never capture from a customer install. No `.env`, tokens, real names, real addresses, production domains, local paths, or debug output.

### Capture Rules

- Viewport 1440x900, captured at 2x
- Browser chrome cropped out
- Crop to the relevant admin region; a full dense admin screen is unreadable at guide width
- Same user, same site, same theme across the whole series
- Meaningful content, never empty states
- One highlight per screenshot at most (arrow or box), drawn in the brand accent

### Naming And Storage

Screenshots live with the capture script in the video project, not in the CMS package repository: `docs/` is not `export-ignore`d, so anything stored there ships inside the Composer package to every install.

Path: `webblocks-cms-videos/assets/screenshots/guides/<guide-slug>/<nn>-<what>.png`

```text
webblocks-cms-videos/assets/screenshots/guides/create-a-page/01-pages-list.png
webblocks-cms-videos/assets/screenshots/guides/create-a-page/02-new-page-form.png
webblocks-cms-videos/assets/screenshots/guides/add-an-image/01-image-block-form.png
```

In the CMS, upload the same files into a Media Library folder named `guides` and set `alt_text` at upload time. Media upload is available through the Internal Content API with the `media.upload` capability, so the screenshot set can be pushed with the same token flow used for docs sync.

### Budget

At 3 screenshots per guide, the full series is ~135 images. Assume every screenshot has to be re-captured when the admin UI changes, and prefer cropped detail shots over full-screen shots: a cropped block editor survives a sidebar redesign, a full-screen shot does not.

## Authoring And Publishing Pipeline

The CMS owns the content. Markdown files in the repository are a drafting and review step, not the long-term source of truth.

1. **Draft in Markdown.** Write `docs/guides/<slug>.md` as a working draft so the wording can be reviewed in a pull request before anything touches the site. Once the guide lives in the CMS, this file is history, not a mirror.
2. **Capture screenshots** into `webblocks-cms-videos/assets/screenshots/guides/<slug>/`.
3. **Upload screenshots** through `POST /webadmin/api/media` (`media.upload`) into the Media Library folder `guides`, setting `alt_text` at upload time. Keep the returned media ids.
4. **Create the page** through the Internal Content API: one page per guide at `/guides/<slug>`, created as a draft.
5. **Build the blocks** through the API - Header, Rich Text, Image (`media_id` from step 3), and so on - in the order the guide reads.
6. **Review the draft** on the site, then publish explicitly. Publishing requires `content.publish` and is never implied.
7. **Later edits happen in the CMS**, in the admin panel or through the API. Do not re-sync from Markdown and overwrite editor changes.

Because pages are built block by block through the API, screenshots are ordinary Image blocks with a real `media_id`. There is no Markdown-image gap to solve and no need to extend the Markdown-to-CMS sync convention. Editors keep full control of every guide after publication, which is the point.

Page settings should still record where a guide came from, using the allowlisted `source_sync` setting, so a guide can be traced back to its draft and its screenshot set. That is provenance, not a sync contract.

## The Guides Index Page

`/guides` is a card grid: each card carries a thumbnail, a title, a one-line description, and a link into the guide.

### There Is No Automatic Page Listing In The CMS

**Superseded.** This section was written before the `page-list` block existed, and it is what prompted the block. The `page-list` block now renders published pages of a page type or path subtree for the current site and locale, so a `/guides` index no longer has to be composed card by card. See [the Page List block plan](page-list-block-plan.md) and the block's contract in [the inventory](inventory.md). The rest of this section is kept because the reasoning still explains what the surrounding blocks do and do not do.

This has to be said plainly, because it shapes the whole design. WebBlocks CMS has no collection, query, or archive block. There is nothing that renders "all pages of type X" on the public site.

What exists and what it actually does:

- `page_types` includes system rows named `Blog` and `Archive`, and pages carry `page_type_id`. Nothing on the public rendering side reads it. In source it is used only for export/import, site clone, promotion, search indexing, and excluding Shared Slot source pages - never to build a list.
- The `navigation-auto` block sounds automatic but is not a page query. It renders a navigation menu that an editor built by hand in the Navigation tree.
- `link-list`, `grid`, `columns`, `feature-grid` are all composition blocks. Their children are authored, not derived.

So the index is a manually composed page. That is fine for this series, and it is better than it sounds, because we build it through the API.

### Card Structure

The canonical composition, all of it supported by the current child rules:

```text
section
  header                      "Guides"
  grid  (columns: 3, gap: 6)
    card
      card_header  -> image        (screenshot thumbnail, media_id)
      card_body    -> header       (guide title)
                   -> plain_text   (one-line description)
      card_footer  -> button_link  (/guides/<slug>)
    card ...
```

`card` accepts only `card_header`, `card_body`, `card_footer`, but those three regions accept any content block, so an Image inside `card_header` is valid. Card also supports a background image plus `background_position` and `background_overlay` if a full-bleed card style is preferred over a thumbnail on top.

For a denser, image-free variant later - a per-series sub-list, for example - `link-list` with `link-list-item` children is the cheaper option.

### Keeping The Index In Sync

Forty-five hand-maintained cards would rot. The fix is not a CMS feature, it is our own tooling: the same script that publishes a guide also rebuilds the index page's card grid from the guide set. Each guide contributes its title, description, thumbnail media id, and path; the script replaces the grid's children in one API pass.

That gives the automatic behavior without asking the CMS for a collection block, and editors can still hand-edit the index afterwards - with the understanding that a rebuild overwrites the grid.

If auto-listing later becomes a recurring product need rather than a docs-site need, the honest shape for it is a dedicated block type (a "Page List" block scoped by page type, site, and locale), not a workaround. Worth noting as product feedback; out of scope for this series.

## Localization

Author in English, same as the rest of `docs/`. Turkish and German versions come later as page translations of the same guide pages, so the URL structure and screenshots are shared. Do not fork the Markdown per language.

Screenshots stay English in all locales for Phase 1-4; localized screenshots multiply the maintenance cost by the number of locales and are only worth it once the series is stable.

## Maintenance

- A guide is stale when the admin UI it shows changes. Add a guides check to the release checklist for releases that touch admin views.
- Keep a single owner for the series; a shared guide series drifts in tone within three contributors.
- Prefer text edits over re-shoots: if a label changed but the layout did not, fix the step text and leave the screenshot.

## Definition Of Done For Phase 1

- `/guides` index page live, with a card for each of the 5 pilot guides and the rest of the Series A-J outline listed as plain text
- 5 pilot guides published as separate CMS pages, built through the API
- ~15 screenshots plus 5 card thumbnails captured from the demo install and uploaded to the `guides` media folder with alt text
- The index rebuild script working end to end for those 5 guides
- Format reviewed against a real editor who has not used the CMS before
