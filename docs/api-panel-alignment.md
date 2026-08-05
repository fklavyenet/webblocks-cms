---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/api-panel-alignment
cms_title: API and Panel Alignment
cms_layout: docs
cms_source_id: webblocks-cms:docs/api-panel-alignment.md
---

# API and Panel Alignment

## Overview

The browser admin at `/webadmin` and the [Internal Content API](internal-content-api.md) are two front doors onto the same CMS data, but they were not built at the same time and they do not cover the same ground. The panel is the complete operator surface. The API is a deliberately narrower surface for trusted AI and operator tools.

This document is the authoritative map of where the two agree, where the API covers less, and where the gap is a deliberate boundary rather than unfinished work. It exists so that:

- an AI or operator tool can find out what it cannot do before it tries;
- a reviewer can tell an intentional boundary from a missing endpoint;
- roadmap work has a single list to close against.

It is a status record, not a specification. When an endpoint ships, update the row here in the same commit.

## How to Read This

Every row carries one status:

| Status | Meaning |
| --- | --- |
| Aligned | The API can accomplish what the panel accomplishes. Shape may differ. |
| Partial | An endpoint exists but covers fewer fields or fewer operations than the panel. |
| Missing | No API path exists. Panel-only by omission, not by design. |
| Panel-only by design | Deliberately excluded. The reason is recorded in the row. |

"Panel-only by design" is not a synonym for "hard". It means excluding it is the safety posture described in the Boundaries section of [Internal Content API](internal-content-api.md): no automatic publish, no crawling or remote fetch, no arbitrary import/export replacement, and no privilege escalation through a token.

## API Surfaces

The API is not a single prefix. A tool integrating with the CMS talks to two:

| Prefix | Auth | Scope |
| --- | --- | --- |
| `/webadmin/api` | `internal-api.token` plus a per-route capability | Everything |
| `/admin-api` | `internal-api.token` plus a per-route capability | Site and domain records, legacy alias |

The split is historical rather than principled. `/admin-api` predates the capability model, and its routes checked nothing beyond token validity until `domains.write` and `domains.delete` were introduced — any valid token could add or remove a domain. The domain routes now also live under `/webadmin/api`, which is where new integrations should point; the legacy prefix is kept working for existing provisioning tools.

Capabilities are defined in `CmsApiTokenCapabilities`. A gap in this document is sometimes a missing capability as much as a missing route.

## Pages

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| List and read pages | Yes | `GET /pages`, `GET /pages/{page}` | Aligned |
| Create a draft page | Yes | `POST /content/apply` (`create_draft_page`) | Aligned |
| Replace slot content on a draft page | Yes | `POST /content/apply` (`replace_existing_draft_page`) | Aligned |
| Staged updates for published pages | Yes | `POST /content/apply` (staged-update modes) | Aligned |
| Publish a page | Yes | `POST /pages/{page}/publish` | Aligned |
| Change the public shell layout | Yes | `PATCH /pages/{page}/layout` | Aligned |
| Sync layout slots | Yes | `POST /pages/{page}/sync-layout-slots` | Aligned |
| Delete one page | Yes | `DELETE /pages/{page}` | Aligned |
| Page CSS and JS assets | Yes | `/pages/{page}/assets/*` | Aligned |
| Rename a page, or change its slug or path | Yes | `PATCH /pages/{page}/translations/{translation}` | Aligned |
| Page translations: add a locale, edit name, slug, path, SEO, Open Graph | Yes | `/pages/{page}/translations/*` | Aligned |
| Preview a page | Yes | `GET /pages/{page}/render` | Aligned |
| **Page revisions: list and restore** | Yes | None | **Missing** |
| Add, remove, or reorder a page slot | Yes | Only `sync-layout-slots` and slot source | Partial |
| Clear every block in a page slot | Yes | Shared Slots have `clear`; pages do not | Partial |
| Duplicate a page | Yes | None | Missing |
| Move a page to another site | Yes | None | Missing |
| Import a page from JSON | Yes | None | Missing |
| HTML-to-block page converter | Yes | None | Missing |
| Bulk delete pages | Yes | Single delete only | Partial |
| Workflow transitions other than publish | Yes | None | Missing |

The two that used to dominate this list are closed.

**Page identity and page translations.** `create_draft_page` writes `name`, `slug`, and `path` onto one page translation row for one locale, and until the Page Translation API landed nothing could touch that row afterwards: the replace and staged-update modes normalize `page` to `null` and only handle slot content. A page created at the wrong path could only be fixed by deleting and recreating it, no page could gain a second locale, and page-level SEO — `seo_title`, `seo_description`, `seo_keywords`, `og_title`, `og_description`, `og_image_media_id`, all of which live on that row — was unwritable and missing from read payloads too.

`/pages/{page}/translations/*` now covers all of it, and because `Page` title and slug read through to the default translation, renaming that translation renames the page. See [Localization](localization.md) for why these fields belong on the translation row and [Internal Content API](internal-content-api.md) for the write contract.

Site-level SEO defaults are still unreachable, for a separate reason; see Sites below.

## Blocks

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| List and read blocks | Yes | `GET /blocks`, `GET /blocks/{block}` | Aligned |
| Create a block in a page slot | Yes | `POST /pages/{page}/slots/{slot}/blocks` | Aligned |
| Update block content and settings | Yes | `PATCH /blocks/{block}` | Aligned |
| Reorder blocks | Yes | `PATCH /pages/{page}/slots/{slot}/blocks/reorder` | Aligned |
| Delete a block | Yes | `DELETE /pages/{page}/slots/{slot}/blocks/{block}` | Aligned |
| Author `html` blocks | Yes | Rejected with `block_type_not_api_writable` | Panel-only by design — raw markup stays human-reviewed |

Blocks are the best-aligned area of the CMS. Settings writes are additionally constrained by `BlockSettingsPatchPolicy`, which is a guard rather than a gap.

## Shared Slots

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| List, read, create | Yes | `GET`/`POST /shared-slots` | Aligned |
| Block create, reorder, delete, clear | Yes | `/shared-slots/{sharedSlot}/blocks/*` | Aligned |
| Publish Shared Slot blocks | Yes | `POST /shared-slots/{sharedSlot}/publish-blocks` | Aligned |
| Assign a Shared Slot to a page slot | Yes | `POST /pages/{page}/slots/{slot}/shared-slot` | Aligned |
| Update a Shared Slot (label, handle, slot type, layout, active status) | Yes | `PATCH /shared-slots/{sharedSlot}` | Aligned |
| Delete a Shared Slot | Yes | `DELETE /shared-slots/{sharedSlot}` | Aligned |
| Move a Shared Slot to another site | Yes | Rejected with `unsupported_shared_slot_fields` | Panel-only by design — a cross-site move, not a rename |
| Shared Slot revisions: list, show, restore | Yes | None | Missing |

Deletion requires the destructive `shared-slots.delete` capability and refuses to remove a Shared Slot any page slot still references, listing the referencing slots so a tool can detach them first.

## Media

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| List, read, upload, fetch remote | Yes | `/media`, `/media/fetch` | Aligned |
| Update descriptive metadata | Yes | `PATCH /media/{media}` | Aligned |
| Replace, move, delete | Yes | `/media/{media}/replace`, `/move`, `DELETE` | Aligned |
| Create a media folder | Yes | `POST /media/folders` | Aligned |
| Regenerate image transforms | Yes | None | Missing |
| Bulk delete | Yes | Single delete only | Partial |
| Change storage fields, binary, or folder via `PATCH` | Yes | Rejected with `unsupported_media_update_fields` | Panel-only by design — metadata writes must not move bytes |

`POST /media/folders` refuses a name that already exists under the same parent and returns the existing folder, so a retrying tool reuses it instead of piling up duplicates.

## Navigation

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| List and read menus | Yes | `/navigation-menus` | Aligned |
| Create a menu | Yes | `POST /navigation-menus` | Aligned |
| Item create, update, reorder, delete | Yes | `/navigation-menus/{menu}/items/*` | Aligned |
| Delete a whole menu | Yes | None | Missing |
| Delete an item that has children | Yes | Rejected until children are handled | Panel-only by design — no silent cascade |

## Engagement and Messages

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| Read comments and ratings | Yes | `/engagement/comments`, `/engagement/ratings` | Aligned |
| Moderate comment status | Yes | `PATCH /engagement/comments/{comment}` | Aligned |
| Delete a comment | Yes | None | Missing |
| **Contact form messages: list, read, status, delete** | Yes | None | **Missing** |

Contact messages have no API representation at all. A tool can build a contact form through the API but can neither read its submissions nor learn where they are delivered — see Sites.

## Sites and Configuration

The panel site form writes more than twenty fields. The API covers them through narrow single-purpose endpoints: `branding`, `head`, `timezone`, `public-theme`, `seo`, `contact-recipient`, and `locales`.

| Field or capability | Panel | API | Status |
| --- | --- | --- | --- |
| Display name, tagline, favicon, social image, brand palette, fonts | Yes | `PATCH /sites/{site}/branding` | Aligned |
| Custom head HTML | Yes | `PATCH /sites/{site}/head` | Aligned |
| Timezone | Yes | `PATCH /sites/{site}/timezone` | Aligned |
| Public theme preset | Yes | `POST /sites/{site}/public-theme` | Aligned |
| Site CSS and JS override files | Yes | `/sites/{site}/assets/{type}` | Aligned |
| Site SEO defaults (`seo_title`, `seo_description`, `seo_keywords`) | Yes | `PATCH /sites/{site}/seo` | Aligned |
| Contact recipient email | Yes | `PATCH /sites/{site}/contact-recipient` | Aligned |
| Locale assignment (`locale_ids`) | Yes | `PUT /sites/{site}/locales` | Aligned — stricter: refuses to detach a locale with page translations |
| Site name and handle | Yes | None | Missing |
| Primary-site flag | Yes | None | Missing |
| Site variables | Yes | None | Missing |
| Create or delete a site | Yes | None | Panel-only by design — `site_create` is a forbidden plan key |
| Clone a site | Yes | None | Panel-only by design — whole-site duplication is operator-owned |
| Promote a site | Yes | None | Panel-only by design — see [Operations](operations.md) |
| Site export and import | Yes | None | Panel-only by design — arbitrary import replacement is out of scope |
| Domains: list, add, update, set primary, remove, status | Yes | `/webadmin/api/sites/{site}/domains/*` | Aligned |

What remains missing here is site identity — name, handle, primary flag — and site variables. Those are closer to provisioning than to content, and no tool has yet needed them.

## Schema and Definitions

Everything in this group is readable and none of it is writable.

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| Page layouts: create, update, slot management | Yes | `GET /page-layouts` only | Partial — read-only |
| Block types: create, update, delete | Yes | `GET /block-types` only | Partial — read-only |
| Slot types | Read-only list | None | Missing — not even readable |
| Locales: create, update, enable, disable | Yes | `POST /locales`, `PATCH /locales/{locale}` | Aligned |
| Locales: delete | Yes | None | Missing |
| Icon catalog: read | Yes | `GET /icon-catalog` | Aligned |
| Icon catalog: sync and activation | Yes | None | Missing |

Read-only schema access is defensible: block types and layouts are structural contracts, and letting a token invent them widens the blast radius of every later content write. It is recorded as Partial rather than by-design because no such decision is written down anywhere.

## Users, System, and Operations

| Capability | Panel | API | Status |
| --- | --- | --- | --- |
| User management | Yes | None | Panel-only by design — no privilege escalation through a token |
| API token management | Yes | None | Panel-only by design — a token must not mint tokens |
| System settings and mail test | Yes | None | Panel-only by design — install-wide configuration is operator-owned |
| Backups: create, restore, download | Yes | None as endpoints. `backups.create` only arms the `create_restore_point` flag on `POST /content/apply` | Panel-only by design |
| Run a system update | Yes | `GET /admin-render/system-updates` returns a read-only snapshot | Panel-only by design — see [Updates](updates.md) |
| Rebuild the search index | Yes | None | Missing |
| Visitor reports | Yes | None | Missing |
| Plugins: catalog browse and install, enable, disable, setup, uninstall, ZIP upload | Yes | `/plugins/*` | Aligned |
| Plugins: update an installed plugin from the catalog | Yes | None | Missing |
| Plugins: read one plugin's detail | Yes | `index` only | Partial |

## Cross-Cutting: Unknown Plan Keys

Until this was fixed, every gap in this document was silent from the caller's side.

`POST /content/validate` and `POST /content/apply` rejected a fixed list of forbidden keys — publish and scheduling keys, site creation, remote fetch, and destructive verbs — but nothing merely unrecognized. Plan normalization read the keys it knew and ignored the rest, so a plan carrying `page.seo_title` returned `ok: true` and a `201` having written none of it. A tool reported success; nothing had happened. Reading the page back did not reveal it either, because the fields the API cannot write are also absent from its read payloads.

Unrecognized keys are now rejected with `422` and the stable code `unsupported_plan_fields`, and the error path names each rejected field. The accepted key set is scoped to the plan `mode`: `replace_slots` is meaningful while replacing a page and rejected while creating one.

This does not close any gap below. It makes them discoverable, which is the prerequisite for a tool being able to fall back to the panel instead of reporting a write that never happened.

## Roadmap

Ordered by how much each unblocks, not by effort.

**Tier 1 — complete**

1. ~~Reject unrecognized plan keys with `422`.~~ See Unknown Plan Keys above.
2. ~~Page translation write endpoint.~~ `/pages/{page}/translations/*` writes name, slug, path, SEO and Open Graph, and reads them back.
3. ~~Page identity update.~~ Delivered with 2: title, slug and path are translation fields, and the default-locale translation is the page's own identity.
4. ~~Shared Slot update and delete.~~ `PATCH` and `DELETE /shared-slots/{sharedSlot}`, the latter behind the new `shared-slots.delete` capability.

**Tier 2 — complete**

5. ~~Extend site settings: SEO defaults, `contact_recipient_email`, `locale_ids`.~~ `/sites/{site}/seo`, `/contact-recipient`, and `/locales`.
6. ~~Page preview or render snapshot.~~ `GET /pages/{page}/render`, with `format=html` and per-locale rendering.
7. ~~Media folder creation.~~ `GET`/`POST /media/folders`.
8. ~~Capability-gate the domain routes and move them under `/webadmin/api`.~~ Done, and `update` and `set primary` came with it.

**What is left**

Everything still marked Missing above is second-order: page duplication and site moves, revisions, bulk operations, the HTML-to-block converter, comment deletion, contact messages, schema authoring, search reindex, and visitor reports. None of them block a tool from building, checking, correcting and publishing a page, which is what Tiers 1 and 2 were about. Pick from them by demand rather than by working down the list.

**Tier 3 — deliberate boundaries**

Users, tokens, system settings, backups, updates, site create and delete, clone, promote, and transfer stay panel-only. They are listed here so that "not in the API" is a recorded decision rather than an unexamined absence.
