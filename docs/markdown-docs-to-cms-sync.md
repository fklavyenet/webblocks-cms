# Markdown Docs To CMS Sync

This document records the architecture direction for publishing Markdown-sourced technical documentation into a target WebBlocks CMS documentation site through a future AI/operator workflow. It defines the source mapping model only. It does not add a runtime feature, endpoint, migration, executable automation, or connection to any live target.

## Problem

Technical documentation and published CMS documentation pages can drift over time when they are maintained as separate sources. Live CMS pages edited by hand can fall behind product code, release notes, package docs, and review history.

Technical product documentation should stay in the Markdown source files shipped with the product and reviewed through the normal Git workflow. The CMS publication layer should be treated as a generated documentation experience derived from those Markdown source files, not as the source of truth.

## Main Decision

Documentation source lives in Git; documentation experience lives in WebBlocks CMS.

- Markdown source files are authoritative for technical product documentation.
- Source-linked CMS pages are publication or draft derivatives produced from Markdown.
- Manual edits in CMS are not authoritative for source-linked documentation pages.
- Source-linked documentation pages should be reproducible from their Markdown source.
- The model must work for any target CMS install or target documentation site; it is not tied to a specific domain, installation, or project name.

## Content Types

Technical product documentation:

- Markdown is the primary source.
- A target CMS documentation site is the publication layer.
- Source-linked CMS pages are managed derivatives of the Markdown source files.

Marketing and landing pages:

- These may be native CMS content.
- They do not have to be derived from Markdown.

Generated or API reference content:

- These may be derived from live API discovery, OpenAPI metadata, or a content contract.
- They can use their own source identity model when they are not authored as Markdown.

## AI/Operator Workflow

This model is intended for a future AI/operator workflow rather than repository-side runtime automation. An operator can ask AI to read a selected Markdown docs folder and prepare source-linked draft documentation pages in a target CMS install.

The AI/operator workflow should:

- Read the Markdown source files and front matter.
- Start from the target CMS API discovery information.
- Read OpenAPI metadata, content contract information, block types, existing pages, navigation, and shared slots from the target CMS install.
- Avoid guessing block handles.
- Build a content plan using the block contract learned from the target CMS install.
- Validate the content plan before any apply step.
- Apply only through an explicitly approved workflow.
- Treat publish as a separate human or workflow approval, never as the default result.

The initial workflow should begin with a small selected-doc set. Converting an entire docs tree in one pass should wait until the selected-doc workflow is documented, validated, and reviewed.

## Markdown Front Matter

Markdown documentation files can opt in with source-linking metadata:

```yaml
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/contact-forms-and-messages
cms_title: Contact Forms and Messages
cms_layout: docs
cms_source_id: webblocks-cms:docs/contact-forms-and-messages.md
```

`cms_site` is an example target site handle, not a real domain or installation name. `cms_source_id` is the stable source identity. If the file moves, the source identity can remain unchanged so the target CMS page can still be matched safely.

## CMS Source Metadata

A source-linked CMS page should keep source metadata in page settings. For an initial MVP, page settings are enough; a separate source mapping table can be considered later if reporting, cross-locale mapping, audit, or large-scale operations require it.

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

The matching order should be deterministic:

1. Look for a CMS page with the same `cms_source_id`.
2. If found, update that page through the draft-safe workflow.
3. If not found, look for a page at `cms_path`.
4. If the path exists without source metadata, report an adopt-existing-page scenario for explicit review.
5. If the path does not exist, create a new draft page.
6. If the path belongs to another `source_id`, report a conflict and stop.

This avoids content comparison as the matching mechanism. The workflow matches by stable source identity and source hash.

## Update Strategy

The workflow should not compare page content line by line or block by block.

- Compute a SHA-256 hash for the Markdown source file.
- If the hash matches the target page source metadata, skip the page.
- If the hash differs, regenerate the managed `main` slot content for the linked CMS page from the Markdown source file.
- Preserve header, footer, and shared slot assignments.
- Treat navigation as a separate plan and explicit option.
- Avoid overwriting published pages directly.
- Use the safest initial model: create or update a draft page, produce a preview URL, and leave publish to a human or approved workflow.
- When an existing draft page is available, use a draft-safe replacement model for the page-owned managed slots.
- For published pages, staged draft updates or separate draft-copy behavior should be designed before broad rollout.

The managed slot list allows future layouts to define which page-owned slots are regenerated from source and which slots remain CMS-managed.

## Why Not Block-Level Diff

Block-level diffing would require persistent mapping between Markdown headings, paragraphs, lists, code blocks, and CMS block IDs. That makes moves, deletes, rewrites, manual CMS edits, and merge behavior much more complex.

For documentation publishing, page-level regeneration of managed slots is simpler, safer, and more sustainable. The Markdown source remains authoritative, while CMS pages remain reproducible publication artifacts.

## Future Scope

A future AI/operator workflow can:

- Read selected Markdown source files.
- Read front matter and source-link metadata.
- Convert Markdown into a CMS content plan.
- Use live API discovery and content-contract information.
- Avoid guessed block handles.
- Validate the plan.
- Apply after explicit approval.
- Produce preview URLs and an operator report.
- Later include docs navigation planning and publish workflow steps.

The first MVP should focus on a few selected documents rather than a full docs tree.

## Safety And Editing Rules

Source-linked documentation pages should be marked as source-managed in CMS. A future edit screen can warn editors: "This page is synced from Markdown source. Edit the source file instead."

Manual CMS edits to source-linked documentation pages are not preserved by the next source-driven regeneration. The Markdown source file is authoritative.

Documentation and operator reports must not include tokens, secrets, local absolute paths, raw logs, or environment values.
