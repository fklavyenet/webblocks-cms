---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/ai-page-building-guide
cms_title: AI Page Building Guide
cms_layout: docs
cms_source_id: webblocks-cms:docs/ai-page-building-guide.md
---

# AI Page Building Guide

This guide defines the safe workflow for trusted AI/operator tools that build WebBlocks CMS pages through the Internal Content API. It is generic CMS product guidance. Do not add site-specific import, sync, or scraping behavior to CMS core.

Before proposing or applying a design, read the [CMS Inventory](inventory.md). It is the per-block authoring contract: what each shipped block renders, which fields stay CMS-editable, which child and media relationships are valid, and which visual results are not expressible yet. Tools can fetch the same document over the API with `GET /webadmin/api/inventory`.

The `html` block is a human-only escape hatch and is never a fallback. The API cannot create, change, move, publish, or delete it; such requests are rejected with `422` and code `block_type_not_api_writable`. When a design cannot be expressed with structured blocks, settings, and site CSS, report a capability gap instead of generating raw HTML.

External AI/operator tools do not need local filesystem access to the CMS repository or installed package docs. Start with the live API discovery endpoint:

```text
GET /webadmin/api
```

In installed package-native sites, this guide also ships inside the Composer package at:

```text
vendor/fklavyenet/webblocks-cms/docs/ai-page-building-guide.md
```

## Purpose

Trusted AI/operator tools can inspect a CMS install, build a structured draft content plan, validate it, create a separate draft page, replace specific page-owned slots on an existing draft page after explicit user approval, or call explicit publish endpoints when the token has `content.publish`. The normal page-building workflow is draft-first and API-first. Content apply does not publish content, overwrite published pages, clear Shared Slot-backed slots, fetch remote websites, or import media.

## Token Setup

Create API tokens from the CMS admin panel:

- Editors and site admins create personal AI tokens under **Profile → Manage AI Tokens**. They select only sites already assigned to their account, choose delegated capabilities, set a 30, 90, or 365 day expiry, and may restrict the token to IPv4/IPv6 or CIDR networks with a token-specific request ceiling. The CMS intersects this scope with the owner’s live role and site assignments on every request; disabling the user or removing a site assignment takes effect immediately.
- Super admins may use the same personal-token flow when an AI should act as their named delegate. Install-level service automation continues to use **System → API Tokens**.
- Personal tokens never receive backup, maintenance, plugin, Embedded Application, domain, site-asset, page-asset, or admin-render authority. Editors cannot delegate publishing or site-setting changes. A token capability narrows user authority and never expands it.
- Personal content writes must identify the target site explicitly. Discovery responses for sites, pages, blocks, navigation, Shared Slots, and media are restricted to the token’s effective site scope.

For installation-level automation, a Super admin instead opens **System → API Tokens** and grants only the required System-token capabilities.

The plain token is shown once immediately after creation. Store it in a trusted operator secret store and never paste a real token into prompts, documentation, logs, screenshots, tickets, or release reports.

Use the API discovery base URL in local tool configuration:

```dotenv
WEBBLOCKS_CMS_API_URL=https://example.com/webadmin/api
WEBBLOCKS_CMS_API_TOKEN=...
```

For normal page-building tools, keep the default page-building capabilities selected. Grant advanced publish or page-delete capabilities only to trusted operator tools that explicitly need them.

API requests use:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## First Discovery Calls

Start with API discovery. The first call is:

```text
GET /webadmin/api
```

Without a valid token, this endpoint returns only minimal public-safe bootstrap JSON. With a valid Bearer token, it returns links to the OpenAPI schema, AI guide, content contract, examples, validate/apply endpoints, pages, navigation, and Shared Slots.

Then follow the returned links. Common token-protected endpoints live under `/webadmin/api`:

```text
GET /webadmin/api/openapi.json
GET /webadmin/api/ai-guide
GET /webadmin/api/examples/contact-page
GET /webadmin/api/sites
GET /webadmin/api/locales
GET /webadmin/api/page-layouts
GET /webadmin/api/block-types
GET /webadmin/api/content-contract
GET /webadmin/api/media
GET /webadmin/api/blocks
GET /webadmin/api/blocks/{block}
PATCH /webadmin/api/blocks/{block}
GET /webadmin/api/navigation-menus
POST /webadmin/api/navigation-menus/{navigationMenu}/items
PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/{item}
PATCH /webadmin/api/navigation-menus/{navigationMenu}/items/reorder
DELETE /webadmin/api/navigation-menus/{navigationMenu}/items/{item}
GET /webadmin/api/shared-slots
GET /webadmin/api/pages
POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks
POST /webadmin/api/pages/{page}/sync-layout-slots
POST /webadmin/api/sites/{site}/public-theme
```

Use `GET /webadmin/api/pages` when you need to check existing slugs, live placeholder pages, or previous drafts before proposing a new page.

## Never Guess Block Handles

AI tools must not invent or guess block handles. Exact handles must be learned from `GET /webadmin/api/block-types` or `GET /webadmin/api/content-contract` for the current install before building a plan.

Examples of handles that commonly exist but must still be verified at runtime:

```text
section
container
grid
card
card_body
hero
cta
plain_text
rich-text
button_link
sticky-navbar
```

Do not substitute nearby spellings such as `plain-text`, `rich_text`, `button`, `navbar`, or `navigation_auto` unless discovery confirms those exact handles.

For blocks that expose `settings.icon_slug`, use only active icon slugs confirmed by the live icon/catalog-backed admin contract. The catalog is seeded in full at install, so it is populated without an operator step. Any active catalog icon is accepted on content blocks; `GET /webadmin/api/icon-catalog?context=content` marks the ones suited to that block with `suggested: true` and lists the rest after them. Navigation icons stay curated: `context=navigation` is the allowlist for navigation items and sidebar nav blocks. Blocks may also expose shared `settings.icon_tone` with the public visual tone values `default`, `soft`, `brand`, `accent`, `highlight`, `bold`, and `quiet`; do not use semantic status tones such as `success` or `danger` for icon tone. Do not invent icon class names, upload SVGs, inject inline SVG, add inline colors, or add site-specific CSS to simulate product icons. Badge labels are locale-owned where the block contract exposes `eyebrow`/`badge_label`; badge tones are shared settings and must stay within the discovered semantic allowlist.

## Safe Workflow

1. Run read-only discovery.
2. Read OpenAPI, the content contract, and examples from the live API links.
3. Build a content plan using only discovered handles and the current site/layout/locale.
4. Validate with `POST /webadmin/api/content/validate`.
5. Read validation errors, warnings, and the `renderability` summary; adjust until the plan has no empty wrapper or empty user-facing content problems.
6. Ask the user for explicit approval to apply the exact final plan.
7. Only after approval, call `POST /webadmin/api/content/apply`.
8. Read the created draft page id from the apply response.
9. Set page-level SEO and any further locales with `/webadmin/api/pages/{page}/translations`; content apply cannot write them.
10. Render the page with `GET /webadmin/api/pages/{page}/render` and check the result before reporting the page finished. `/webadmin/pages/{page}/preview` serves the same render for a browser, with the same Bearer token, and always in the default locale.
11. Leave publishing to a human workflow unless the user explicitly approved an API publish operation and the token has `content.publish`.

## Page Identity, SEO, and Locales

Page identity and page-level SEO live on the page translation row. Content apply writes one translation for one locale when it creates the page and never touches it again, so everything afterwards goes through `/webadmin/api/pages/{page}/translations` with `content.apply`.

- `GET /webadmin/api/pages/{page}/translations` lists every translation with `name`, `slug`, `path`, `seo_title`, `seo_description`, `seo_keywords`, `og_title`, `og_description`, and `og_image_media_id`. The same fields appear on each translation in `GET /webadmin/api/pages/{page}`.
- `POST /webadmin/api/pages/{page}/translations/{locale}` adds a locale, by code or ID, that the site already has enabled. Only `name` is required; omit `slug` and `path` to derive both from it.
- `PATCH /webadmin/api/pages/{page}/translations/{translation}` writes only the fields present, so `seo_description` can be set without resending page identity. Send `null` to clear an optional field.

Slug and path move together: sending `path` re-derives the slug from its last segment, and sending `slug` alone moves the page to `/{slug}`. Renaming the default-locale translation renames the page itself, because `Page` title and slug read through to it.

Do not put `page.seo_title` or any other SEO field in a content plan. Plans reject keys they do not understand with `422` and code `unsupported_plan_fields`, and a rejected plan writes nothing.

Adding a language is two steps. Create or enable the locale globally with `POST`/`PATCH /webadmin/api/locales`, then assign it to the site with `PUT /webadmin/api/sites/{site}/locales` and `locale_ids`. A page translation cannot be saved for a locale the site has not enabled. That call replaces the whole set, always keeps the default locale, and refuses to detach a locale that still has page translations.

Site-wide SEO defaults, inherited by any page whose translation has no override, use `PATCH /webadmin/api/sites/{site}/seo` with `site-settings.write`. That is separate from page-level SEO and from `PATCH /webadmin/api/sites/{site}/head`, which injects raw head markup.

Translating the blocks of an existing page is `PATCH /webadmin/api/blocks/{block}` with `locale` plus the fields the block's translation family owns: `title`/`eyebrow`/`subtitle`/`content`/`meta` for text blocks, `title` for a button, `caption`/`alt_text` for an image, and `title`/`content`/`submit_label`/`success_message`/`consent_label` for a contact form. A contact form's button and confirmation text live only there — they are translations, not settings, and a page whose form still reads "Send message" in every language is the sign they were skipped. A field the family does not own is refused with `unsupported_block_translation_fields` rather than stored somewhere nothing reads it.

## Privacy And Consent

Two separate consent surfaces exist. Neither needs a Trusted HTML block, and neither should be hand-rolled.

- **Cookie/analytics consent** is a site-wide feature, not a block. Visitor Reports plus the System Settings toggle *Show the public privacy settings banner* render WebBlocks UI's Cookie Consent pattern on every public page and post the visitor's decision to `POST /privacy-consent/sync`, which returns the consent cookie that gates analytics tracking. There is nothing to place on a page and nothing to author — if a site needs a cookie banner, the answer is the toggle, not markup.
- **Form consent** is per contact form. Set `settings.consent_required` and translate `consent_label` on the `contact_form` block to render a required checkbox whose label is the data-processing notice. The accepted submission records the time and a copy of that wording. Do not demote the notice into the form's intro text: intro copy is prose beside the form, not an auditable per-submission fact. `consent_required` is closed to `PATCH`, so it is set in the admin or in the block's creating plan.

A full-site translation pass is bulk work, so pace it: the installation API allows 120 requests per minute per token and IP by default (read `x-rate-limit` in the OpenAPI document for the live value), a personal token may impose a lower 30/60/120/300 ceiling, hosting layers may allow fewer, and `429` carries a `Retry-After` to honour.

## Safety Rules

- Draft-first.
- Render the page before reporting it finished. Content apply reports what was stored, not what renders: an empty wrapper, a slot pointing at the wrong Shared Slot, or a layout missing the slots the plan assumed all apply cleanly and still produce a broken page.
- Content plans reject unrecognized fields. If a plan fails with `unsupported_plan_fields`, remove the named keys rather than retrying; the field belongs to another endpoint or to the browser admin.
- After building a contact form, set the recipient with `PATCH /webadmin/api/sites/{site}/contact-recipient` and `site-settings.write`. Until it is set, the form collects submissions against the install-wide fallback rather than the site's own address.
- Use `GET`/`POST /webadmin/api/media/folders` to organize uploads; creating a folder requires `media.write`. A duplicate name under the same parent is refused with `media_folder_exists` and the existing folder in the response — reuse that id instead of retrying.
- Shared Slots can be corrected with `PATCH /webadmin/api/shared-slots/{sharedSlot}` and `shared-slots.write`. Deletion requires the explicit destructive `shared-slots.delete` and is refused while any page slot still references the Shared Slot. Moving one between sites is browser-admin work.
- Site domains under `/webadmin/api/sites/{site}/domains` need `domains.write` to add, update, or promote, and the destructive `domains.delete` to remove. Domains decide which hostname resolves to which site; a page-building token should not hold these.
- Apply only after explicit user approval.
- Do not publish through content apply.
- Do not assume page publish makes all blocks public; use `include_page_owned_blocks: true` only after explicit approval.
- Do not delete pages through content apply.
- Do not overwrite existing pages or blocks except with the explicit `replace_existing_draft_page` mode.
- Do not replace live published pages directly. Use `create_staged_update_for_published_page`, `replace_staged_page_update`, render the staged page with `GET /webadmin/api/pages/{staged_page}/render`, then use `promote_staged_page_update` only after explicit approval and only with `content.publish`.
- Do not call apply if the target path already exists unless the user explicitly approves a conflict-handling plan supported by the API.
- For existing draft replacement, include `expected_path` or `expected_updated_at` and replace only page-owned slots.
- Treat `page.path` as the canonical public URL. Use `/contact`, `/games/fruit-train`, or `/docs/internal-content-api`, not `/p/contact`; `/p/...` is only a legacy public redirect. Preserve slash-bearing section paths; CMS derives the short slug from the final path segment.
- Do not try to replace Shared Slot-backed slots; leave shared header/footer assignments intact.
- Before assigning a Shared Slot to a layout slot such as `header`, confirm the page actually has that Page Slot. If it is missing but belongs to the selected Page Layout, call `POST /webadmin/api/pages/{page}/sync-layout-slots` before assignment.
- Shared Slot blocks are draft by default. When the user explicitly approves public Shared Slot changes and the token has `content.publish`, call `POST /webadmin/api/shared-slots/{sharedSlot}/publish-blocks`; do not try to publish Shared Slot content through page publish cascade fields.
- Do not fetch remote pages.
- Do not use browser automation or admin UI clicks when API discovery is available.
- Do not put remote media URLs directly inside content plans. Use `GET /webadmin/api/media` to discover existing CMS Media Library records; the dedicated read capability is `media.read`, with transitional `content.read` compatibility on older tokens.
- Use `POST /webadmin/api/media` only when discovered and when the token has `media.upload`; uploaded files become normal admin-visible Media Library records.
- Use `POST /webadmin/api/media/fetch` only when discovered and when the token has `media.upload`; it fetches one approved public HTTP/HTTPS file URL with public-network, redirect, MIME, and size guards. Assign the returned `media.id` in the content plan.
- Use `PATCH /webadmin/api/media/{media}` only when discovered and when the token has `media.write`; this media-write scope is metadata-only for `title`, `alt_text`, `caption`, and `description`.
- Use `POST /webadmin/api/media/{media}/replace`, `POST /webadmin/api/media/{media}/move`, or `DELETE /webadmin/api/media/{media}` only when discovered and when the token has the matching `media.replace`, `media.move`, or `media.delete` capability. Delete keeps the CMS usage guard and must not remove media still referenced by blocks, site branding, or page SEO.
- For approved public remote files, use only the discovered `POST /webadmin/api/media/fetch` endpoint. Do not put remote URLs directly in content plans.
- In content plans, assign uploaded Media Library records to native media blocks with `media_id` or `asset_id` on `image`, `navbar-brand`, `sidebar-brand`, `file`, `download`, and `video`, or with `gallery_items` / `gallery_media_ids` on `gallery`. Gallery assignments preserve the submitted item order. For card-like designs, put the media on a nested `image` block inside `card` / `card_body`.
- Do not use Trusted HTML, invented file paths, static markup, unsupported `settings.logo_url`, or `/cms/brand/*` product assets for native media-backed fields. For public favicon/social image changes, upload or discover an image media id and use `PATCH /webadmin/api/sites/{site}/branding`. For an existing `navbar-brand` or `sidebar-brand` block, upload or discover the image media id and use `PATCH /webadmin/api/blocks/{block}` with `media_id`, safe settings, and translations. For an existing `image` block, use the same endpoint with `translations.image.alt_text` and `translations.image.caption` when only the locale-owned image copy changes. Shared Slot source blocks also require `shared-slots.write`.
- Do not create API tokens from automation unless the user explicitly asks for token administration.
- Do not print, log, or report token values.
- Report only status codes and safe summarized response data.
- Treat `401`, `403`, and `422` JSON responses as API feedback and follow their discovery/documentation links.
- Build block trees with nested `children` arrays only. Do not use flat `id`, `parent_id`, `block_id`, `slot_type_id`, or `block_type_id` fields in content plans; those are database implementation details and validation rejects them.
- Put block translations directly under `translations` for the selected plan locale, such as `translations.title` or `translations.content`. Do not nest block copy under `translations.en`, `translations.tr`, or other locale keys.
- Wrapper blocks such as `section`, `container`, `cluster`, `grid`, `card`, `card_body`, `card_footer`, `sticky-navbar`, and `sidebar-navigation` must contain meaningful child blocks. Creating wrappers without children is invalid because it renders empty chrome.

## Mode-Aware Site CSS

Migration and new-site tools should treat site CSS as a narrow site-specific override layer, not as the primary layout or color system. Use native block settings, Media Library-backed fields, public theme tokens, and inherited WebBlocks UI `wb-*` component styles first.

Choose layout blocks by intent: `stack` arranges content top to bottom and owns vertical rhythm; `cluster` groups compact inline items and may wrap; `split` pairs exactly two direct children so the first grows and the second stays content-sized; `grid` creates repeated equal columns; `container` owns width. When one side of a Split needs several blocks, make that side a nested Stack rather than adding a third Split child.

When site-level CSS is needed, use the canonical asset endpoints:

```text
GET /webadmin/api/sites/{site}/assets/css
PUT /webadmin/api/sites/{site}/assets/css
```

The read and write responses include `asset.analysis.mode_awareness` for CSS assets. Tools should inspect this object together with `asset.guidance` before and after edits. `status = warning` does not block writes, because some brand systems need intentional custom colors, but it is not a completion signal. Review, report, or fix the listed warnings before marking a migration or new site setup complete.

Prefer these patterns:

- connect page, surface, text, muted text, border, and accent roles to `--wb-public-*` tokens
- define site-specific semantic custom properties, then give them light and dark values
- scope unavoidable dark values under active mode selectors such as `html[data-mode="dark"] body[data-wb-public-theme]`
- use native WebBlocks UI controls such as `wb-card`, `wb-input`, `wb-textarea`, and `wb-btn` as the reference for expected mode behavior

Avoid these patterns:

- page-wide hard-coded light backgrounds
- dark text on surfaces that should follow tokens
- white card overrides that ignore dark mode
- one-off custom light/dark palettes when public theme tokens can express the design

## Good Structures

Prefer structured blocks over a single large content blob.

Marketing homepage:

```text
section -> container -> content_header + cluster -> button_link
section -> container -> grid -> stat-card
section -> container -> columns -> column_item
section -> container -> cta + cluster -> button_link
```

Header/navbar:

```text
shared_slot header
sticky-navbar -> container -> cluster -> navbar-brand + cluster -> navbar-navigation + header-actions
```

Use the CMS Navigation API for navbar links. Navigation item URLs must be safe paths or `http`/`https` URLs; for same-page anchors, use a path plus fragment such as `/#platform`, not a raw `#platform` value. If `GET /webadmin/api/sites` shows the target site rendering with the wrong `public_theme_preset`, update it with `POST /webadmin/api/sites/{site}/public-theme` instead of trying to force theme mode with page content.

For most public pages, place wide promo blocks such as `hero` and `cta` inside `section -> container`. Direct full-width `hero` or `cta` blocks under `main` should be intentional edge-to-edge design choices, not the default.

Contact page:

```text
section -> hero + contact_form
```

Use the native `contact_form` block for contact pages after discovery confirms the handle is available. Its visible copy is translated with `title`, `content`, `submit_label`, and `success_message`; shared settings are `recipient_email`, `send_email_notification`, and `store_submissions`. The renderer emits the native CSRF-protected public form, CMS-owned hidden generated anti-spam check field, and `/contact-messages` submit endpoint. AI/operator tools should not create the check field manually and should not use Trusted HTML, raw forms, or `mailto:` as substitutes.

Index page that lists other pages:

```text
section -> container -> header + page-list
```

Use the native `page-list` block instead of hand-building a card grid that has to be rebuilt whenever a page is added. It renders published pages of a page type (`settings.scope = page_type` plus `settings.page_type`), of a path subtree (`path_prefix`), or below the hosting page (`subtree_of_current`), with `settings.sort`, `settings.limit` (1-48), `settings.layout` (`cards` or `links`), and `settings.columns`. Titles, descriptions, and thumbnails are read from each listed page's translation, so the block itself carries no editorial copy and accepts no children. To control what a card says, set `list_excerpt` on the listed page's translation through the Page Translation API rather than rewriting its SEO description, which is authored for search results; with no excerpt the SEO description is used, trimmed. Published status, site, render locale, Shared Slot source pages, and the hosting page are always filtered by the query and are not settings. There is no pagination: build a curated `link-list` if a specific order or a longer list is required.

## Bad Structures

- Do not put a full page into one `rich-text` block.
- Do not put a full page into one trusted `html` block when structured blocks can represent it.
- Do not represent nesting with sibling rows plus `id`/`parent_id`; use nested `children`.
- Do not create `section`, `container`, `cluster`, or `grid` blocks unless they contain useful child content.
- Do not submit locale-keyed block translation shapes such as `translations.en.title`; use `translations.title` for the plan locale.
- Do not build contact forms with Trusted HTML, raw form markup, or `mailto:` links when `contact_form` is available.
- Do not hand-build a card grid of links to other pages when `page-list` can derive it, and do not add child blocks to `page-list`.
- Do not guess handles.
- Do not overwrite published content.
- Do not mutate an existing live page when a new separate draft page is safer.
- Do not paste tokens into prompts or reports.

## Minimal Draft Plan Example

This example assumes discovery confirmed `section`, `container`, `content_header`, `cluster`, `grid`, `card`, `card_body`, `plain_text`, `button_link`, and `cta`.

```json
{
  "plan": {
    "site": "default",
    "locale": "en",
    "layout": "default",
    "page": {
      "title": "Example Homepage Draft",
      "path": "/example-homepage-draft",
      "status": "draft"
    },
    "slots": {
      "main": [
        {
          "type": "section",
          "settings": {
            "spacing": "lg"
          },
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "content_header",
                  "translations": {
                    "title": "Build useful pages faster",
                    "subtitle": "A structured CMS workflow for practical content teams.",
                    "content": "Create focused draft pages from reusable blocks, then review them safely before publishing."
                  }
                },
                {
                  "type": "cluster",
                  "children": [
                    {
                      "type": "button_link",
                      "translations": {
                        "title": "Start building"
                      },
                      "settings": {
                        "url": "/get-started",
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
          "settings": {
            "spacing": "lg"
          },
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "grid",
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
                                "content": "Create drafts from structured content plans."
                              }
                            }
                          ]
                        }
                      ]
                    },
                    {
                      "type": "card",
                      "children": [
                        {
                          "type": "card_body",
                          "children": [
                            {
                              "type": "plain_text",
                              "translations": {
                                "content": "Review safely through authenticated admin preview."
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
          "settings": {
            "spacing": "lg"
          },
          "children": [
            {
              "type": "container",
              "children": [
                {
                  "type": "cta",
                  "translations": {
                    "title": "Ready for review?",
                    "content": "Validate the plan, apply only after approval, then open the admin preview."
                  }
                },
                {
                  "type": "cluster",
                  "children": [
                    {
                      "type": "button_link",
                      "translations": {
                        "title": "Open docs"
                      },
                      "settings": {
                        "url": "/docs",
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

Validate first:

```text
POST /webadmin/api/content/validate
```

Apply only after explicit approval:

```text
POST /webadmin/api/content/apply
```

Then preview:

```text
/webadmin/pages/{page}/preview
```

The preview URL is a browser/admin route, not a JSON API endpoint. It can be opened by an authenticated admin browser session or fetched with `Authorization: Bearer <token>` when the CMS API token has `content.read`. Missing or invalid preview tokens return JSON `401`, insufficient preview tokens return JSON `403`, and browser requests without a token continue to redirect to the CMS login page.

## Existing Draft Slot Replacement

Use this mode only when the user explicitly wants to update an existing draft page instead of creating a new draft. Validate first, then apply only after approval:

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
            "content": "Updated draft page copy."
          }
        }
      ]
    }
  }
}
```

Validate:

```text
POST /webadmin/api/content/validate
```

Apply:

```text
POST /webadmin/api/content/apply
```

The CMS removes old page-owned blocks only from the named slots and writes the new block tree in one transaction. Shared Slot-backed slots are rejected by this mode, so header/footer assignments remain untouched unless a separate supported API operation changes them.

## Explicit Publish

Publishing is not part of validate/apply. Trusted operator tools with `content.publish` may call:

```text
POST /webadmin/api/pages/{page}/publish
POST /webadmin/api/pages/{page}/publish-page-owned-blocks
```

`POST /webadmin/api/pages/{page}/publish` defaults to:

```json
{
  "include_page_owned_blocks": false
}
```

With the default, the endpoint publishes only the page record. It does not publish draft or in-review blocks. Set `include_page_owned_blocks: true` only when the user explicitly wants all unpublished page-owned blocks for that page to publish too. The cascade includes nested child blocks under page-owned slots and excludes Shared Slot-backed slots.

`POST /webadmin/api/pages/{page}/publish-page-owned-blocks` publishes page-owned draft or in-review blocks without changing the page workflow status.

Never request Shared Slot cascade publishing from page publish endpoints. Shared Slot content is not included and must be reviewed and published separately.

## Real Site Revisions

For real sites, first inspect the current pages and existing drafts. If a draft already exists, preview it before proposing new work. Use `replace_existing_draft_page` only for explicit draft-safe page-owned slot replacement. Otherwise, create a new separate draft page instead of overwriting the existing draft or live published homepage.

When the target is already published and must keep serving its public URL, use the staged update workflow instead of taking the page back to draft. Create a staged update for the published page, replace only page-owned managed slots on the staged draft, preview `/webadmin/pages/{stagedPage}/preview`, and promote only after approval. Promote writes page-owned promoted blocks as published and keeps Shared Slot-backed slots out of scope.

For QuizTem-style homepage revisions, use the existing draft as reference material only unless the user explicitly approves a supported update flow. The safe default for new work is:

1. Preview the existing draft.
2. Build a better structured plan with discovered block handles.
3. Validate the plan.
4. Ask for explicit apply approval.
5. Create a new separate draft page.
6. Open `/webadmin/pages/{page}/preview` for human review.
