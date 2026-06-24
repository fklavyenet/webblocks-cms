# Internal Content API

## Purpose

The Internal Content API is a secure CMS API for trusted AI and operator tools. It lets those tools inspect CMS content contracts, create draft-first content, replace specific page-owned slots on existing draft pages, and run explicit publish operations through structured JSON without logging into, scraping, or automating the browser admin UI.

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

API discovery starts at:

```text
GET /webadmin/api
```

Unauthenticated callers receive only public-safe bootstrap JSON. Authenticated callers receive safe product version metadata plus links to OpenAPI, the AI guide, content contract, examples, content validate/apply, pages, navigation, and Shared Slots. External AI/operator tools should start from this live discovery response instead of reading the CMS repository or local package docs.

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

Super admins can revoke a token to immediately disable API access while keeping the audit row visible, or delete a token to permanently remove the token record from the list. Deleting an active token also immediately disables API access because the authenticator can no longer find a matching stored hash.

Local AI and operator tools should store the generated token in a trusted operator secret store.

The CMS runtime does not require `WEBBLOCKS_CMS_INTERNAL_API_TOKEN`.

Authentication rules:

- missing, wrong, or revoked tokens return JSON `401`
- revoked tokens stop working immediately
- tokens must never be printed in logs, diagnostics, support reports, tests, or documentation examples
- token comparison must use a constant-time comparison
- successful API requests update the token's `last_used_at` and `last_used_ip`
- successful API requests also store a truncated user-agent for operator audit context
- responses are JSON-only

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

Destructive and publish capabilities are separate advanced options and are not selected by default:

- `content.publish`
- `pages.delete`

Write endpoints check the relevant capability server-side. Missing capabilities return JSON `403` with `api_discovery_url`, `openapi_url`, `documentation_url`, and `example_url` guidance. Normal page-building tokens should not include destructive capabilities.

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
GET /webadmin/api/page-layouts
GET /webadmin/api/block-types
GET /webadmin/api/content-contract
GET /webadmin/api/pages
GET /webadmin/api/pages/{page}
POST /webadmin/api/pages/{page}/publish
POST /webadmin/api/pages/{page}/publish-page-owned-blocks
POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot
GET /webadmin/api/blocks
GET /webadmin/api/blocks/{block}
GET /webadmin/api/navigation-menus
GET /webadmin/api/navigation-menus/{navigationMenu}
POST /webadmin/api/navigation-menus
POST /webadmin/api/navigation-menus/{navigationMenu}/items
GET /webadmin/api/shared-slots
GET /webadmin/api/shared-slots/{sharedSlot}
POST /webadmin/api/shared-slots
POST /webadmin/api/shared-slots/{sharedSlot}/blocks
```

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

### Existing Draft Page Slot Replacement

Existing draft page replacement stays inside the validate/apply contract:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

Use `mode: replace_existing_draft_page` to replace one or more page-owned slots on an existing draft page. The operation requires `content.validate` for validate and `content.apply` for apply. It does not require `pages.delete`, because it is not a general page delete operation.

Example:

```json
{
  "plan": {
    "mode": "replace_existing_draft_page",
    "site": "default",
    "locale": "en",
    "page": {
      "id": 9,
      "expected_path": "/p/contact",
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
- the target page must belong to the requested site and locale must be enabled for that site
- each slot must exist on the page and use page-owned blocks
- Shared Slot-backed slots are rejected instead of being cleared
- only blocks in the named `replace_slots` are removed
- old blocks are removed and new blocks are written in one transaction
- page revisions are captured before and after apply
- no publish, media fetch/import, broad delete, or Shared Slot assignment clearing happens

### Explicit Publish Endpoints

Publishing is separate from content apply and requires a token with `content.publish`.

```text
POST /webadmin/api/pages/{page}/publish
POST /webadmin/api/pages/{page}/publish-page-owned-blocks
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

AI/operator tools must not assume page publish makes all block content public. Use `include_page_owned_blocks: true` only when the user explicitly approved publishing all unpublished page-owned blocks for that page. Shared Slot content must be reviewed and published separately.

### Content Contract Endpoint

`GET /webadmin/api/content-contract` is a read-only discovery endpoint for trusted AI/operator tools. It returns the API prefix, validate/apply URLs, admin preview URL template, safety flags, discovery URLs, recommended page-building patterns, and sanitized block contract metadata.

The endpoint is generic CMS product behavior. It must not return install-specific secrets, token values, raw Blade contents, absolute filesystem paths, private server paths, or site-specific instructions. Block contract rows may include handle/slug, label, category, status, container and child support, translatable fields, shared settings fields, and public renderer root behavior.

AI tools should call this endpoint or `GET /webadmin/api/block-types` before building a plan and must use only handles that are present in the current install.

The `contact_form` contract includes additional safe form metadata: settings schema, translated fields, the public `POST /contact-messages` submit endpoint, required CSRF browser behavior, server validation rules, the hidden `website` honeypot, generic honeypot success behavior, spam classification/quarantine notes, storage-before-notification behavior, recipient fallback order, safe notification failure recording, and `/webadmin/contact-messages` review behavior. Contact-page tools should use that native block rather than Trusted HTML, raw form markup, or `mailto:` forms.

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
- `GET /webadmin/api/page-layouts`
- `GET /webadmin/api/block-types`
- `GET /webadmin/api/content-contract`

### Page Endpoints

- `GET /webadmin/api/pages`
- `GET /webadmin/api/pages/{page}`
- `POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot`

### Block Endpoints

- `GET /webadmin/api/blocks`
- `GET /webadmin/api/blocks/{block}`

### Navigation Endpoints

- `GET /webadmin/api/navigation-menus`
- `GET /webadmin/api/navigation-menus/{navigationMenu}`
- `POST /webadmin/api/navigation-menus`
- `POST /webadmin/api/navigation-menus/{navigationMenu}/items`

Navigation menus use the existing CMS `navigation_items.menu_key` model. Phase 2A supports the shipped CMS menu handles such as `primary`, `footer`, `mobile`, `legal`, and `docs`; it does not add a separate menu table. Creating a navigation menu is treated as creating a safe site-scoped menu group with optional initial items. It refuses to overwrite a site/menu that already has items.

Navigation item URLs may be internal paths such as `/`, `/about`, and `/contact`, or safe `http`/`https` URLs. The API rejects `javascript:`, `data:`, protocol-relative URLs, traversal, malformed URLs, unsupported targets, and empty labels. Navigation endpoints do not create pages, publish pages, crawl sites, or fetch remote URLs.

### Shared Slot Endpoints

- `GET /webadmin/api/shared-slots`
- `GET /webadmin/api/shared-slots/{sharedSlot}`
- `POST /webadmin/api/shared-slots`
- `POST /webadmin/api/shared-slots/{sharedSlot}/blocks`

Shared Slot creation is site-scoped and refuses duplicate handles for the same site. Shared Slot blocks reuse the same block payload writer used by page-owned blocks, so locale-owned copy stays in translation rows and shared settings remain on the block record/settings path. Media import and media assignment remain outside this phase.

### Page Slot Assignment

```text
POST /webadmin/api/pages/{page}/slots/{slot}/shared-slot
```

The endpoint assigns an existing compatible same-site active Shared Slot to an existing page slot. It does not create missing pages or slots. It does not publish the page. It refuses cross-site, inactive, and incompatible Shared Slots. It also refuses to switch a slot that still has page-owned blocks, because Phase 2A does not delete or replace those blocks automatically.

### Content Validate / Apply Endpoints

- `POST /webadmin/api/content/validate`
- `POST /webadmin/api/content/apply`

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

### List Pages

```text
GET /webadmin/api/pages
```

### Read Page Details

```text
GET /webadmin/api/pages/{page}
```

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
          "type": "hero",
          "translations": {
            "title": "Plan, build, and publish with confidence",
            "subtitle": "Structured content for modern teams",
            "content": "Create a draft homepage from a validated content plan."
          },
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
          "type": "cta",
          "translations": {
            "title": "Ready to shape the next page?",
            "content": "Use structured plans for repeatable content creation."
          },
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
  }
}
```

## Validation Rules

- site handle or ID must resolve
- locale must exist and be enabled for the target site
- layout must exist
- path conflict blocks page creation
- block type must be published and usable
- child support must follow block contracts where available
- user-facing text belongs in translation rows
- shared settings remain shared
- unknown unsafe settings are rejected
- harmless unknown settings may warn or be ignored consistently
- apply validates again before writing
- apply is transactional
- Content apply still rejects publish, site creation, media import, remote fetch, unsupported overwrite, unsupported replace, and delete operations
- navigation and Shared Slot creation are create-only unless a later phase adds explicit draft-safe mutation contracts

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

`page_slot_shared_slots[].page` may refer to the page created by the same plan by using `created`, or to an existing page ID. `shared_slot` may refer to a Shared Slot created earlier in the same plan or an existing same-site Shared Slot handle.

## Future Phases

### Phase 2B

- optional draft-safe update/move endpoints for navigation and Shared Slot blocks
- explicit safe clearing/replacement contracts where needed
- deeper header/navbar construction helpers only if they stay generic CMS behavior

### Phase 3

- resource endpoints for draft-safe direct page/block edits where needed
- controlled draft updates or draft content replacement
- page assets
- media by existing media ID only

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
