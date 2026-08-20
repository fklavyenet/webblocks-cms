---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/internal-content-api
cms_title: Internal Content API
cms_layout: docs
cms_source_id: webblocks-cms:docs/internal-content-api.md
---

# Internal Content API

## Purpose

The Internal Content API is a secure CMS API for trusted AI and operator tools. It lets those tools inspect CMS content contracts, create draft-first content, replace specific page-owned slots on existing draft pages, run explicit publish operations through structured JSON, and request allowlisted admin render snapshots for visual QA without logging into, scraping, or automating the browser admin UI.

Phase 1 is implemented as a token-protected, JSON-only, non-public API for read-only content discovery plus draft page creation through validated content plans. Phase 2A adds safe foundations for navigation menus, Shared Slots, and explicit page slot Shared Slot assignment. Phase 2B adds controlled draft-only replacement for page-owned slot content on existing pages. Publish endpoints are explicit and require `content.publish`; content apply remains draft-first and does not publish. The API remains intentionally narrow: no remote fetch, no broad page delete through content apply, no replacement of Shared Slot-backed slots, and no Shared Slot cascade publishing.

## Product Positioning

The Internal Content API is:

- an internal/operator CMS API
- token-protected
- not public
- not a headless CMS delivery API
- not a replacement for admin permissions
- not an import/export replacement
- not an AI vendor integration

CMS core should own this API because it operates on core content concepts: sites, pages, layouts, slots, blocks, translations, navigation, and shared slots. AI or operator tools may call the API, but CMS core should not embed OpenAI, LLM, crawler, or vendor-specific integration logic.

## Route Prefix

The canonical prefix is:

```text
/webadmin/api
```

This keeps the API inside the CMS admin boundary while using a concise, familiar API segment. Resource-style endpoints should live directly under this prefix, such as `/webadmin/api/pages` and `/webadmin/api/blocks`.

Before planning content, read the packaged [CMS Inventory](inventory.md) with `GET /webadmin/api/inventory`. It is the per-block design and authoring contract and is the first document an AI should read. The `html` block is human-only: it stays readable through the API, but every API mutation touching it is rejected with `422` and the stable code `block_type_not_api_writable`, and no capability overrides that. For an `application` block, first discover a ready definition and its settings schema through `GET /webadmin/api/applications`; API writes may reference registered applications but cannot introduce executable code or file paths.

API discovery starts at:

```text
GET /webadmin/api
```

Unauthenticated callers receive only public-safe bootstrap JSON. Authenticated callers receive safe product version metadata plus links to OpenAPI, the AI guide, content contract, examples, content validate/apply, pages, blocks, media, navigation, and Shared Slots. External AI/operator tools should start from this live discovery response instead of reading the CMS repository or local package docs.

Plan-based content operations use:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

Route choices to avoid:

- `/webadmin/internal-api`, because it is unnecessarily verbose
- `/webadmin/api/content-plans/...`, because `content-plans` is too technical and narrow for the URL contract
- placing every resource under `/webadmin/api/content/...`, because resource APIs should stay clear and direct
- `/admin`, because CMS must not assume the host product's `/admin` path is CMS-owned
- `/cms`, because `/cms` remains reserved for static CMS assets only

## Authentication

The API uses Bearer token authentication:

```http
Authorization: Bearer <token>
```

CMS API tokens are created by a CMS super admin from `System -> API Tokens`. The CMS stores only a SHA-256 hash plus a safe preview in the `cms_api_tokens` database table. The plain token is shown once immediately after creation and is never shown again.

The one-time token panel provides copy controls for both the full token and the generated local `.env` example. Copy feedback is announced accessibly, and the browser implementation includes a fallback for environments without the modern Clipboard API.

Tokens created with the older default page-building capability set are treated as eligible for the read-only `admin.render` capability at runtime, so trusted operator tools can use allowlisted admin visual QA snapshots after updating without rotating the token. Narrow custom tokens that do not include the former default set remain restricted.

The Tokens list includes a history action for each token. It opens a WebBlocks UI modal with the latest 10 API activity records for that token. Activity records are intentionally small: request time, status, method, path without query string, route name, required capability when a capability guard was evaluated, IP, and a short user-agent value. The CMS does not store request bodies, query strings, response bodies, bearer token values, token hashes, or token previews in activity rows. Older activity rows are pruned automatically so each token keeps only the latest 10 records.

Super admins can revoke a token to immediately disable API access while keeping the audit row visible, or delete a token to permanently remove the token record from the list. Deleting an active token also immediately disables API access because the authenticator can no longer find a matching stored hash.

Local AI and operator tools should store the generated token in a trusted operator secret store.

Use the Internal Content API base URL in local operator configuration:

```dotenv
WEBBLOCKS_CMS_API_URL=https://example.com/webadmin/api
WEBBLOCKS_CMS_API_TOKEN=...
```

The CMS runtime does not require `WEBBLOCKS_CMS_INTERNAL_API_TOKEN`.

Authentication rules:

- missing, wrong, or revoked tokens return JSON `401`
- revoked tokens stop working immediately
- tokens must never be printed in logs, diagnostics, support reports, tests, or documentation examples
- token comparison must use a constant-time comparison
- successful API requests update the token's `last_used_at` and `last_used_ip`
- successful API requests also store a truncated user-agent for operator audit context
- normal resource responses and all errors are JSON; allowlisted visual QA routes may return direct HTML only when the caller explicitly requests an HTML format

Example request:

```http
GET /webadmin/api/sites
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## Capabilities

Super admins choose token capabilities when creating a token from `System -> API Tokens`, and can later edit a token's name and capabilities without exposing or rotating the token secret. Discovery exposes the saved capabilities without returning the token value, token hash, or token preview. Standard page-building tokens default to these capabilities:

- `content.read`
- `content.validate`
- `content.apply`
- `navigation.write`
- `shared-slots.write`
- `media.read`
- `site-settings.write`

Advanced capabilities are separate options and are not selected by default:

- `navigation.delete`
- `shared-slots.delete`
- `domains.write`
- `domains.delete`
- `site-assets.read`
- `site-assets.write`
- `engagement.read`
- `engagement.moderate`
- `plugins.read`
- `plugins.install`
- `plugins.manage`
- `plugins.setup`
- `plugins.uninstall`
- `commerce.read`
- `commerce.products.write`
- `commerce.orders.read`
- `media.write`
- `media.upload`
- `media.replace`
- `media.move`
- `media.delete`
- `content.publish`
- `pages.delete`
- `content.blocks.delete`
- `backups.create`
- `page-assets.write`

Write endpoints check the relevant capability server-side. Missing capabilities return JSON `403` with `api_discovery_url`, `openapi_url`, `documentation_url`, and `example_url` guidance. Normal page-building tokens should not include destructive capabilities such as navigation delete, Shared Slot delete, content publish, plugin install/manage/setup/uninstall, or page delete. Reading Comments/Rating feedback requires explicit `engagement.read`; changing comment status requires explicit `engagement.moderate`. Plugin lifecycle automation requires explicit plugin capabilities, and WebBlocks Commerce product/order API access requires explicit commerce capabilities.

## Rate Limit

Every `/webadmin/api` route is throttled together, keyed by client IP and bearer token. The default budget is 120 requests per minute; exceeding it returns `429` with a `Retry-After` header. An install can change it with `CMS_INTERNAL_API_RATE_LIMIT_PER_MINUTE`, and the live value is published as `x-rate-limit` on `GET /webadmin/api/openapi.json` — clients should read it there rather than assume the default.

Hosting, proxy, or CDN layers in front of the site may enforce a lower limit than the CMS does, so a bulk client such as a full-site translation pass should pace itself and back off on `429` instead of retrying immediately.

## API Model

The API has two complementary modes.

### Resource API

Resource endpoints mirror individual admin-equivalent operations:

- list and read pages
- list and read blocks
- list sites, locales, layouts, and block types
- later create or update draft page resources directly
- later list or ensure page slots
- later add, update, move, and delete blocks through resource endpoints
- later add child blocks through resource endpoints
- later manage navigation and shared slots

Phase 1 resource endpoints:

```text
GET /webadmin/api/sites
GET /webadmin/api/locales
GET /webadmin/api/locale-options
POST /webadmin/api/locales
PATCH /webadmin/api/locales/{locale}
GET /webadmin/api/page-layouts
GET /webadmin/api/block-types
GET /webadmin/api/content-contract
GET /webadmin/api/pages
GET /webadmin/api/pages/{page}
POST /webadmin/api/pages/{page}/publish
POST /webadmin/api/pages/{page}/publish-page-owned-blocks
POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot
PUT /webadmin/api/pages/{page}/slots/{slot}/source
GET /webadmin/api/pages/{page}/assets
POST /webadmin/api/pages/{page}/assets/{type}
PATCH /webadmin/api/pages/{page}/assets/{pageAsset}
DELETE /webadmin/api/pages/{page}/assets/{pageAsset}
GET /webadmin/api/media
POST /webadmin/api/media/fetch
GET /webadmin/api/blocks
GET /webadmin/api/blocks/{block}
PATCH /webadmin/api/blocks/{block}
POST /webadmin/api/pages/{page}/slots/{slot}/blocks
PATCH /webadmin/api/pages/{page}/slots/{slot}/blocks/reorder
DELETE /webadmin/api/pages/{page}/slots/{slot}/blocks/{block}
GET /webadmin/api/navigation-menus
GET /webadmin/api/navigation-menus/{navigationMenu}
POST /webadmin/api/navigation-menus
POST /webadmin/api/navigation-menus/{navigationMenu}/items
PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/{item}
PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/reorder
DELETE /webadmin/api/navigation-menus/{navigationMenu}/items/{item}
GET /webadmin/api/shared-slots
GET /webadmin/api/shared-slots/{sharedSlot}
POST /webadmin/api/shared-slots
POST /webadmin/api/shared-slots/{sharedSlot}/blocks
PATCH /webadmin/api/shared-slots/{sharedSlot}/blocks/reorder
DELETE /webadmin/api/shared-slots/{sharedSlot}/blocks/{block}
DELETE /webadmin/api/shared-slots/{sharedSlot}/blocks
GET /webadmin/api/plugins
POST /webadmin/api/plugins/install
POST /webadmin/api/plugins/{plugin}/enable
POST /webadmin/api/plugins/{plugin}/setup
POST /webadmin/api/plugins/{plugin}/disable
DELETE /webadmin/api/plugins/{plugin}
GET /webadmin/api/commerce/products
POST /webadmin/api/commerce/products
PATCH /webadmin/api/commerce/products/{product}
GET /webadmin/api/commerce/orders
GET /webadmin/api/commerce/orders/{order}
GET /webadmin/api/sites/{site}/assets/css
PUT /webadmin/api/sites/{site}/assets/css
```

`GET /webadmin/api/locale-options` returns the curated standard language locale picker catalog as a flat `locale_options` list. API tools should choose a returned `locale_option` when creating a normal language locale instead of inventing `code` and `name` strings.

`POST /webadmin/api/locales` and `PATCH /webadmin/api/locales/{locale}` require `site-settings.write` and accept safe locale fields only: `locale_option`, `code`, `name`, `is_default`, and `is_enabled`. Sending `locale_option` copies the canonical code and English name from the standard catalog; direct `code` and `name` remain available for controlled country-variant or custom/operator cases. Setting `is_default=true` forces the locale enabled and demotes any previous default through the normal CMS locale invariant. Migration tools should patch an existing locale id when correcting a language-only setup mistake, such as changing a fresh single-locale German install from `en` to `de`, so existing page, block, and site locale relations keep their ids.

### Content Validate / Apply API

Content validate/apply endpoints handle complete multi-step content plans:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

`validate` checks a complete content plan and writes nothing. `apply` validates the plan again and then creates the requested draft page, navigation items, Shared Slots, Shared Slot block trees, and page slot Shared Slot assignments transactionally. It can also replace named page-owned slots on an existing draft page when the plan uses `mode: replace_existing_draft_page` and includes an optimistic safety guard. This is useful for AI-generated pages, templates, starter pages, shared headers/footers, and migration helpers where the CMS should avoid half-created content.

The request body may still contain a `plan` field or another structured content plan payload. The URL should remain `/content/validate` and `/content/apply`.

Both modes are needed:

- the Resource API exposes the existing CMS content model and contracts to internal tools
- the Content Validate / Apply API avoids partial writes during larger page builds

#### Apply Restore Point

`POST /content/apply` accepts an optional top-level `create_restore_point: true` flag. When set, the CMS takes a full system backup (database plus uploads) as a restore point **before** applying the plan, so an operator can roll back from `System -> Backups` if an AI-generated apply goes wrong.

```json
{
  "create_restore_point": true,
  "plan": { "mode": "create_draft_page", "page": { "...": "..." } }
}
```

Rules:

- it requires the dedicated `backups.create` capability, which is not part of the default page-building set; without it the request returns JSON `403`
- the plan is validated first, so an invalid plan returns `422` without creating a wasted backup
- if the backup cannot be created the apply is aborted and returns JSON `409`; content is never applied without the requested safety net
- on success the apply response includes a `restore_point` object with the backup `id`, `type` (`content_apply`), `label`, `status`, and `created_at`
- the backup is full and synchronous, so it adds noticeable latency; use it for large or destructive applies, not high-frequency automation

The API intentionally exposes only restore-point creation. Restoring, downloading, and deleting backups stay in the operator admin UI and are not part of the token API.

### Plugin And Commerce API

Trusted operator tools can manage manually installed plugins through explicit lifecycle endpoints:

```text
GET /webadmin/api/plugins
POST /webadmin/api/plugins/install
POST /webadmin/api/plugins/{plugin}/enable
POST /webadmin/api/plugins/{plugin}/setup
POST /webadmin/api/plugins/{plugin}/disable
DELETE /webadmin/api/plugins/{plugin}
GET /webadmin/api/plugins/catalog
GET /webadmin/api/plugins/catalog/{plugin}
POST /webadmin/api/plugins/catalog/{plugin}/install
```

These endpoints require `plugins.read`, `plugins.install`, `plugins.manage`, `plugins.setup`, or `plugins.uninstall` respectively. They work with bearer authentication and do not require an authenticated browser session. The manual install endpoint accepts a validated plugin ZIP artifact. Catalog list/detail requests require `plugins.read`; catalog installation requires `plugins.install` and reuses the CMS catalog client, compatibility checks, artifact size/ZIP validation, and SHA-256 verification. Both installation paths keep the plugin disabled by default. Setup runs the plugin-declared migrations. Uninstall is limited to disabled manually uploaded plugins and preserves plugin-owned tables.

When WebBlocks Commerce is enabled and setup-ready, trusted tools can create/list products and read orders:

```text
GET /webadmin/api/commerce/products
POST /webadmin/api/commerce/products
PATCH /webadmin/api/commerce/products/{product}
GET /webadmin/api/commerce/orders
GET /webadmin/api/commerce/orders/{order}
```

Commerce product reads require `commerce.read`, product writes require `commerce.products.write`, and order reads require `commerce.orders.read`. If the plugin is disabled or setup migrations have not run, Commerce endpoints return JSON `409` with setup guidance instead of a raw database error.

The plugin-owned `webblocks-commerce-buy-button` block is discoverable from `GET /webadmin/api/block-types` and `GET /webadmin/api/content-contract` only while the plugin is enabled. Its settings require `settings.commerce_product_id` from `GET /webadmin/api/commerce/products`; validate/apply rejects missing, unknown, or inactive product ids. Checkout remains on the public Commerce/PayPal flow. The CMS API creates products and page blocks, but it does not collect card data or start a card-entry flow.

### Existing Block Native Field Updates

Use content validate/apply to build a full page in one transaction. For incremental draft edits, use the [Page Block Topology API](#page-block-topology-api) to add, reorder, or delete individual page-owned blocks. Use the existing-block update endpoint only when the block already exists and the change maps to a safe native field:

```text
GET /webadmin/api/media?kind=image
PATCH /webadmin/api/blocks/{block}
```

The endpoint requires `content.apply`. If the block is part of a Shared Slot source tree, the token must also have `shared-slots.write`.

Supported fields are intentionally narrow:

- `media_id` or `asset_id` for `navbar-brand` and `sidebar-brand` logo media
- `media_id` or `asset_id` for `hero`, `section`, `card`, `cta`, and `content_header` background media
- `settings.url`
- `settings.target`
- `settings.aria_label`
- `settings.background_position`
- `settings.background_overlay`
- `settings.show_search` for `header-actions`
- `settings.show_mode_toggle` for `header-actions`
- `settings.show_accent_toggle` for `header-actions`
- `settings.show_language_switcher` for `header-actions`
- text translations such as `title` and `subtitle`
- image block translations through `translations.image.alt_text`, `translations.image.caption`, or the shorthand `translations.alt_text` and `translations.caption`
- contact form translations through `translations.contact_form.submit_label` and `translations.contact_form.success_message`, or the same names as shorthand, alongside `title` and `content`
- `url`
- `variant`

Translated fields are written into the translation family the block type belongs to, for the locale in the request (the site default locale when `locale` is omitted):

| Family | Block types | Fields |
| --- | --- | --- |
| `text` | header, hero, rich-text, columns, cta, and most content blocks | `title`, `eyebrow`, `subtitle`, `content`, `meta` |
| `button` | button | `title` |
| `image` | image | `caption`, `alt_text` (also accepted as `title`, `subtitle`) |
| `contact_form` | contact_form | `title`, `content`, `submit_label`, `success_message` |

A field the family does not own is refused with `422` and code `unsupported_block_translation_fields`, and a block type with no translation family refuses `translations` outright with `unsupported_block_translations` — neither is written to the text table, where nothing would read it. A locale the block's site has not enabled is refused with `invalid_block_translation_locale`. Stored rows are returned under `block.translations`, keyed by family.

A contact form's `submit_label` and `success_message` are translations, not settings: they are the only way to localize the form's button and confirmation text, and they cannot be set through `settings`.

The endpoint rejects topology and database implementation fields such as `parent_id`, `slot_type_id`, `block_type_id`, `type`, `sort_order`, and `children`. It also rejects raw HTML, remote media URLs, and source URLs. Existing media can be selected from the CMS Media Library, but this API does not import or download new remote media.

Example:

```json
{
  "locale": "en",
  "media_id": 12,
  "settings": {
    "url": "/",
    "target": "_self",
    "aria_label": "WebBlocks CMS home"
  },
  "translations": {
    "title": "WebBlocks CMS",
    "subtitle": "Composable content operations"
  }
}
```

For an existing Image block, update the locale-owned image translation row with:

```json
{
  "locale": "en",
  "translations": {
    "image": {
      "alt_text": "Dashboard-style ecosystem panel",
      "caption": "Homepage ecosystem panel"
    }
  }
}
```

### Page Block Topology API

For incremental draft page editing, page-owned block topology can be changed one operation at a time without sending a full content plan:

```text
POST /webadmin/api/pages/{page}/slots/{slot}/blocks
PATCH /webadmin/api/pages/{page}/slots/{slot}/blocks/reorder
DELETE /webadmin/api/pages/{page}/slots/{slot}/blocks/{block}
```

`{slot}` is a page-owned slot slug. These endpoints are intentionally draft-safe and narrow:

- they operate only on draft pages; a non-draft page returns JSON `409` with code `page_not_draft`. Use content apply staged updates for published pages.
- they operate only on page-owned slots; a Shared Slot-backed slot returns JSON `422`. Use the Shared Slot endpoints for Shared Slot content.
- an unknown page slot returns JSON `404`.

`POST .../blocks` adds one block using the same normalized block payload as content apply (`type`, `settings`, `translations`, and nested `children`). New blocks are created with `draft` status and appended to the end of the slot. Pass `parent_id` to append under an existing container block in the same slot; the parent must accept children. Creating and reordering require `content.apply`.

`PATCH .../blocks/reorder` renumbers one sibling group. The `blocks` array must contain every sibling id for a single parent group exactly once, in the desired order; the parent is derived from the submitted blocks. Submitting a partial group returns JSON `422`.

`DELETE .../blocks/{block}` removes the block and its whole subtree and returns `deleted_blocks_count`. Deletion requires the dedicated `content.blocks.delete` capability, which is not part of the default page-building capability set. Shared Slot source blocks cannot be deleted through this endpoint.

Every write captures a page revision so draft edits stay reversible.

### Hero And CTA Actions

`hero` and `cta` are ordinary containers whose action buttons are plain `button_link` children — declare them under `children` like any other nested block. Two optional block fields remain as a shorthand for the common one-or-two-button case:

```json
{
  "type": "hero",
  "translations": { "title": "Welcome" },
  "primary_cta": { "label": "Get started", "url": "/signup" },
  "secondary_cta": { "label": "Docs", "url": "https://example.com/docs" }
}
```

Both fields accept an object with a required `label` and a required `url`, or `null` to leave the action unset. URLs must be a safe internal path or an `http(s)` URL. The shorthand writes the first two `button_link` children, so the result is indistinguishable from buttons an editor adds by hand. Declaring the children explicitly is the canonical form and the only way to author more than two buttons. Sending `primary_cta` or `secondary_cta` on any other block type is rejected.

The fields work everywhere a block payload is accepted: content validate/apply plans, `POST /pages/{page}/slots/{slot}/blocks`, and `POST /shared-slots/{sharedSlot}/blocks`.

### Page Render API

`GET /webadmin/api/pages/{page}/render` requires `content.read` and returns the page rendered exactly as the browser admin preview renders it: draft blocks included, `X-Robots-Tag: noindex, nofollow`, and never through the public route, so nothing about it publishes anything.

Add `?format=html` for the markup itself instead of the JSON envelope, and `?locale=de` to render a specific translation; the default is the site default locale. A locale the page has no translation for returns `422` with `page_translation_missing` and the locales it does have. Shared Slot source pages are editing scaffolding and return `422` with `page_not_renderable`, matching the browser admin's own 404 on them.

Rendering was already reachable before this endpoint existed: `GET /webadmin/pages/{page}/preview` accepts a Bearer token with `content.read` as well as an admin session, and still does. What this adds is locale selection — the browser preview always renders the default translation — plus a JSON envelope and a place in discovery and the OpenAPI document, so a tool finds it the same way it finds everything else instead of having to know about a route outside `/webadmin/api`.

Either way, render before reporting a page finished. Content apply says what was stored, which is not the same as what renders: a wrapper block with no children, a slot assigned to the wrong Shared Slot, or a layout without the slots the plan assumed all apply cleanly and still produce a page nobody wants.

### Page Translation API

Page translations own localized page identity and the page-level SEO and Open Graph overrides. Content apply writes one translation row for one locale when it creates a page; these endpoints are how that row is read back and changed afterwards.

`GET /webadmin/api/pages/{page}/translations` lists every translation on a page with `name`, `slug`, `path`, `seo_title`, `seo_description`, `seo_keywords`, `list_excerpt`, `og_title`, `og_description`, and `og_image_media_id`. `list_excerpt` is the per-locale sentence a Page List block shows under this page's title on a listing card; it is capped at 300 characters and falls back to the SEO description when empty. The same fields now appear on each translation in `GET /webadmin/api/pages/{page}`.

`POST /webadmin/api/pages/{page}/translations/{locale}` requires `content.apply` and adds a translation for a locale that is already enabled for the page site. The `{locale}` segment accepts a locale code or a numeric ID. Only `name` is required: omit `slug` and `path` and both are derived from the name, exactly as the browser admin derives them. Creating a translation for a locale the page already has returns `422` with the code `page_translation_exists`.

`PATCH /webadmin/api/pages/{page}/translations/{translation}` requires `content.apply` and writes only the fields the request carries, so a tool can set `seo_description` without resending page identity. Send `null` to clear an optional field.

Slug and path always move together. Sending `path` re-derives the slug from its last segment; sending `slug` alone moves the page to `/{slug}`. Paths are canonicalized, checked against reserved CMS and host routes, and must stay unique for the site and locale. Because `Page` title and slug read through to the default translation, renaming that translation renames the page itself.

Fields outside localized identity and SEO are rejected with `422` and the code `unsupported_page_translation_fields`, which names them in `blocked_fields`. Page status, layout, and block content have their own endpoints.

### Page Assets API

Pages can load their own `/site/...` CSS and JS files in addition to the site-wide assets:

```text
GET    /webadmin/api/pages/{page}/assets
POST   /webadmin/api/pages/{page}/assets/{type}
PATCH  /webadmin/api/pages/{page}/assets/{pageAsset}
DELETE /webadmin/api/pages/{page}/assets/{pageAsset}
```

`{type}` is `css` or `js`. Listing is available to any valid token; the write endpoints require the dedicated opt-in `page-assets.write` capability, which is not part of the default page-building set.

The `path` contract is deliberately narrow and is the security boundary for this API:

- it must be a local path starting with `/site/` and ending in `.css` or `.js` matching the asset type
- external URLs (`http://`, `https://`), protocol-relative (`//`), `javascript:`, and `data:` paths are rejected
- directory traversal, backslashes, query strings, and fragments are rejected

This endpoint only *attaches* an existing file to a page; it never writes file contents. Write the file itself with the site assets API (`PUT /webadmin/api/sites/{site}/assets/{type}`, which requires `site-assets.write`) or from the admin UI.

Supported fields are `path` (required on create), `sort_order`, `is_enabled`, and — for JS assets only — `is_defer`, `is_async`, and `is_module`. `load_position` is derived from the type (`head` for CSS, `body_end` for JS), matching the admin editor. The `css`/`js` type is immutable on update; delete and re-add to change it.

Unlike the page block topology endpoints, page assets are not draft-only: they can be changed on any page, matching the admin Page Assets tab. Every write captures a page revision.

### Shared Slot Block Topology API

Shared Slot block trees have their own topology endpoints because they are shared across every page the slot is assigned to:

```text
PATCH  /webadmin/api/shared-slots/{sharedSlot}/blocks/reorder
DELETE /webadmin/api/shared-slots/{sharedSlot}/blocks/{block}
DELETE /webadmin/api/shared-slots/{sharedSlot}/blocks
```

Adding blocks stays at `POST /shared-slots/{sharedSlot}/blocks`, and editing a single block's safe content fields stays at `PATCH /blocks/{block}` (which already accepts Shared Slot source blocks with `shared-slots.write`). The new endpoints add the missing topology operations:

- `PATCH .../blocks/reorder` renumbers one sibling group; the `blocks` array must contain every sibling id for a single parent group exactly once, in the desired order. It requires `shared-slots.write`.
- `DELETE .../blocks/{block}` removes one block and its subtree and returns `deleted_blocks_count`.
- `DELETE .../blocks` clears every block in the slot so a tool can clear-and-replace a Shared Slot; it returns `deleted_blocks_count`.
- Both deletes require `shared-slots.write` plus the dedicated `content.blocks.delete` capability.

Unlike page block edits, these are not draft-only: Shared Slots have no draft-page concept. Reordering or deleting an already-published Shared Slot block therefore affects every assigned page immediately, which is why deletion is gated behind the destructive `content.blocks.delete` capability. Every write rebuilds the slot's page assignments and captures a Shared Slot revision so changes stay reversible.

### Site Settings API

Alongside `branding`, `head`, `timezone`, and `public-theme`, three further site fields are writable. All three require `site-settings.write`.

`PATCH /webadmin/api/sites/{site}/seo` writes `seo_title`, `seo_description`, and `seo_keywords`: the site-wide fallback metadata a public page inherits when its own page translation carries no override. Partial, and `null` clears a field. Page-level overrides belong on the page translation; see the Page Translation API above.

`PATCH /webadmin/api/sites/{site}/contact-recipient` writes `contact_recipient_email`, the address contact form submissions are mailed to. Send an empty value to fall back to the system recipient. A contact form assembled through the API does nothing useful until this is set.

`PUT /webadmin/api/sites/{site}/locales` replaces the site's locale set from `locale_ids`. The default locale is always kept, exactly as the admin form keeps it, and locales must be globally enabled first through `POST`/`PATCH /webadmin/api/locales`. A page translation cannot be saved for a locale the site has not enabled, so this is the endpoint that makes a new locale usable on a site.

This endpoint is stricter than the admin form in one respect: it refuses to detach a locale that still has page translations on the site, and the `422` names the locale and the count. The form lets an operator do that knowingly; an unattended tool would silently orphan the content.

### Site Domain API

Domain records decide which hostname resolves to which site, so the browser admin keeps them behind system-level access. The API endpoints are:

- `PATCH /webadmin/api/sites/{site}/seo`
- `PATCH /webadmin/api/sites/{site}/contact-recipient`
- `PUT /webadmin/api/sites/{site}/locales`
- `GET /webadmin/api/sites/{site}/domains` requires `content.read`
- `POST /webadmin/api/sites/{site}/domains` requires `domains.write`
- `PUT /webadmin/api/sites/{site}/domains/{domain}` requires `domains.write`
- `POST /webadmin/api/sites/{site}/domains/{domain}/primary` requires `domains.write`
- `DELETE /webadmin/api/sites/{site}/domains/{domain}` requires the destructive `domains.delete`
- `GET /webadmin/api/domains/{domain}/status` requires `content.read`

These endpoints previously lived only under an `/admin-api` prefix and checked nothing beyond token validity, so any token at all could add or remove a domain. They now sit under `/webadmin/api` with the rest of the API and every route carries a capability. The `/admin-api` prefix keeps working for existing provisioning tools, with the same capability requirements and one extra route, `GET /admin-api/sites`; new integrations should use `/webadmin/api`, whose paths are also covered by the CSRF exemption that the legacy prefix never had.

### Shared Slot Metadata API

`PATCH /webadmin/api/shared-slots/{sharedSlot}` requires `shared-slots.write` and changes the Shared Slot itself rather than its blocks: `label`, `handle`, `slot`, `layout`, and `is_active`. Only the fields present are written. Handles stay unique per site, the slot name must resolve to a published slot type, and the Shared Slot source page and its assignments are rebuilt afterwards exactly as the browser admin rebuilds them. A metadata or status change captures a Shared Slot revision.

Moving a Shared Slot to another site is not supported here and is rejected with `422` and the code `unsupported_shared_slot_fields`, which also covers any other field outside that set. Cross-site moves stay in the browser admin with the other site transfer operations.

`DELETE /webadmin/api/shared-slots/{sharedSlot}` requires the destructive `shared-slots.delete` capability and applies the same guard as the browser admin: a Shared Slot still referenced by any page slot is never deleted. The `422` response carries the code `shared_slot_in_use` and a `usage` array naming the referencing page slots, so a tool can detach them first with `PUT /webadmin/api/pages/{page}/slots/{slot}/source`. If the Shared Slot reference columns have not been migrated yet, the endpoint returns `409` with `shared_slots_not_ready` instead of guessing.

### Media Library API

The Media Library API is intentionally split from broad content permissions so AI/operator tools can be granted media discovery or metadata cleanup without receiving unrelated write powers.

Capabilities:

- `media.read`: list and inspect existing CMS Media Library records.
- `media.upload`: upload files into the Media Library.
- `media.write`: update safe metadata on existing media records.
- `media.replace`: replace an existing media file while preserving its media id.
- `media.move`: move media between folders or clear its folder.
- `media.delete`: delete unused media with the same usage guard as the admin panel.

Phase 1 scope:

```text
GET /webadmin/api/media
POST /webadmin/api/media
GET /webadmin/api/media/{media}
PATCH /webadmin/api/media/{media}
POST /webadmin/api/media/{media}/replace
POST /webadmin/api/media/{media}/move
DELETE /webadmin/api/media/{media}
```

`GET /webadmin/api/media` returns existing media records with safe fields such as id, kind, title, filename, original name, mime type, visibility, public URL when available, alt text, dimensions, previewability, and compact metadata label. It requires `media.read`. During the transition from older API tokens, `content.read` may also be accepted for read-only media discovery so existing page-building integrations keep working.

`GET /webadmin/api/media/folders` lists Media Library folders with their media counts, and `POST /webadmin/api/media/folders` requires `media.write` and creates one from `name`, with optional `slug` and `parent_id`. A name that already exists under the same parent is refused with `422` and the code `media_folder_exists`, and the response carries the existing folder so a retrying tool reuses it instead of creating duplicates. File uploads into a folder with `folder_id`; move existing media with `POST /webadmin/api/media/{media}/move`.

`POST /webadmin/api/media` requires `media.upload` and accepts `multipart/form-data` uploads into the normal CMS Media Library. The uploaded file becomes a regular `media` row and is visible in the browser admin Media screen and media pickers. The upload validation mirrors the browser admin Media Library allowed file types: common images, videos, PDFs, Office documents, text/CSV/RTF, and ZIP files.

Supported metadata fields are `folder_id`, `title`, `alt_text`, `caption`, and `description`. The endpoint returns the created media object with its id and public URL when available.

`PATCH /webadmin/api/media/{media}` requires `media.write` and updates only safe descriptive metadata:

- `title`
- `alt_text`
- `caption`
- `description`

`POST /webadmin/api/media/{media}/replace` requires `media.replace` and replaces the stored binary file while preserving the media id and existing references. The replacement file must resolve to the same media kind as the existing record, such as image-to-image or document-to-document. The response includes current usage details so tools can see whether the replacement affects blocks, site branding, or page SEO.

`POST /webadmin/api/media/{media}/move` requires `media.move` and accepts `folder_id`, including `null` to clear the folder assignment.

`DELETE /webadmin/api/media/{media}` requires `media.delete` and uses the same usage guard as the browser admin. Media referenced by blocks, galleries, attachments, site branding, or page SEO is not deleted; the API returns `422` with usage details instead.

This API explicitly does not support:

- changing disk, path, filename, mime type, kind, visibility, size, dimensions, uploader, or usage relationships directly
- fetching or importing remote media URLs

This boundary lets AI/operator tools receive only the media powers they need. For example, a page-building token can read and upload media without being able to delete or replace existing files, while a trusted maintenance token can be granted `media.replace` or `media.delete` explicitly.

### Existing Draft Page Slot Replacement

Existing draft page replacement stays inside the validate/apply contract:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

Use `mode: replace_existing_draft_page` to replace one or more page-owned slots on an existing draft page. The operation requires `content.validate` for validate and `content.apply` for apply. It does not require `pages.delete`, because it is not a general page delete operation.

Page Translation `path` is the canonical public URL. New plans should use paths such as `/contact`, `/features`, `/games/fruit-train`, or `/docs/internal-content-api`; `/p/...` is legacy compatibility only. Slash-bearing paths are normalized segment by segment, so `/docs/internal-content-api/` becomes `/docs/internal-content-api` and is not collapsed into `docsinternal-content-api`. CMS derives the short page slug from the final path segment, so `/games/fruit-train` stores slug `fruit-train` and path `/games/fruit-train`. Reserved route areas such as `/webadmin`, `/webadmin/api`, `/cms`, `/search`, `/search.json`, `/contact-messages`, `/install`, and host auth routes cannot be created as public page paths.

Example:

```json
{
  "plan": {
    "mode": "replace_existing_draft_page",
    "site": "default",
    "locale": "en",
    "page": {
      "id": 9,
      "expected_path": "/contact",
      "status": "draft"
    },
    "replace_slots": {
      "main": [
        {
          "type": "plain_text",
          "translations": {
            "content": "Updated draft contact content."
          }
        }
      ]
    }
  }
}
```

Rules:

- target page must be in `draft` status
- `expected_path` or `expected_updated_at` is required
- `expected_path` uses the canonical public Page Translation path, not a `/p/...` legacy alias
- the target page must belong to the requested site and locale must be enabled for that site
- each slot must exist on the page and use page-owned blocks
- Shared Slot-backed slots are rejected instead of being cleared
- only blocks in the named `replace_slots` are removed
- old blocks are removed and new blocks are written in one transaction
- page revisions are captured before and after apply
- no publish, media fetch/import, broad delete, or Shared Slot assignment clearing happens

### Published Page Staged Updates

Published pages cannot be replaced directly. For source-linked docs pages or other live pages that must remain public while content changes are prepared, use the staged update modes:

```text
create_staged_update_for_published_page
replace_staged_page_update
promote_staged_page_update
```

`create_staged_update_for_published_page` creates a draft staging page from the published source page only when that source page does not already have an active draft staged update. Repeating the create call for the same source page returns the existing active staged draft with `data.reused_staged_update=true` instead of creating another technical page. The source page remains published at its existing public path. The staged page stores `settings.staged_update` metadata with the source page id, source path, source updated timestamp, lifecycle state, and managed slots. It uses an internal draft path such as `/staged-updates/page-123/update-456`, so it does not collide with the source canonical path and is not publicly routed. The normal admin preview URL `/webadmin/pages/{page}/preview` can render the staged page for authenticated admin sessions or CMS API Bearer tokens with `content.read`.

`replace_staged_page_update` reuses the draft replacement rules against the staged draft page. Use it for every subsequent revision after a staged draft exists. It replaces only page-owned slots and rejects Shared Slot-backed slots.

`promote_staged_page_update` applies staged page-owned slot content back onto the published source page in a transaction. It preserves the source page path, status, layout, Shared Slot assignments, and page settings unless an allowlisted `source_sync` update is supplied. Promoted page-owned blocks are written as `published` so the public page reflects the promoted content immediately. Shared Slot content is never cascaded. Promote requires the normal `content.apply` route capability and the advanced `content.publish` capability.

If a normalized apply plan passes validation but fails during transactional writes, the API returns normal validation JSON with `ok=false` and an error at `plan.apply`. The detailed exception is recorded in application logs; API responses stay public-safe and do not include stack traces, database internals, tokens, or local paths.

Example staged workflow:

```json
{
  "plan": {
    "mode": "create_staged_update_for_published_page",
    "site": "default",
    "locale": "en",
    "page": {
      "id": 123
    },
    "expected_source_path": "/docs",
    "managed_slots": ["main"]
  }
}
```

Then replace the staged content:

```json
{
  "plan": {
    "mode": "replace_staged_page_update",
    "staged_page_id": 456,
    "expected_source_page_id": 123,
    "expected_source_path": "/docs",
    "replace_slots": {
      "main": [
        {
          "type": "plain_text",
          "translations": {
            "content": "Replacement staged content."
          }
        }
      ]
    }
  }
}
```

Promote after preview and explicit approval:

```json
{
  "plan": {
    "mode": "promote_staged_page_update",
    "staged_page_id": 456,
    "expected_source_page_id": 123,
    "expected_source_path": "/docs",
    "promote_slots": ["main"]
  }
}
```

No new schema table is required for this workflow; staged lifecycle metadata uses the existing Page `settings` JSON column. Existing installs already updated enough to run current Internal Content API source-sync workflows have the required storage.

### Source Sync Metadata

Content plans may persist a limited, secret-safe `source_sync` object for AI/operator docs sync workflows. Arbitrary page settings are rejected. The accepted shape is:

```json
{
  "page": {
    "settings": {
      "source_sync": {
        "type": "markdown_documentation",
        "source_id": "webblocks-cms:docs/internal-content-api.md",
        "source_path": "docs/internal-content-api.md",
        "source_sha256": "64-character-lowercase-sha256",
        "managed_slots": ["main"],
        "last_synced_at": "2026-06-25T00:00:00Z"
      }
    }
  }
}
```

Apply persists this metadata to page settings, and page list/detail API responses expose the same allowlisted `source_sync` fields for future matching. Do not include tokens, environment values, local/server absolute paths, or other secrets.

### Explicit Publish Endpoints

Publishing is separate from content apply and requires a token with `content.publish`.

```text
POST /webadmin/api/pages/{page}/publish
POST /webadmin/api/pages/{page}/publish-page-owned-blocks
POST /webadmin/api/pages/{page}/archive
```

`POST /webadmin/api/pages/{page}/publish` publishes the page record. Its default payload is page-only:

```json
{
  "include_page_owned_blocks": false
}
```

Rules:

- omitted `include_page_owned_blocks` behaves as `false`
- `include_page_owned_blocks: false` publishes only the page record and leaves draft or in-review blocks unchanged
- `include_page_owned_blocks: true` publishes draft and in-review blocks owned by the page's non-shared page slots, including nested child blocks
- already published blocks remain unchanged
- Shared Slot-backed slots are excluded and reported in the response
- unsupported Shared Slot cascade fields such as `publish_shared_slots`, `include_shared_slot_blocks`, or `shared_slot_cascade` return JSON `422`
- the response includes page id/status/path metadata, whether page-owned blocks were included, the count published, excluded Shared Slot summaries, and the page revision id

`POST /webadmin/api/pages/{page}/publish-page-owned-blocks` publishes only unpublished page-owned blocks and does not change the page workflow status. It uses the same `content.publish` capability and the same Shared Slot exclusion rule.

`POST /webadmin/api/pages/{page}/archive` takes the page off the public site: archived pages return 404 to visitors and their page-linked menu items stop rendering in public menus. It uses the same `content.publish` capability and mirrors the admin workflow rules: allowed from `in_review` and `published`, a draft page returns JSON `422`, re-archiving an archived page is a no-op success, and staged update pages are rejected (promote or delete those instead). The response includes page metadata, `from_status`, and the captured page revision id. Publishing the page again reverses the archive.

AI/operator tools must not assume page publish makes all block content public. Use `include_page_owned_blocks: true` only when the user explicitly approved publishing all unpublished page-owned blocks for that page. Shared Slot content must be reviewed and published separately.

### Content Contract Endpoint

`GET /webadmin/api/content-contract` is a read-only discovery endpoint for trusted AI/operator tools. It returns the API prefix, validate/apply URLs, admin preview URL template, safety flags, discovery URLs, recommended page-building patterns, and sanitized block contract metadata.

The endpoint is generic CMS product behavior. It must not return install-specific secrets, token values, raw Blade contents, absolute filesystem paths, private server paths, or site-specific instructions. Block contract rows may include handle/slug, label, category, status, container and child support, translatable fields, shared settings fields, and public renderer root behavior.

AI tools should call this endpoint or `GET /webadmin/api/block-types` before building a plan and must use only handles that are present in the current install.

Block contracts may expose optional public icon and badge fields, including `settings.icon_slug`, `settings.icon_tone`, `settings.badge_tone`, and translated `eyebrow`/`badge_label` copy. Tools must treat icon slugs as catalog-backed selections rather than free text; public rendering skips unknown or inactive icons and only emits safe `wb-icon wb-icon-{slug}` classes for active WebBlocks UI catalog rows. `settings.icon_tone` is a shared public visual tone setting, not locale-owned copy, and accepts only `default`, `soft`, `brand`, `accent`, `highlight`, `bold`, and `quiet`; `info`, `success`, `warning`, and `danger` remain semantic status tones for badges, alerts, validation, and feedback. Unknown icon tones are rejected during content plan validation or ignored by public rendering as a safe fallback.

`GET /webadmin/api/icon-catalog?context=content` returns the active safe icon slugs that API clients may assign to public content blocks. Use `context=navigation` for navigation item icon choices. API tools should read this endpoint before sending `settings.icon_slug` or navigation `icon` values and must not guess icon names from the WebBlocks UI manifest.

The `contact_form` contract includes additional safe form metadata: settings schema, translated fields, the public `POST /contact-messages` submit endpoint, required CSRF browser behavior, server validation rules, the CMS-owned hidden generated anti-spam check field, generic check-field success behavior, spam classification/quarantine notes, storage-before-notification behavior, recipient fallback order, safe notification failure recording, and `/webadmin/contact-messages` review behavior. The check field is generated by the renderer, is not part of normal visitor input, and should not be created manually by API or AI/operator tools. Contact-page tools should use that native block rather than Trusted HTML, raw form markup, or `mailto:` forms. The old `website` field is no longer the public Contact Form contract.

The human-readable AI Page Building Guide ships in package-native installs at `vendor/fklavyenet/webblocks-cms/docs/ai-page-building-guide.md`.

## Phase 1 Scope

### Discovery Endpoints

- `GET /webadmin/api`
- `GET /webadmin/api/openapi.json`
- `GET /webadmin/api/ai-guide`
- `GET /webadmin/api/examples`
- `GET /webadmin/api/examples/contact-page`
- `GET /webadmin/api/examples/landing-page`
- `GET /webadmin/api/sites`
- `GET /webadmin/api/locales`
- `GET /webadmin/api/locale-options`
- `POST /webadmin/api/locales`
- `PATCH /webadmin/api/locales/{locale}`
- `GET /webadmin/api/page-layouts`
- `GET /webadmin/api/block-types`
- `GET /webadmin/api/icon-catalog`
- `GET /webadmin/api/content-contract`

### Page Endpoints

- `GET /webadmin/api/pages`
- `GET /webadmin/api/pages/{page}`
- `GET /webadmin/api/pages/{page}/render`
- `GET /webadmin/api/pages/{page}/translations`
- `POST /webadmin/api/pages/{page}/translations/{locale}`
- `PATCH /webadmin/api/pages/{page}/translations/{translation}`
- `POST /webadmin/api/pages/{page}/sync-layout-slots`
- `PATCH /webadmin/api/pages/{page}/layout`
- `POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot`
- `PUT /webadmin/api/pages/{page}/slots/{slot}/source`

### Block Endpoints

- `GET /webadmin/api/blocks`
- `GET /webadmin/api/blocks/{block}`

### Navigation Endpoints

- `GET /webadmin/api/navigation-menus`
- `GET /webadmin/api/navigation-menus/{navigationMenu}`
- `POST /webadmin/api/navigation-menus`
- `POST /webadmin/api/navigation-menus/{navigationMenu}/items`
- `PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/{item}`
- `PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/reorder`
- `DELETE /webadmin/api/navigation-menus/{navigationMenu}/items/{item}`

Navigation menus use the existing CMS `navigation_items.menu_key` model. Phase 2A supports the shipped CMS menu handles such as `primary`, `footer`, `mobile`, `legal`, and `docs`; it does not add a separate menu table. Creating a navigation menu is treated as creating a safe site-scoped menu group with optional initial items. It refuses to overwrite a site/menu that already has items.

Navigation item create, update, visibility changes, and reorder require `navigation.write`. Delete requires the explicit destructive `navigation.delete` capability. Item updates support `label`/`title`, `url`, `link_type`, `page_id`, `target`, `visibility`, `sort_order`/`position`, `parent_id`, and `icon` where those fields are valid for the selected link type. Updates are partial: fields absent from the payload keep their stored values without re-validation, so legacy rows (a pre-tree-editor sort order of 0, a child nested under a page-type parent) stay updatable; the rules apply to the values a request actually sends.

Item updates also accept an optional `locale` key (a locale code enabled for the item's site), mirroring block translation writes. Without `locale`, or with the site default locale, `label`/`title` writes the canonical default-locale title. With a non-default `locale`, the `label`/`title` value is stored as a locale-keyed translation row for that item and the canonical title stays untouched; other fields in the same request (visibility, position, and so on) remain shared and apply to the item itself. Public rendering picks the navigation title for the requested locale and falls back to the canonical default-locale title when no translation row exists. Item read payloads expose the stored per-locale titles as a `title_translations` map keyed by locale code. Reorder payloads must include every item in the selected site/menu exactly once. Delete rejects parent items that still have child items, so tools must move or delete children first instead of relying on cascades.

Navigation item URLs may be internal paths such as `/`, `/about`, `/contact`, and `/#platform`, or safe `http`/`https` URLs. Use a path plus fragment for same-page anchors; raw fragment-only values such as `#platform` are not navigation URLs. The API rejects `javascript:`, `data:`, protocol-relative URLs, traversal, malformed URLs, unsupported targets, and empty labels. Navigation endpoints do not create pages, publish pages, crawl sites, fetch remote URLs, or cascade-delete child items.

### Shared Slot Endpoints

- `GET /webadmin/api/shared-slots`
- `GET /webadmin/api/shared-slots/{sharedSlot}`
- `PATCH /webadmin/api/shared-slots/{sharedSlot}`
- `DELETE /webadmin/api/shared-slots/{sharedSlot}`
- `POST /webadmin/api/shared-slots`
- `POST /webadmin/api/shared-slots/{sharedSlot}/blocks`
- `POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks`

Shared Slot creation is site-scoped and refuses duplicate handles for the same site. Shared Slot blocks reuse the same block payload writer used by page-owned blocks, so locale-owned copy stays in translation rows and shared settings remain on the block record/settings path. Shared Slot block append requests may include `parent_id` or `parent_block_id` to add a block under an existing block in the same Shared Slot source tree when that parent accepts the requested child type. Shared Slot blocks are created as draft content and require explicit `POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks` with `shared-slots.write` plus `content.publish` before they render publicly. Media import and media assignment remain outside this phase.

### Page Slot Assignment

```text
POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot
PUT  /webadmin/api/pages/{page}/slots/{slot}/source
```

The `shared-slot` endpoint assigns an existing compatible same-site active Shared Slot to an existing page slot. It does not publish the page. It refuses cross-site, inactive, and incompatible Shared Slots. It also refuses to switch a slot that still has page-owned blocks, because Phase 2A does not delete or replace those blocks automatically.

`PUT .../source` writes the slot's content source and is the way back out: `source_type` accepts `page`, `shared_slot`, or `disabled`. It requires `content.apply`, and `shared_slot` additionally requires `shared-slots.write` and the same compatibility rules as the assign endpoint, which it delegates to. Detaching clears `shared_slot_id` and leaves page-owned blocks untouched, so `page` renders them again and `disabled` keeps the slot wrapper with nothing inside. Before this endpoint existed the API could create a Shared Slot reference it had no way to remove — the only exit was the session-authenticated admin screen.

If the page layout contains a slot such as `header` but the page record was created before that Page Slot existed, call `POST /webadmin/api/pages/{page}/sync-layout-slots` first. Slot sync is idempotent and only creates missing Page Slots from the selected Page Layout; it never deletes existing slots, blocks, disabled states, Shared Slot assignments, translations, or revisions. For a new Shared Slot header, publish the Shared Slot blocks explicitly before expecting public output.

### Page Layout Assignment

```text
PATCH /webadmin/api/pages/{page}/layout
```

Writes an existing page's Page Layout (`public_shell`). The admin edit form could always change this; the API could previously only set it when creating a page. `content.apply` covers it. `layout` (or `handle`) must be one of `GET /webadmin/api/page-layouts`' active handles — the legacy `dashboard` alias still normalizes to `docs`. Matches `PageController::update()`'s own contract: it does not mutate Page Slots on a plain layout change, so this endpoint doesn't either. Call `sync-layout-slots` separately if the new layout defines slots the page does not have yet.

### Site Presentation Endpoint

```text
POST /webadmin/api/sites/{site}/public-theme
PATCH /webadmin/api/sites/{site}/branding
PATCH /webadmin/api/sites/{site}/timezone
GET /webadmin/api/sites/{site}/assets/{css|js}
PUT /webadmin/api/sites/{site}/assets/{css|js}
```

This endpoint updates only the safe site public theme preset used by public rendering. Send `public_theme_preset` or `theme` with one of the supported `Site::PUBLIC_THEME_PRESETS` values such as `canvas`, `atlas`, `pulse`, `prism`, `graphite`, or `horizon`. Use this when API discovery shows a site rendering with the wrong `data-wb-public-theme` preset; do not try to override the preset with content blocks.

`PATCH /webadmin/api/sites/{site}/branding` requires `site-settings.write` and updates safe public site branding fields that are also visible in `Sites -> Edit Site -> Branding`:

- `display_name`
- `tagline`
- `favicon_media_id`
- `social_image_media_id`
- Media replacement preserves the Media id and editorial references, clears obsolete image transforms, and derives future variants from the replacement binary.

The favicon and social image fields must reference image records from the CMS Media Library, and `null` clears the selected media. Public site favicon changes should use this endpoint so the result remains admin-editable. Do not overwrite `/cms/brand/*`; those files are CMS product/admin shell assets, not site-level public branding.

`PATCH /webadmin/api/sites/{site}/timezone` requires `site-settings.write` and sets a site's own timezone — a standard IANA identifier such as `Europe/Berlin`. Anything that resolves local wall-clock time for that site, such as a plugin's booking availability windows, is interpreted against this value. Send an empty `timezone` to clear it back to the install-wide system timezone from `Admin -> System Settings`.

`GET /webadmin/api/sites/{site}/assets/{css|js}` requires `site-assets.read` and reads the canonical physical site-level override file. It returns `relative_path`, `public_path`, `exists`, `contents`, `checksum`, `size`, `updated_at`, `readiness`, `guidance`, and CSS `analysis` without exposing the server absolute path. The `readiness` object reports whether the site directory, asset directory, and file are writable enough for CMS to create or update the asset. The `guidance` object tells AI/operator tools to keep CSS token-first, mode-aware, and native-block-first. For CSS, `asset.analysis.mode_awareness` reports `status`, warning messages, detected anti-pattern keys, signals such as literal color count, and recommended `--wb-public-*` tokens. `PUT /webadmin/api/sites/{site}/assets/{css|js}` requires `site-assets.write` and accepts:

- `contents`
- `expected_checksum`

The write endpoint uses the same physical-file store as `Sites -> Edit Site -> Assets`. It writes only `public/site/{site_handle}/css/site.css` or `public/site/{site_handle}/js/site.js`, creates missing directories on first save, rejects stale `expected_checksum` values, and stores a pre-overwrite revision snapshot before replacing an existing changed file. There is no database fallback route and no arbitrary `/site/...` path writer. If hosting permissions prevent directory creation or file writes, the endpoint returns JSON `422` with `errors.0.path = asset.write` and the current readiness metadata instead of an HTML server error.

For CSS writes, use native block structure/settings, Media Library background fields, public theme tokens, and inherited WebBlocks UI component styles before adding custom selectors. If custom colors are unavoidable, define semantic custom properties with light and dark values tied to active mode selectors or public theme tokens so public pages remain coherent in Light/Dark/Auto mode. Avoid page-wide hard-coded light backgrounds, dark text, white cards, or one-off dark-mode palettes when public theme tokens can express the design. `PUT` does not reject mode-awareness warnings because some brand systems need intentional custom colors, but tools should treat `asset.analysis.mode_awareness.status = warning` as work to review, report, or fix before considering a migration or new site setup complete.

Comments are stripped before analysis: a stylesheet that documents itself by quoting the rule it repairs does not report the quoted color as one of its own, and a dark-mode scope named only in a comment does not count as present.

The `anti_patterns` checks are about page-wide paint, so each one requires its token to be the last compound of the selector and to end where the name ends: `body { background: #fff }` is reported, `body .promo { background: #fff }` and `.wb-public-body { ... }` are not, and `.wb-card-title` is not read as `.wb-card`. A rule that paints one element is left to the literal-color signals rather than reported as a page-wide anti-pattern.

`asset.analysis.mode_awareness` is intentionally a heuristic contract, not a CSS linter. It reports `pass` or `warning`, `warnings`, detected `anti_patterns`, simple `signals` such as literal color declarations, `uses_public_theme_tokens`, and `has_dark_mode_scope`, plus the recommended `--wb-public-*` token roles. Tools should use this signal to catch likely mixed-mode regressions early and still rely on visual QA for final design parity.

### Embedded Application Assets API

Application code should not be merged into a site's global `site.css` or
`site.js`. A registered Embedded Application can own physical CSS, JavaScript,
and a managed `index.html` entry
files under its site-scoped public directory:

```text
GET    /webadmin/api/sites/{site}/applications/{application}/assets
GET    /webadmin/api/sites/{site}/applications/{application}/assets/{css|js}/{filename}
PUT    /webadmin/api/sites/{site}/applications/{application}/assets/{css|js}/{filename}
DELETE /webadmin/api/sites/{site}/applications/{application}/assets/{css|js}/{filename}
```

The deterministic public path is
`/site/{site_handle}/applications/{application_handle}/{type}/{filename}`.
`GET`, `PUT`, and `DELETE` require `applications.read`, `applications.write`,
and `applications.delete` respectively. Filenames are basenames only and must
end in the selected `.css` or `.js` extension; traversal, nested paths, other
file types, and remote URLs are rejected.

`PUT` accepts `contents` and `expected_checksum`. Send `null` only when creating
a missing file; read an existing file first and send its checksum when replacing
it. Replacements and deletions preserve revision copies. Delete is refused while
the Embedded Application definition still references the asset's public path.
After writing an asset, use its returned `public_path` in `css_assets` or
`js_assets`; the Application Block then loads it only where the application is
placed.

### Content Validate / Apply Endpoints

- `POST /webadmin/api/content/validate`
- `POST /webadmin/api/content/apply`

Content plans may assign already-uploaded CMS Media Library records to native media-backed blocks. Use `media_id` or `asset_id` on `image`, `navbar-brand`, `sidebar-brand`, `file`, `download`, and `video` blocks with media of the matching kind. Use `media_id` or `asset_id` on `hero`, `section`, `card`, `cta`, and `content_header` when the media is the block background image, with optional `settings.background_position` and `settings.background_overlay`. Use `gallery_items` or `gallery_media_ids` on `gallery` blocks with image media records; the API preserves the submitted Gallery item order when writing `block_media.position`. For card content images, create the normal nested structure, such as `card` -> `card_body` -> `image`, and put `media_id` on the child `image` block. Content plans still reject `remote_url` and `source_url`; upload files through `POST /webadmin/api/media` or import one approved public file URL through `POST /webadmin/api/media/fetch` first, then assign the returned `media.id`.

### Phase 1 Safety

- draft-only
- no publish through content apply
- no overwrite of existing published content
- no broad overwrite of existing pages or blocks outside `mode: replace_existing_draft_page`
- no remote fetch
- no media download or import
- no site creation yet
- no destructive page deletion through content apply
- no destructive block deletion outside transaction-scoped draft slot replacement
- no resource update, move, or delete endpoints yet
- no browser session, form, or CSRF requirement for Bearer-token JSON writes
- public unauthenticated access is limited to the minimal `GET /webadmin/api` bootstrap response

## JSON Error Shape

API errors are JSON-only. They must not redirect to login, render CSRF pages, or expose stack traces. Common fields:

```json
{
  "ok": false,
  "code": "invalid_internal_api_token",
  "message": "Invalid internal API token.",
  "api_discovery_url": "/webadmin/api",
  "openapi_url": "/webadmin/api/openapi.json",
  "documentation_url": "/webadmin/api/ai-guide",
  "example_url": "/webadmin/api/examples/contact-page",
  "errors": []
}
```

Expected statuses:

- `401` for missing, invalid, or revoked tokens
- `403` for missing capabilities
- `422` for validation errors

## Resource API Examples

### Render System Updates Admin Snapshot

```text
GET /webadmin/api/admin-render/system-updates
GET /webadmin/api/admin-render/system-updates?format=html
```

Requires `admin.render` and a token created by a user who can access `System`. The JSON response includes the rendered admin HTML for the allowlisted `System Updates` screen; `?format=html` returns direct `text/html` so operator tools can load the snapshot into a local browser and capture screenshots for visual comparison. The endpoint is read-only and must not click, install, publish, or mutate update state.

### List Pages

```text
GET /webadmin/api/pages
```

### Read Page Details

```text
GET /webadmin/api/pages/{page}
```

### Read Engagement Comments

```text
GET /webadmin/api/engagement/comments?status=pending&per_page=25
```

Requires `engagement.read`. Supported filters are `status`, `site_id`, `page_id`, `block_id`, `search`, and `per_page`. Responses include comment body, status, spam score/reasons, source page/block references, and timestamps, but do not expose visitor hashes, IP hashes, or user-agent values.

### Moderate Engagement Comment

```text
PATCH /webadmin/api/engagement/comments/{comment}
Content-Type: application/json

{"status":"approved"}
```

Requires `engagement.moderate`. Supported statuses are `pending`, `approved`, `rejected`, `spam`, and `hidden`.

### Read Engagement Ratings

```text
GET /webadmin/api/engagement/ratings?block_id=123
```

Requires `engagement.read`. Supported filters are `status`, `site_id`, `page_id`, `block_id`, and `per_page`. Responses include rating rows plus total, average, and value-count summaries, but do not expose visitor hashes, IP hashes, or user-agent values.

### List Blocks

```text
GET /webadmin/api/blocks
```

### Read Block Details

```text
GET /webadmin/api/blocks/{block}
```

## Content Validate / Apply Example

The same payload can be submitted to either endpoint:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

Example English marketing homepage draft:

```json
{
  "plan": {
    "site": "example-site",
    "locale": "en",
    "layout": "default",
    "page": {
      "title": "Acme Studio",
      "path": "/",
      "status": "draft"
    },
    "slots": {
      "main": [
        {
          "type": "section",
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "content_header",
                  "translations": {
                    "title": "Plan, build, and publish with confidence",
                    "subtitle": "Structured content for modern teams",
                    "content": "Create a draft homepage from a validated content plan."
                  }
                },
                {
                  "type": "cluster",
                  "children": [
                    {
                      "type": "button_link",
                      "translations": {
                        "title": "Start planning"
                      },
                      "settings": {
                        "url": "/contact",
                        "variant": "primary"
                      }
                    }
                  ]
                }
              ]
            }
          ]
        },
        {
          "type": "section",
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "grid",
                  "settings": {
                    "columns": 3
                  },
                  "children": [
                    {
                      "type": "card",
                      "children": [
                        {
                          "type": "card_body",
                          "children": [
                            {
                              "type": "plain_text",
                              "translations": {
                                "content": "Validate the whole draft before anything is written."
                              }
                            }
                          ]
                        }
                      ]
                    }
                  ]
                }
              ]
            }
          ]
        },
        {
          "type": "section",
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "cta",
                  "translations": {
                    "title": "Ready to shape the next page?",
                    "content": "Use structured plans for repeatable content creation."
                  }
                },
                {
                  "type": "cluster",
                  "children": [
                    {
                      "type": "button_link",
                      "translations": {
                        "title": "Contact us"
                      },
                      "settings": {
                        "url": "/contact",
                        "variant": "primary"
                      }
                    }
                  ]
                }
              ]
            }
          ]
        }
      ]
    }
  }
}
```

## Validation Rules

- plan fields the API does not understand are rejected with `422` and the stable code `unsupported_plan_fields`, and the error path names each one; the accepted key set is scoped to the plan `mode`, so a key that is meaningful in one mode is still rejected in another
- site handle or ID must resolve
- locale must exist and be enabled for the target site
- layout must exist
- path conflict blocks page creation
- block type must be published and usable
- child support must follow block contracts where available
- content plan block trees must use nested `children`; flat `id`, `parent_id`, `block_id`, `slot_type_id`, and `block_type_id` relationship fields are rejected because the API owns database IDs and parent assignment
- block `translations` must contain direct field keys for the selected plan locale, such as `title`, `subtitle`, and `content`; locale-keyed shapes such as `translations.en.title` are rejected instead of being silently ignored
- wrapper blocks such as `section`, `container`, `stack`, `split`, `cluster`, `grid`, `card`, `card_body`, `card_footer`, `sticky-navbar`, and `sidebar-navigation` must include child blocks so they cannot validate as empty public chrome; `split` requires exactly two direct children, and either side may be a nested `stack`
- user-facing text belongs in translation rows
- shared settings remain shared
- unknown unsafe settings are rejected
- harmless unknown settings may warn or be ignored consistently
- apply validates again before writing
- apply is transactional
- Content apply still rejects publish, site creation, media import, remote fetch, unsupported overwrite, unsupported replace, and delete operations
- Shared Slot creation remains create-only unless a later phase adds explicit draft-safe mutation contracts
- navigation item resource endpoints support explicit update, visibility, reorder, and delete operations outside content apply with capability guards

## Response Shape

Responses should be predictable JSON:

```json
{
  "ok": true,
  "writes": [],
  "data": {
    "page": {
      "id": 123,
      "title": "Product Overview",
      "status": "draft",
      "edit_url": "/webadmin/pages/123/edit"
    }
  },
  "normalized_plan": {},
  "renderability": {
    "root_blocks": 3,
    "total_blocks": 12,
    "html_blocks": 0,
    "wrapper_blocks_without_children": 0,
    "text_blocks_without_visible_content": 0,
    "button_blocks_without_label_or_url": 0
  },
  "warnings": [],
  "errors": []
}
```

Validation errors should include a path and message:

```json
{
  "ok": false,
  "writes": [],
  "data": null,
  "normalized_plan": {},
  "renderability": {
    "root_blocks": 2,
    "total_blocks": 3,
    "html_blocks": 0,
    "wrapper_blocks_without_children": 1,
    "text_blocks_without_visible_content": 0,
    "button_blocks_without_label_or_url": 0
  },
  "warnings": [
    {
      "path": "plan.slots.main.1.settings.theme",
      "message": "Unknown harmless setting ignored."
    }
  ],
  "errors": [
    {
      "path": "plan.page.path",
      "message": "A page already exists at this path for the selected site and locale."
    }
  ]
}
```

Include `edit_url` where useful for created or updated CMS resources.

`renderability` is a plan-sanity summary for AI/operator tools. A successful structured page plan should normally have `wrapper_blocks_without_children`, `text_blocks_without_visible_content`, and `button_blocks_without_label_or_url` at `0`. `html_blocks` above `0` means the plan used the Trusted HTML escape hatch; tools should do that only with explicit operator approval and only when first-class block types cannot represent the content.

## Phase 2A Plan Sections

Content plans may include `navigation_menus`, `shared_slots`, and `page_slot_shared_slots` alongside the existing page/slot plan. `validate` writes nothing. `apply` writes all valid sections in one transaction and rolls the full plan back when any later section fails.

```json
{
  "plan": {
    "site": "default",
    "locale": "en",
    "layout": "default",
    "page": {
      "title": "Homepage Draft",
      "path": "/",
      "status": "draft"
    },
    "slots": {
      "main": []
    },
    "navigation_menus": [
      {
        "handle": "primary",
        "label": "Primary Navigation",
        "items": [
          {
            "label": "Home",
            "url": "/",
            "target": "_self",
            "sort_order": 10
          },
          {
            "label": "About",
            "link_type": "page",
            "page_id": 9
          },
          {
            "label": "Docs",
            "link_type": "group",
            "children": [
              { "label": "Intro", "url": "/docs/intro" },
              { "label": "Guide", "url": "/docs/guide" }
            ]
          }
        ]
      }
    ],
    "shared_slots": [
      {
        "handle": "site-header",
        "label": "Site Header",
        "slot": "header",
        "blocks": []
      }
    ],
    "page_slot_shared_slots": [
      {
        "page": "created",
        "slot": "header",
        "shared_slot": "site-header"
      }
    ]
  }
}
```

Navigation items carry `link_type`: `custom_url` (the default, requires a safe internal path or `http(s)` URL), `page` (requires `page_id` for a page on the same site; the label falls back to the page name), or `group` (a label that opens its children and carries no URL). Only a `group` accepts `children`, which are created with their `parent_id` already linked, so a dropdown is one plan rather than a POST plus a PATCH per item. Content plans nest one level; deeper trees use `POST /navigation-menus/{menu}/items`. An unsupported `link_type` is refused by name instead of being coerced to `custom_url`.

`page_slot_shared_slots[].page` may refer to the page created by the same plan by using `created`, or to an existing page ID. `shared_slot` may refer to a Shared Slot created earlier in the same plan or an existing same-site Shared Slot handle.

## Future Phases

### Phase 2B

Delivered:

- Shared Slot block topology endpoints: reorder a sibling group (`PATCH /shared-slots/{sharedSlot}/blocks/reorder`, requires `shared-slots.write`), delete a single block subtree (`DELETE /shared-slots/{sharedSlot}/blocks/{block}`), and clear every block for clear-and-replace (`DELETE /shared-slots/{sharedSlot}/blocks`); the two deletes require `shared-slots.write` plus `content.blocks.delete`. Existing Shared Slot block content edits continue to use `PATCH /blocks/{block}` with `shared-slots.write`. Every write rebuilds assignments on pages using the slot and captures a Shared Slot revision.

Remaining:

- deeper header/navbar construction helpers only if they stay generic CMS behavior

### Phase 3

Delivered:

- draft-safe page-owned block topology endpoints: add a single block, reorder a slot sibling group, and delete a block subtree (see [Page Block Topology API](#page-block-topology-api))
- page assets: list, attach, update, and detach per-page `/site` CSS and JS files with `page-assets.write` (see [Page Assets API](#page-assets-api))
- media by existing media ID only: content plans and block payloads accept `media_id`/`asset_id` (and Gallery `gallery_media_ids`/`gallery_items`), validated for record existence and block-type kind compatibility, while remote fetch inside plans stays rejected

Remaining:

- controlled draft updates or draft content replacement beyond the existing block PATCH

### Phase 4

- additional explicit workflow transitions beyond publish when they have separate design and permissions

## AI Usage Guidance

- discover sites, locales, layouts, and block types first
- validate before apply
- create draft content
- prefer structured blocks from `docs/public-block-render-markup.md`
- avoid Safe HTML except as a reviewed fallback
- keep generated public copy in the target language, such as English for an English homepage

## Boundaries

- no OpenAI or LLM integration in CMS core
- no crawling or fetching
- no arbitrary import/export replacement
- no automatic publish
- no destructive delete in Phase 1
- no host `/admin` route assumption
- no `/cms` route prefix use
- no QuizTem-specific runtime code; QuizTem homepage generation is a later consumer use case for this generic CMS API
