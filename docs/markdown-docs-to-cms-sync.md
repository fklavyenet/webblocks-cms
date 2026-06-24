# Markdown Docs To CMS Sync

This document is an operational runbook for trusted AI/operator workflows that sync changed Markdown documentation files from the repository `docs/` folder into source-linked WebBlocks CMS documentation pages. It is documentation-only product guidance. It does not add a runtime sync engine, endpoint, migration, Artisan command, script, job, queue, database table, release process, or connection to any live target.

## Purpose

Markdown files under `docs/` remain the source of truth for technical WebBlocks CMS documentation. CMS documentation pages are generated draft or published derivatives of those Markdown files. The workflow exists so documentation changes made during normal product development can be reflected in a CMS documentation site without treating the CMS page as the authoritative copy.

This is an AI/operator workflow, not automatic runtime synchronization. The CMS should not watch the repository, pull Markdown files, or mutate content on its own. A trusted operator or AI tool plans, validates, and optionally applies safe draft updates through the Internal Content API.

The model must work for any target CMS install or documentation site. Documentation and reports must stay generic and must not include a real target-site name, real domain, real API token, local absolute path, raw log, or environment value.

## Short Operator Commands

Future operators should be able to use concise prompts such as:

```text
Update the CMS documentation site from changed Markdown files under docs/.
Plan Docs -> CMS updates for the changed docs/ Markdown files.
Validate and apply safe draft updates for changed docs/ Markdown files; do not publish.
```

From these short commands, the AI/operator must infer the standard workflow:

- use Markdown files under `docs/` as the candidate source set
- prefer changed files rather than a full docs tree scan
- read `cms_sync` front matter and source metadata
- discover the target CMS API from `GET /webadmin/api`
- use only discovered content contracts and block handles
- match source-linked CMS pages by source identity before path
- produce a per-file plan and validation report
- apply only when the command explicitly authorizes safe draft apply or the user approves the exact plan
- never publish unless the user explicitly requests publish and the token has `content.publish`

`Update` by itself means plan, validate, and apply safe draft changes only when the user's instruction clearly authorizes apply. It does not imply publish, navigation edits, live-page overwrite, media import, or browser automation.

## Candidate File Detection

Use this order to decide which Markdown files are candidates:

1. If the user provides an explicit file list, use that list.
2. Otherwise, use repository changed Markdown files under `docs/`.
3. Include newly added, modified, and renamed `.md` files.
4. Exclude archived release changelog files under `docs/releases/` unless explicitly requested.
5. Exclude internal AI, worklog, audit, or private planning docs when they are outside public documentation or marked internal.
6. Exclude files without `cms_sync` metadata unless the workflow is explicitly in planning or adoption mode.
7. Run a full `docs/` rescan only when the user explicitly requests a full rescan.

Changed-file detection is a source-selection step only. It must not mutate Git state, stage files, create release artifacts, or infer a target CMS install from repository remotes.

## Source Metadata

Markdown files opt in with front matter:

```yaml
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/contact-forms-and-messages
cms_title: Contact Forms and Messages
cms_layout: docs
cms_source_id: webblocks-cms:docs/contact-forms-and-messages.md
```

`cms_site` above is an example target site handle, not a real domain or install name. `cms_source_id` is the stable source identity. If a file moves, the source identity can remain unchanged so the target page can still be matched safely.

Metadata rules:

- `cms_source_id` is the stable source identity.
- `cms_path` is the intended CMS URL path.
- `cms_layout` defaults to `docs` when absent.
- `cms_locale` defaults to `en` when absent.
- `cms_title` defaults to the first H1 or a title derived from the file name when absent.
- the source hash is a SHA-256 hash of the Markdown source content used to detect changes.
- missing `cms_sync` metadata means the file is skipped by normal update mode and can be reported for adoption planning.

## CMS Source Metadata

A source-linked CMS page should keep source metadata in page settings. Page settings are enough for the documented workflow; a separate source mapping table can be considered later only if reporting, cross-locale mapping, audit, or large-scale operations require it.

Recommended page settings shape:

```json
{
  "source_sync": {
    "type": "markdown_documentation",
    "source_id": "webblocks-cms:docs/contact-forms-and-messages.md",
    "source_path": "docs/contact-forms-and-messages.md",
    "source_sha256": "sha256-value",
    "managed_slots": ["main"],
    "last_synced_at": "2026-06-24T00:00:00Z"
  }
}
```

The source path is descriptive. The stable identity is `source_id`, and the change detector is `source_sha256`.

## Page Matching

The matching order must be deterministic:

1. Look for a CMS page with matching `source_sync.source_id` or equivalent `cms_source_id` metadata.
2. If found, compare `source_sha256`.
3. If no source-id match exists, look for a page at `cms_path`.
4. If the path exists without matching source metadata, report an adoption or conflict review case.
5. If the path belongs to another `source_id`, report a conflict and stop for that file.
6. If no page exists at the path, plan `create_draft_page`.

Do not use content comparison as the primary match mechanism. Match by stable source identity first, then by path only for adoption or conflict review.

## Default Decisions

For changed Markdown docs, use these defaults:

- If the source hash is unchanged in CMS metadata, skip the file.
- If no matching CMS page exists, plan `create_draft_page`.
- If a matching draft page exists, plan `replace_existing_draft_page` for managed page-owned slots.
- If only a matching published page exists, do not directly replace it unless the workflow has a documented safe draft or staging path and the user explicitly approves that path.
- The default managed slot is `main`.
- Preserve header, footer, disabled slots, and Shared Slot assignments.
- Do not replace a Shared Slot-backed slot.
- Plan navigation separately and do not apply navigation changes by default.
- Publishing is never part of content apply by default.

The workflow should regenerate managed page-owned slots from the Markdown source instead of trying to preserve manual CMS edits inside those slots. Source-linked documentation pages are reproducible derivatives; Markdown remains authoritative.

## Markdown To Block Mapping

Build structured content using only handles discovered from the target install. Never guess block handles or nearby spellings.

Practical mapping rules:

- H1 maps to the page title and/or a `content_header` block when that handle is available.
- H2 and H3 map to `header` blocks with anchors where supported.
- Paragraphs map to `rich-text`.
- Simple unformatted short copy may use `plain_text` only when that is more appropriate than rich text.
- Lists map to a list block when the current content contract supports one; otherwise keep them in `rich-text`.
- Tables map to a `table` block where possible.
- Code fences map to a `code` block.
- Blockquotes map to `quote` or `alert`/callout-style blocks depending on meaning and discovered contracts.
- Normal Markdown links stay rich-text links.
- CTA-like links may become `button_link` only when they are intentionally action-oriented.
- Raw HTML should be avoided; `html` is a reviewed fallback only when structured blocks cannot represent the content.
- Images and media should not be downloaded or imported. Warn unless the target workflow explicitly supports existing-media references.

Prefer readable documentation structure over a single large `rich-text` block. A normal documentation page usually uses `content_header` or H1-derived title content, followed by headings, rich text, lists, tables, and code blocks inside the managed `main` slot.

## API Workflow

The workflow is API-first:

1. Start from `GET /webadmin/api`.
2. Use returned links for OpenAPI, the AI guide, content contract, block types, pages, navigation, and Shared Slots.
3. Confirm the token has the capabilities needed for the requested mode.
4. Read existing pages and source metadata through the API.
5. Build page plans using discovered handles only.
6. Run `POST /webadmin/api/content/validate` before apply.
7. Apply only after explicit approval or when the user's instruction explicitly authorizes safe draft apply.
8. Never publish unless the user explicitly requests publish and the token has `content.publish`.
9. Never use browser automation when the API is available.

API errors are workflow feedback. Treat `401`, `403`, and `422` JSON responses as stop or revise signals, follow discovery/documentation links, and report safe summarized status without printing secrets.

## Batch Behavior

When multiple changed docs are candidates:

- process each source doc as an independent planned page update
- validate all candidate page plans before applying any batch unless the operator explicitly chooses per-file apply
- let one file's conflict stop that file without hiding successful plans for other files
- report skipped, planned, validated, applied, failed, and conflict files separately
- do not make navigation changes just because multiple docs changed
- keep navigation planning as a separate explicit plan

Batch apply should be conservative. If the user requested safe draft apply, apply only the validated draft-safe items and leave conflicts or review cases unapplied.

## Stop Conditions

Stop before apply when:

- the API token is missing, invalid, or revoked
- API discovery fails
- OpenAPI, content contract, or block types cannot be read
- required block handles are unavailable
- `cms_path` conflicts with another `cms_source_id`
- the target page is published and no safe draft replacement path is available
- the plan would replace a Shared Slot-backed slot
- validation fails
- the user did not approve apply and the instruction was dry-run or plan-only

Also stop before publish unless the user explicitly requests publish, the plan has already been validated/applied safely, and the token has `content.publish`.

## Report Formats

### Dry-Run Report

```text
Docs -> CMS dry-run

Source path: docs/example.md
Source id: webblocks-cms:docs/example.md
Source hash: sha256:...
Target path: /docs/example
Target locale: en
Target layout: docs
Decision: create draft | replace draft | skip | conflict | needs review
Planned managed slots: main
Warnings: none | ...
Validation result: not run
Apply result: not performed
Preview URL: not available
Publish status: not performed
```

### Validation Report

```text
Docs -> CMS validation

Source path: docs/example.md
Source id: webblocks-cms:docs/example.md
Source hash: sha256:...
Target path: /docs/example
Target locale: en
Target layout: docs
Decision: replace draft
Planned managed slots: main
Warnings: ...
Validation result: passed | failed
Validation details: safe summary of API feedback
Apply result: not performed
Preview URL: not available
Publish status: not performed
```

### Apply Report

```text
Docs -> CMS apply

Source path: docs/example.md
Source id: webblocks-cms:docs/example.md
Source hash: sha256:...
Target path: /docs/example
Target locale: en
Target layout: docs
Decision: replace draft
Planned managed slots: main
Warnings: ...
Validation result: passed
Apply result: applied | skipped | failed
Preview URL: /webadmin/pages/{page}/preview
Publish status: not performed
```

For batches, group the same fields under `skipped`, `planned`, `validated`, `applied`, `failed`, `conflict`, and `needs review` sections.

## Minimal Prompt Examples

Plan only:

```text
Plan Docs -> CMS updates for the changed docs/ Markdown files. Do not validate or apply.
```

Validate only:

```text
Validate Docs -> CMS content plans for the changed docs/ Markdown files. Do not apply.
```

Validate and apply safe draft updates:

```text
Validate and apply safe draft updates for changed docs/ Markdown files; do not publish.
```

Full rescan planning:

```text
Plan Docs -> CMS updates for all cms_sync Markdown files under docs/. Full rescan only; do not apply.
```

Navigation planning only:

```text
Plan documentation navigation updates for changed docs/ Markdown files. Do not apply content or navigation.
```

## Safety And Editing Rules

Source-linked documentation pages should be marked as source-managed in CMS. A future edit screen can warn editors: "This page is synced from Markdown source. Edit the source file instead."

Manual CMS edits to source-linked documentation pages are not preserved by the next source-driven regeneration of managed slots. Header/footer and Shared Slot assignments are preserved because they are not page-owned managed Markdown content.

Documentation and operator reports must not include tokens, secrets, local absolute paths, raw logs, environment values, real target-site names, or real domains.
