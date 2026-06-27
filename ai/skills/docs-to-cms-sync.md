# Docs To CMS Sync Skill

Use this skill when the user asks to update, plan, validate, or apply CMS documentation pages from Markdown files under `docs/`.

Markdown files under `docs/` are the source of truth. CMS documentation pages are generated derivatives. This is an AI/operator workflow, not an automatic runtime sync engine.

Do not add a runtime watcher, queue job, command, endpoint, migration, or background sync.

## Candidate Detection

- Use an explicit file list if provided.
- Otherwise use changed Markdown files under `docs/`.
- Include added, modified, and renamed `.md` files.
- Exclude `docs/releases/` unless explicitly requested.
- Exclude internal, private, audit, and worklog docs.
- Skip files without `cms_sync` metadata unless in adoption or planning mode.

## Front Matter

Read:

- `cms_sync`
- `cms_site`
- `cms_locale`
- `cms_path`
- `cms_layout`
- `cms_source_id`

Match target pages by `source_sync.source_id` first. Use path matching second only for adoption or conflict review. Compare source SHA-256.

## Default Decisions

- Unchanged source hash: skip.
- No page: plan `create_draft_page`.
- Matching draft page: plan `replace_existing_draft_page`.
- Matching published page: plan the staged update workflow.
- Preserve header, footer, and Shared Slots.
- Managed slot default is `main`.
- No navigation edits by default.
- No publish by default.

## Markdown Mapping

- H1 -> page title and/or `content_header`.
- H2/H3 -> `header` with anchors where supported.
- Paragraphs, lists, and links -> `rich-text` unless a better supported block fits.
- Tables -> `table`.
- Code fences -> `code`.
- Blockquotes -> `quote` or alert/callout if the contract supports it and the meaning fits.
- Images/media -> warn; do not download or import.
- Safe HTML only as a reviewed fallback.

## API Workflow

1. Start from `GET /webadmin/api`.
2. Use content contract, block types, pages, navigation, and Shared Slot links.
3. Validate all plans before apply unless the user requests per-file apply.
4. Apply only when explicitly approved or explicitly requested as safe draft apply.
5. Never publish or promote without explicit approval and `content.publish`.

Batch reports should group skipped, planned, validated, applied, failed, conflict, and needs review items.

Reports must not include tokens, secrets, local absolute paths, raw logs, real target-site names, or real domains.

## Live Documentation Site Testing Boundary

For docs sync work, report validation/apply results and preview URLs. Do not run live documentation-site browser checks unless explicitly requested in the same prompt. Live visual checks are operator-owned by default.

## Final Report

Include:

- source path
- source id
- source hash
- target path
- target locale/layout
- decision
- managed slots
- validation/apply result
- preview URL if applied
- publish status
- warnings
