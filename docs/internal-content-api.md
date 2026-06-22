# Internal Content API

## Purpose

The Internal Content API is a secure CMS API for trusted AI and operator tools. It lets those tools inspect CMS content contracts and create draft-first content through structured JSON without logging into, scraping, or automating the browser admin UI.

Phase 1 is implemented as a token-protected, JSON-only, non-public API for read-only content discovery plus draft page creation through validated content plans. It is intentionally non-destructive.

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

## Authentication And Disabled State

The API uses Bearer token authentication:

```http
Authorization: Bearer <token>
```

The token is configured with:

```text
WEBBLOCKS_CMS_INTERNAL_API_TOKEN
```

If `WEBBLOCKS_CMS_INTERNAL_API_TOKEN` is missing, the API is disabled. Disabled endpoints should return a controlled JSON response instead of falling through to raw framework errors or browser-oriented admin output.

Authentication rules:

- missing or wrong tokens return JSON `401`
- disabled API state returns a controlled JSON response
- tokens must never be printed in logs, diagnostics, support reports, tests, or documentation examples
- token comparison must use a constant-time comparison
- responses are JSON-only

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
GET /webadmin/api/pages
GET /webadmin/api/pages/{page}
GET /webadmin/api/blocks
GET /webadmin/api/blocks/{block}
```

### Content Validate / Apply API

Content validate/apply endpoints handle complete multi-step content plans:

```text
POST /webadmin/api/content/validate
POST /webadmin/api/content/apply
```

`validate` checks a complete content plan and writes nothing. `apply` validates the plan again and then creates the draft page transactionally. This is useful for AI-generated pages, templates, starter pages, and migration helpers where the CMS should avoid half-created content.

The request body may still contain a `plan` field or another structured content plan payload. The URL should remain `/content/validate` and `/content/apply`.

Both modes are needed:

- the Resource API exposes the existing CMS content model and contracts to internal tools
- the Content Validate / Apply API avoids partial writes during larger page builds

## Phase 1 Scope

### Discovery Endpoints

- `GET /webadmin/api/sites`
- `GET /webadmin/api/locales`
- `GET /webadmin/api/page-layouts`
- `GET /webadmin/api/block-types`

### Page Endpoints

- `GET /webadmin/api/pages`
- `GET /webadmin/api/pages/{page}`

### Block Endpoints

- `GET /webadmin/api/blocks`
- `GET /webadmin/api/blocks/{block}`

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
- Phase 1 rejects publish, site creation, navigation mutation, shared slot mutation, media import, remote fetch, overwrite, replace, and delete operations

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

## Future Phases

### Phase 2

- navigation menus and items
- shared slots
- assign shared slot to page slot
- header/navbar construction

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
