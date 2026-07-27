---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/multisite
cms_title: Multisite
cms_layout: docs
cms_source_id: webblocks-cms:docs/multisite.md
---

# Multisite

WebBlocks CMS supports multiple sites within one install.

## Overview

Each site has its own content scope, domains, locales, navigation, and editorial context.

Each site also owns its own public identity fallback layer:

- public display name
- tagline
- favicon
- social image
- SEO Defaults for title, description, and keywords
- default Contact Form recipient email

## How Site Scope Works

- Content is scoped by site.
- Public routing resolves the active site from the current host.
- Public `<head>` metadata and favicon output resolve from that same current host-matched site.
- Public page titles default to `Site Label · Page Label` using the current resolved site before the current page translation label.
- Page-level SEO overrides still resolve from the current host-matched site and the currently resolved page translation; they do not fall back to another site's defaults.
- Public search modal copy also resolves the current host-matched site label and does not use install-level Project Identity.
- Admin users with site-scoped roles can work only inside their assigned sites.
- Navigation, pages, media usage, and reporting remain site-aware.

## Practical Rules

- `super_admin` can access all sites.
- `site_admin` and `editor` users must be assigned to the sites they manage.
- Site portability between installs is handled through Export / Import.
- Site duplication inside the same install is covered by the clone tooling and admin flow.
- Existing pages stay site-scoped on the normal Edit Page form. To move one page between sites inside the same install, use the dedicated `Move to another site` action.
- To create a copy of one page inside the same site or another accessible site, use the dedicated `Duplicate page` action instead of move.
- Page moves require a different target site, matching locale support, no conflicting translated paths on the target site, and compatible Shared Slot remaps when the page uses Shared Slots.
- Page duplicates require target-site access, locale compatibility, unique translated target paths, and Shared Slot-safe handling for cross-site Shared Slot usage.
- Each site may set its own timezone on the Edit Site form. Leaving it unset means the site follows the install-wide System Settings timezone, which stays the default and is what admin chrome uses. Set it when a site's business hours run on a different clock from the install — an install with sites in more than one region needs this before anything time-bound can be correct.
- Shared Slots are site-scoped and cannot be referenced across sites directly.
- Same-site duplicate keeps existing Shared Slot references.
- Cross-site duplicate remaps only compatible same-handle Shared Slots from the target site.
- Missing or incompatible target Shared Slots still block the duplicate by default.
- When the duplicate screen offers `Disable incompatible Shared Slot-backed slots on the duplicated page` and the user opts in, only those incompatible duplicated page slots are written as disabled instead of preserving an invalid cross-site Shared Slot reference.
- This duplicate fallback does not create Shared Slots automatically and does not copy Shared Slot content into page-owned blocks.
- Page-linked navigation may need manual review after a move even though strict same-page navigation references are kept valid.

## Related Docs

- [Localization](localization.md)
- [Users And Permissions](users-and-permissions.md)
- [Operations](operations.md)
