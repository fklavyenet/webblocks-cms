# AI Page Building Guide

This guide defines the safe workflow for trusted AI/operator tools that build WebBlocks CMS pages through the Internal Content API. It is generic CMS product guidance. Do not add site-specific import, sync, or scraping behavior to CMS core.

External AI/operator tools do not need local filesystem access to the CMS repository or installed package docs. Start with the live API discovery endpoint:

```text
GET /webadmin/api
```

In installed package-native sites, this guide also ships inside the Composer package at:

```text
vendor/fklavyenet/webblocks-cms/docs/ai-page-building-guide.md
```

## Purpose

Trusted AI/operator tools can inspect a CMS install, build a structured draft content plan, validate it, create a separate draft page, or replace specific page-owned slots on an existing draft page after explicit user approval. The workflow is draft-first and API-first. It does not publish content, overwrite published pages, clear Shared Slot-backed slots, fetch remote websites, or import media.

## Token Setup

Create API tokens from the CMS admin panel:

```text
System -> API Tokens
```

The plain token is shown once immediately after creation. Store it in a trusted operator secret store and never paste a real token into prompts, documentation, logs, screenshots, tickets, or release reports.

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
2. Read OpenAPI, the content contract, and examples from the live API links.
3. Build a content plan using only discovered handles and the current site/layout/locale.
4. Validate with `POST /webadmin/api/content/validate`.
5. Read validation errors and adjust the plan.
6. Ask the user for explicit approval to apply the exact final plan.
7. Only after approval, call `POST /webadmin/api/content/apply`.
8. Read the created draft page id from the apply response.
9. Produce the admin preview URL with `/webadmin/pages/{page}/preview`.
10. Leave publishing to a human workflow. The Internal Content API does not automatically publish.

## Safety Rules

- Draft-first.
- Apply only after explicit user approval.
- Do not publish.
- Do not delete pages through content apply.
- Do not overwrite existing pages or blocks except with the explicit `replace_existing_draft_page` mode.
- Do not call apply if the target path already exists unless the user explicitly approves a conflict-handling plan supported by the API.
- For existing draft replacement, include `expected_path` or `expected_updated_at` and replace only page-owned slots.
- Do not try to replace Shared Slot-backed slots; leave shared header/footer assignments intact.
- Do not fetch remote pages.
- Do not use browser automation or admin UI clicks when API discovery is available.
- Do not download or import media.
- Do not create API tokens from automation unless the user explicitly asks for token administration.
- Do not print, log, or report token values.
- Report only status codes and safe summarized response data.
- Treat `401`, `403`, and `422` JSON responses as API feedback and follow their discovery/documentation links.

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

Contact page:

```text
section -> hero + contact_form
```

Use the native `contact_form` block for contact pages after discovery confirms the handle is available. Its visible copy is translated with `title`, `content`, `submit_label`, and `success_message`; shared settings are `recipient_email`, `send_email_notification`, and `store_submissions`. The renderer emits the native CSRF-protected public form, hidden `website` honeypot, and `/contact-messages` submit endpoint.

## Bad Structures

- Do not put a full page into one `rich-text` block.
- Do not put a full page into one trusted `html` block when structured blocks can represent it.
- Do not build contact forms with Trusted HTML, raw form markup, or `mailto:` links when `contact_form` is available.
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

The preview URL is a browser/admin route. It requires an authenticated admin browser session and is not accessible with a CMS API Bearer token. If browser smoke testing lands on a login page, report that the admin browser session is missing; do not treat it as a JSON API token failure.

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
      "expected_path": "/p/contact",
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

## Real Site Revisions

For real sites, first inspect the current pages and existing drafts. If a draft already exists, preview it before proposing new work. Use `replace_existing_draft_page` only for explicit draft-safe page-owned slot replacement. Otherwise, create a new separate draft page instead of overwriting the existing draft or live published homepage.

For QuizTem-style homepage revisions, use the existing draft as reference material only unless the user explicitly approves a supported update flow. The safe default for new work is:

1. Preview the existing draft.
2. Build a better structured plan with discovered block handles.
3. Validate the plan.
4. Ask for explicit apply approval.
5. Create a new separate draft page.
6. Open `/webadmin/pages/{page}/preview` for human review.
