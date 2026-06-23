# AI Page Building Guide

This guide defines the safe workflow for trusted AI/operator tools that build WebBlocks CMS pages through the Internal Content API. It is generic CMS product guidance. Do not add site-specific import, sync, or scraping behavior to CMS core.

## Purpose

Trusted AI/operator tools can inspect a CMS install, build a structured draft content plan, validate it, and create a separate draft page after explicit user approval. The workflow is draft-first and API-first. It does not publish content, overwrite published pages, delete content, fetch remote websites, or import media.

## Token Setup

Create API tokens from the CMS admin panel:

```text
System -> API Tokens
```

The plain token is shown once immediately after creation. Store it in the local operator environment and never paste a real token into prompts, documentation, logs, screenshots, tickets, or release reports.

Example local `.env` values:

```dotenv
WEBBLOCKS_CMS_URL=https://example.com
WEBBLOCKS_CMS_API_TOKEN=...
```

API requests use:

```http
Authorization: Bearer ...
Accept: application/json
```

## First Discovery Calls

Start with read-only discovery. These endpoints are token-protected and live under `/webadmin/api`:

```text
GET /webadmin/api/sites
GET /webadmin/api/locales
GET /webadmin/api/page-layouts
GET /webadmin/api/block-types
GET /webadmin/api/content-contract
GET /webadmin/api/navigation-menus
GET /webadmin/api/shared-slots
GET /webadmin/api/pages
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

## Safe Workflow

1. Run read-only discovery.
2. Build a content plan using only discovered handles and the current site/layout/locale.
3. Validate with `POST /webadmin/api/content/validate`.
4. Read validation errors and adjust the plan.
5. Ask the user for explicit approval to apply the exact final plan.
6. Only after approval, call `POST /webadmin/api/content/apply`.
7. Read the created draft page id from the apply response.
8. Produce the admin preview URL with `/webadmin/pages/{page}/preview`.
9. Leave publishing to a human workflow. The Internal Content API does not automatically publish.

## Safety Rules

- Draft-first.
- Apply only after explicit user approval.
- Do not publish.
- Do not delete content.
- Do not overwrite existing pages or blocks.
- Do not call apply if the target path already exists unless the user explicitly approves a conflict-handling plan supported by the API.
- Do not fetch remote pages.
- Do not scrape with browser automation when API discovery is available.
- Do not download or import media.
- Do not create API tokens from automation unless the user explicitly asks for token administration.
- Do not print, log, or report token values.
- Report only status codes and safe summarized response data.

## Good Structures

Prefer structured blocks over a single large content blob.

Marketing homepage:

```text
section -> container -> hero
section -> container -> grid -> card -> card_body
section -> container -> cta
```

Header/navbar:

```text
shared_slot header
sticky-navbar -> container(flow:none) -> cluster -> navbar-brand + cluster -> navbar-navigation + header-actions
```

For most public pages, place wide promo blocks such as `hero` and `cta` inside `section -> container`. Direct full-width `hero` or `cta` blocks under `main` should be intentional edge-to-edge design choices, not the default.

## Bad Structures

- Do not put a full page into one `rich-text` block.
- Do not put a full page into one trusted `html` block when structured blocks can represent it.
- Do not guess handles.
- Do not overwrite published content.
- Do not mutate an existing live page when a new separate draft page is safer.
- Do not paste tokens into prompts or reports.

## Minimal Draft Plan Example

This example assumes discovery confirmed `section`, `container`, `hero`, `grid`, `card`, `card_body`, `plain_text`, `button_link`, and `cta`.

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
                  "type": "hero",
                  "translations": {
                    "title": "Build useful pages faster",
                    "subtitle": "A structured CMS workflow for practical content teams.",
                    "content": "Create focused draft pages from reusable blocks, then review them safely before publishing."
                  },
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

## Real Site Revisions

For real sites, first inspect the current pages and existing drafts. If a draft already exists, preview it before proposing new work. When there is no draft update endpoint, create a new separate draft page instead of overwriting the existing draft or live published homepage.

For QuizTem-style homepage revisions, use the existing draft as reference material only unless the user explicitly approves a supported update flow. The safe default is:

1. Preview the existing draft.
2. Build a better structured plan with discovered block handles.
3. Validate the plan.
4. Ask for explicit apply approval.
5. Create a new separate draft page.
6. Open `/webadmin/pages/{page}/preview` for human review.
