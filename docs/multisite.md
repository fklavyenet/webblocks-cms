# Multisite

WebBlocks CMS supports multiple sites within one install.

## Overview

Each site has its own content scope, domains, locales, navigation, and editorial context.

## How Site Scope Works

- Content is scoped by site.
- Public routing resolves the active site from the current host.
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
