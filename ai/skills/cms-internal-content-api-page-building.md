# CMS Internal Content API Page Building Skill

Use this skill when creating, replacing, staging, or publishing CMS page content through `/webadmin/api`. This workflow is for trusted operator tools, not public delivery.

## Discovery First

Start from live API discovery:

1. `GET /webadmin/api`
2. Follow discovered links to OpenAPI, AI guide, content contract, examples, pages, navigation, and Shared Slots.
3. Use only discovered sites, locales, page layouts, block handles, icon slugs, capabilities, and contract fields.

Never guess block handles or field names. Do not scrape browser admin UI when API discovery is available. Do not fetch remote pages.

Use examples shaped like:

```text
WEBBLOCKS_CMS_API_URL=https://example.com/webadmin/api
```

Do not use the public site root as the API URL. Never print, log, or commit real API tokens. Token requests use Bearer auth and JSON headers.

## Normal Workflow

1. Perform read-only discovery.
2. Build a content plan using discovered contracts.
3. `POST /webadmin/api/content/validate`.
4. Revise until valid.
5. Apply only after explicit approval.
6. Report the preview URL.
7. Do not publish unless explicitly approved and the token has `content.publish`.

## Draft And Staged Safety

- Use `create_draft_page` for new content.
- Use `replace_existing_draft_page` only for draft pages with `expected_path` or `expected_updated_at`.
- Do not overwrite published pages directly.
- For published pages, use `create_staged_update_for_published_page`, then `replace_staged_page_update`, preview the staged draft, and run `promote_staged_page_update` only after explicit approval and only with `content.publish`.
- Before promoting a staged update, read `GET /webadmin/api/pages/{staged_page}` and follow `page._actions.promote`; do not call `POST /webadmin/api/pages/{staged_page}/publish` for staged updates.
- Preserve Shared Slot-backed slots.
- Do not replace, clear, or cascade Shared Slot content unless an explicit supported API operation is discovered and approved.
- Page publish and page-owned block publish are separate.
- Do not assume page publish makes draft blocks public.
- Use `include_page_owned_blocks: true` only after explicit approval.
- Use canonical `page.path` values such as `/contact` or `/docs/internal-content-api`; do not generate `/p/...`.

## Content Rules

- Do not import or download media unless a later explicit contract supports it.
- For contact pages, use native `contact_form` when discovered; do not use Trusted HTML, raw forms, or `mailto:` substitutes.
- For icons and badges, use only active catalog-backed icon slugs and allowlisted badge fields discovered from block contracts.

## Live Site Testing Boundary

For API-created drafts, staged pages, or promoted content, report edit and preview URLs. Do not run live browser or public-site visual tests unless explicitly requested in the same prompt. Live visual checks are operator-owned by default.

## Final Report

Include:

- API discovery/authenticated status without token value
- capabilities relevant to the requested operation
- source page or target path
- plan mode
- validation result
- apply result
- created, replaced, staged, or promoted page id
- edit or preview URL
- publish/promote status
- preserved Shared Slots and media
- warnings or API limitations
