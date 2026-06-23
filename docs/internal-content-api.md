# Internal Content API

## Purpose

The Internal Content API is a secure CMS API for trusted AI and operator tools. It lets those tools inspect CMS content contracts and create draft-first content through structured JSON without logging into, scraping, or automating the browser admin UI.

Phase 1 is implemented as a token-protected, JSON-only, non-public API for read-only content discovery plus draft page creation through validated content plans. Phase 2A adds safe foundations for navigation menus, Shared Slots, and explicit page slot Shared Slot assignment. The API remains intentionally non-destructive.

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

Local AI and operator tools may store the generated token in their own local `.env`:

```dotenv
WEBBLOCKS_CMS_URL=https://example.com
WEBBLOCKS_CMS_API_TOKEN=...
```

The CMS runtime does not require `WEBBLOCKS_CMS_INTERNAL_API_TOKEN`.

Authentication rules:

- missing, wrong, or revoked tokens return JSON `401`
- revoked tokens stop working immediately
- tokens must never be printed in logs, diagnostics, support reports, tests, or documentation examples
- token comparison must use a constant-time comparison
- successful API requests update the token's `last_used_at` and `last_used_ip`
- responses are JSON-only

Example request:

```http
GET /webadmin/api/sites
Authorization: Bearer <token>
Accept: application/json
```

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

`validate` checks a complete content plan and writes nothing. `apply` validates the plan again and then creates the requested draft page, navigation items, Shared Slots, Shared Slot block trees, and page slot Shared Slot assignments transactionally. This is useful for AI-generated pages, templates, starter pages, shared headers/footers, and migration helpers where the CMS should avoid half-created content.

The request body may still contain a `plan` field or another structured content plan payload. The URL should remain `/content/validate` and `/content/apply`.

Both modes are needed:

- the Resource API exposes the existing CMS content model and contracts to internal tools
- the Content Validate / Apply API avoids partial writes during larger page builds

### Content Contract Endpoint

`GET /webadmin/api/content-contract` is a read-only discovery endpoint for trusted AI/operator tools. It returns the API prefix, validate/apply URLs, admin preview URL template, safety flags, discovery URLs, recommended page-building patterns, and sanitized block contract metadata.

The endpoint is generic CMS product behavior. It must not return install-specific secrets, token values, raw Blade contents, absolute filesystem paths, private server paths, or site-specific instructions. Block contract rows may include handle/slug, label, category, status, container and child support, translatable fields, shared settings fields, and public renderer root behavior.

AI tools should call this endpoint or `GET /webadmin/api/block-types` before building a plan and must use only handles that are present in the current install.

## Phase 1 Scope

### Discovery Endpoints

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
- no publish
- no overwrite of existing published content
- no overwrite of existing pages or blocks
- no remote fetch
- no media download or import
- no site creation yet
- no navigation or shared slot creation yet unless a later phase adds it
- no destructive page deletion
- no destructive block deletion
- no resource update, move, or delete endpoints yet
- no browser session requirement
- no public unauthenticated access

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
- Phase 2A still rejects publish, site creation, media import, remote fetch, overwrite, replace, and delete operations
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

- explicit workflow transitions
- optional publish only after separate design, still explicit and permissioned

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
