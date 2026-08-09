# Feature requests from the webblocksui.com sandbox session (2026-08-08)

Context: `ui.docs.webblocksui.com` (site 4) documents WebBlocks UI. Its home page
is built from structured blocks, but the other 19 published doc pages each hold a
single `html` block carrying a raw copy of the corresponding static page's
`<main>`. We are migrating those 19 pages to structured blocks so they can be
updated and translated through the Internal Content API, the way site 2 and
site 3 already are.

A pilot conversion of `docs/architecture.html` (draft page 101,
`/architecture-block-pilot`) reached **0.9964** text fidelity against the static
source using only existing block types, and the rendered `link-list` markup
matches the static markup structurally. So the block model is sufficient for this
content. Four things stand between that pilot and the actual migration.

Items 1 and 3 are hard blockers: without them the migration cannot start. Item 2
is what makes it verifiable rather than a leap of faith. Item 4 is the only piece
of the page the block set genuinely cannot express.

---

## 1. The API cannot migrate a page away from an `html` block

**Staging an update for a published page fails when that page's current content
is an `html` block, so the 19 pages cannot be converted through the API at all.**

`create_staged_update_for_published_page` clones the source page's blocks into the
staged draft before the caller replaces them. The clone hits the html-block guard
and the whole plan is rejected:

```
POST /webadmin/api/content/validate
{"plan":{"mode":"create_staged_update_for_published_page","site":4,"locale":"en",
         "page":{"id":6},"expected_source_path":"/p/architecture",
         "managed_slots":["main"]}}

→ ok:false, code: block_type_not_api_writable
  path: plan.page.blocks
  "The html block type cannot be created, changed, moved, published, or deleted
   through the Internal Content API. …Build the design with structured blocks, or
   report a capability gap instead of falling back to raw HTML."
```

The guard's intent is right and should stay: the API must not *author* raw HTML.
But the operation being refused here is the opposite — removing raw HTML and
replacing it with structured blocks. The message even says "report a capability
gap instead of falling back to raw HTML", which is exactly what this is. As it
stands the guard permanently freezes every page that already contains an `html`
block: it can never be improved except by hand in the admin, one page at a time,
in three locales.

**Suggested shape.** Keep `create` and `update` of `html` blocks refused. Allow
the three operations that only ever *reduce* raw HTML:

1. **Skip the clone for managed slots.** In
   `create_staged_update_for_published_page`, a slot listed in `managed_slots`
   is going to be replaced wholesale, so its source blocks do not need to be
   cloned at all. Not cloning them removes the failure without loosening
   anything — this alone unblocks the migration and is the smallest change.
2. **Allow slot replacement to delete existing `html` blocks**, so
   `replace_slots` / `replace_staged_page_update` can drop them.
3. **Allow promote to delete the source page's `html` blocks** when the promoted
   slot replaces them.

If a narrower gate is preferred, gate these behind a distinct capability (e.g.
`content.html_block_removal`) or an explicit plan flag rather than the general
`content.apply`. Please keep the rejection message for `create`/`update`
unchanged.

**Reproduce:** any page on site 4 except page 4 (home) — e.g. page 6
`/p/architecture`, page 9 `/p/primitives`. The relevant service is
`src/Support/InternalContentApi/InternalContentPlanService.php`.

---

## 2. `html` block content is not readable through the API

**`GET /webadmin/api/blocks/{id}` on an `html` block returns no content, so a
migration cannot verify its own output against what it is replacing.**

The contract advertises `api_readable: true` for `html`, and the block renders
fine on the public page, but the read comes back empty:

```
GET /webadmin/api/blocks/150          (site 4, page 9 /p/primitives)

→ {"id":150,"type":"html","status":"published","settings":null,
   "translations":{"text":[],"button":[],"image":[]},"children":[]}
```

The same emptiness shows up on the page read: `GET /webadmin/api/pages/9` reports
one block with zero characters of text, while the live page serves ~7,600
characters.

This matters for the migration specifically. Converting 19 pages × 3 locales is
only responsible if each converted page can be diffed against the markup it
replaces — that is how we measured the pilot at 0.9964 fidelity and how we would
catch a section silently dropped in conversion. Right now the only way to obtain
the current content is to scrape the public page, which works for published pages
in the default locale and not at all for anything else.

**Suggested shape.** Return the stored markup on read for `html` blocks — as a
plain field, or in the `text` translation family alongside the other block types.
Read-only; the write guard in item 1 is a separate decision and we are not asking
to relax it here. If exposing trusted markup on the general block read is
unwelcome, a dedicated read (e.g. `GET /webadmin/api/blocks/{id}/raw` behind its
own capability) would serve the purpose equally well.

Alternatively, if `api_readable: true` is meant to describe only the block's
metadata, please correct the contract so tools do not expect content.

---

## 3. A page created through the API cannot get its layout's shared slots

**`create_draft_page` produces a docs page with empty page-owned `header` and
`sidebar` slots instead of the shared slots the layout expects, and nothing in
the API can bind them.**

Existing docs page (page 6):

```
layout: docs
slots: header → shared_slot 1 (docs-header)
       sidebar → shared_slot 2 (docs-sidebar)
       main   → page
```

Page created via `content/apply` mode `create_draft_page` with `layout: "docs"`
(page 101):

```
layout: docs
slots: header → page (empty)
       sidebar → page (empty)
       main   → page
       footer → page (empty)
```

The page therefore renders with no site header and no docs sidebar.
`POST /webadmin/api/pages/101/sync-layout-slots` returns `ok: true` and changes
nothing — it appears to sync the slot *set* against the layout but not the
shared-slot bindings.

**Suggested shape.** Either of these solves it:

- `create_draft_page` inherits the layout's default shared-slot bindings, the way
  creating a page in the browser admin does; or
- `sync-layout-slots` binds any slot whose layout definition declares a default
  shared slot; or
- an explicit binding write (plan field or endpoint) that points a slot at a
  shared slot id.

This is about **binding a slot to a shared slot**, not writing content into one.
The existing rule that shared-slot-backed slots are rejected for content writes
is correct and should stay.

Relevant: `src/Http/Controllers/Admin/PageSlotController.php`,
`src/Http/Controllers/InternalContentApi/InternalContentResourceController.php`.

---

## 4. No block can express the docs previous/next footer

Every static docs page ends with:

```html
<footer class="wb-content-footer">
  <a href="getting-started.html" class="wb-content-prev">
    <span class="wb-content-prev-label">Previous</span>
    <span class="wb-content-prev-title">Getting Started</span>
  </a>
  <a href="foundation.html" class="wb-content-next">
    <span class="wb-content-next-label">Next</span>
    <span class="wb-content-next-title">Foundation</span>
  </a>
</footer>
```

Nothing in the block set produces it:

- `button_link` renders only `wb-btn wb-btn-primary` or `wb-btn wb-btn-secondary`
  (`Block::buttonLinkVariantClass()`), so it can only ever look like a button.
- `rich-text` cannot carry the markup: `SafeRichTextRenderer`
  (`src/Support/Formatting/SafeRichTextRenderer.php`) allows only
  `p, ul, ol, li, blockquote` plus `strong, em, code, s, a, br`, with no `div`
  and no class attributes.
- `wb-content-footer`, `content-prev` and `content-next` appear nowhere in the
  package.

**We are explicitly not asking for a content block here.** Previous/next is
navigation chrome derived from the sidebar order, not page content — authoring it
by hand would mean 19 pages × 3 locales of duplicated links that silently rot
whenever a page is reordered or renamed.

**Suggested shape: a `content-pager` navigation block**, modelled on the existing
`breadcrumb` block, which already derives its content from the page hierarchy and
sits once in the docs header shared slot (site 4, shared slot 1 `docs-header`
contains exactly `breadcrumb` + `header-actions`). By analogy `content-pager`
would:

- resolve the previous and next entries from the docs navigation menu order
  (the same menu that feeds `sidebar-navigation`), skipping group headings and
  unpublished pages;
- take link titles from the target page's translation in the render locale, and
  the "Previous"/"Next" labels from a public translation catalog, the way the
  1.52.2 branded 404 takes `errors.not_found`;
- resolve hrefs through `PageRouteResolver::localizedPublicUrl()` so a visitor on
  `/tr/...` stays in their locale;
- render nothing at the first and last entry, and emit only the side that exists;
- sit once in the docs layout's footer/shared slot, needing no per-page
  authoring.

That would let the docs pages be fully block-built with no per-page navigation
maintenance.

---

## Minor, already-known defects (unrelated to the migration, cheap to fix)

**`link-list-item` internal URLs are not locale-aware.** 1.52.1 gave
`button_link` locale-following internal URLs via
`PageRouteResolver::localizedPublicUrl()`, called from
`resources/views/pages/partials/blocks/button_link.blade.php`. The same call is
missing from `link-list-item.blade.php`, so footer link lists on a translated
page still point at default-locale paths and drop the visitor out of their
locale. We are currently patching this client-side in each site's `site.js`,
which we would like to delete. Reproduce: `https://cms.webblocksui.com/tr`,
footer link list.

**The navbar's "hide the current page's own link" rule does not recognise a
non-default-locale home.** A page-type item pointing at the home page is
correctly hidden on `/`, but renders on `/tr` and `/de`. Reproduce:
`https://webblocksui.com/` shows no "Home" item; `https://webblocksui.com/tr`
shows "Anasayfa". Cosmetic, but it makes the navbar differ per locale.
