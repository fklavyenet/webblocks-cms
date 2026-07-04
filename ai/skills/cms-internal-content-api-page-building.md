# CMS Internal Content API Page Building Skill

Use this skill when creating, replacing, staging, or publishing CMS page content through `/webadmin/api`. This workflow is for trusted operator tools, not public delivery.

## Discovery First

Start from live API discovery:

1. `GET /webadmin/api`
2. Follow discovered links to OpenAPI, AI guide, content contract, examples, pages, blocks, media, navigation, Shared Slots, layout slot sync, and site public theme endpoints.
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
4. Revise until valid and review the `renderability` summary.
5. Apply only after explicit approval.
6. Report the preview URL.
7. Do not publish unless explicitly approved and the token has `content.publish`.
8. For canonical site CSS changes, read `GET /webadmin/api/sites/{site}/assets/css`, inspect `asset.guidance`, and keep `site.css` token-first and mode-aware.

## Draft And Staged Safety

- Use `create_draft_page` for new content.
- Use `replace_existing_draft_page` only for draft pages with `expected_path` or `expected_updated_at`.
- Do not overwrite published pages directly.
- For published pages, first read `GET /webadmin/api/pages/{source_page}` and reuse any existing active staged draft exposed by the API.
- Use `create_staged_update_for_published_page` only when no active staged draft exists. Repeated create calls for the same source page return the existing staged draft with `data.reused_staged_update=true` instead of creating another page.
- Use `replace_staged_page_update` for later revisions on an existing staged draft, then preview the staged draft and run `promote_staged_page_update` only after explicit approval and only with `content.publish`.
- Before promoting a staged update, read `GET /webadmin/api/pages/{staged_page}` and follow `page._actions.promote`; do not call `POST /webadmin/api/pages/{staged_page}/publish` for staged updates.
- Preserve Shared Slot-backed slots.
- Do not replace, clear, or cascade Shared Slot content unless an explicit supported API operation is discovered and approved.
- Before assigning a Shared Slot to a page slot such as `header`, confirm the page has that Page Slot. If the selected Page Layout defines it but the page is missing it, call `POST /webadmin/api/pages/{page}/sync-layout-slots` first.
- Shared Slot blocks are draft by default. Publish them only after explicit approval with `POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks` when discovered and when the token has `content.publish`.
- Page publish and page-owned block publish are separate.
- Do not assume page publish makes draft blocks public.
- Use `include_page_owned_blocks: true` only after explicit approval.
- Use canonical `page.path` values such as `/contact` or `/docs/internal-content-api`; do not generate `/p/...`.
- Build nested block trees with `children` arrays only. Never send flat block `id`, `parent_id`, `block_id`, `slot_type_id`, or `block_type_id` fields in content plans.
- Put block copy directly under `translations` for the selected plan locale. Do not send locale-keyed block shapes such as `translations.en.title`.
- Do not create wrapper-only blocks such as `section`, `container`, `cluster`, `grid`, `card`, `card_body`, `card_footer`, or `sticky-navbar` without meaningful children.
- Treat nonzero `renderability.wrapper_blocks_without_children`, `renderability.text_blocks_without_visible_content`, or `renderability.button_blocks_without_label_or_url` as a failed plan even if the API returns other useful metadata.
- Treat `renderability.html_blocks > 0` as use of the Trusted HTML escape hatch and require explicit operator approval plus a report of the missing native block type.
- If a public page renders with the wrong site theme preset, update the site through `POST /webadmin/api/sites/{site}/public-theme` when discovered and authorized; do not try to solve site-level theme with content blocks.
- If a public page needs site CSS, use the canonical site asset endpoint and preserve WebBlocks UI Light/Dark/Auto behavior. Prefer public theme custom properties, inherited `wb-*` component styling, and semantic site custom properties over hard-coded light backgrounds, dark text, white cards, or one-off dark palettes.

## Content Rules

- Do not put remote media URLs directly inside content plans. If API discovery exposes `POST /webadmin/api/media/fetch` and the token has `media.upload`, use it only for an approved single public file URL, then assign the returned Media Library id in the content plan.
- Use `GET /webadmin/api/media` only for existing CMS Media Library discovery. Prefer tokens with `media.read`; transitional installs may still allow `content.read` for read-only media discovery.
- Use `POST /webadmin/api/media` only when discovered and authorized with `media.upload`; uploaded files become normal admin-visible Media Library records.
- Use `POST /webadmin/api/media/fetch` only when discovered and authorized with `media.upload`; fetched files become normal admin-visible Media Library records and must pass the CMS remote-fetch public-network, redirect, MIME, and size guards.
- Use `PATCH /webadmin/api/media/{media}` only when discovered and authorized with `media.write`. This write scope is metadata-only: `title`, `alt_text`, `caption`, and `description`.
- Use `POST /webadmin/api/media/{media}/replace`, `POST /webadmin/api/media/{media}/move`, or `DELETE /webadmin/api/media/{media}` only when discovered and authorized with the matching `media.replace`, `media.move`, or `media.delete` capability. Delete keeps the CMS usage guard and must not remove media still referenced by blocks, site branding, or page SEO.
- Do not remotely fetch Media Library files unless live API discovery supports `POST /webadmin/api/media/fetch` for that exact operation.
- In content plans, assign uploaded Media Library records to native media blocks with `media_id` or `asset_id` on `image`, `navbar-brand`, `sidebar-brand`, `file`, `download`, and `video`, or with `gallery_items` / `gallery_media_ids` on `gallery`. For card-like designs, put the media on a nested `image` block inside `card` / `card_body`.
- For site favicon and social image changes, upload or discover image media and use `PATCH /webadmin/api/sites/{site}/branding`; do not overwrite `/cms/brand/*` product/admin assets.
- For existing `navbar-brand` or `sidebar-brand` logo changes, use `PATCH /webadmin/api/blocks/{block}` with `media_id`, safe settings, and translations instead of Trusted HTML, invented file paths, unsupported `settings.logo_url`, or static markup.
- Existing block updates require `content.apply`; Shared Slot source blocks additionally require `shared-slots.write`.
- For contact pages, use native `contact_form` when discovered; do not use Trusted HTML, raw forms, or `mailto:` substitutes.
- For icons and badges, use only active catalog-backed icon slugs and allowlisted badge fields discovered from block contracts.
- For navbar links managed by CMS Navigation, use safe internal paths or `http`/`https` URLs. For same-page anchors, send a path plus fragment such as `/#platform`, not raw `#platform`.

## Live Site Testing Boundary

For API-created drafts, staged pages, or promoted content, report edit and preview URLs. Do not run live browser or public-site visual tests unless explicitly requested in the same prompt. Live visual checks are operator-owned by default.

## Final Report

Include:

- API discovery/authenticated status without token value
- capabilities relevant to the requested operation
- source page or target path
- plan mode
- validation result
- renderability summary
- apply result
- created, replaced, staged, or promoted page id
- edit or preview URL
- publish/promote status
- preserved Shared Slots and media
- warnings or API limitations
