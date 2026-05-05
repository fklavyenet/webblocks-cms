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
- Page duplicates require target-site access, locale compatibility, unique translated target paths, and compatible Shared Slot remaps for cross-site Shared Slot usage.
- Page-linked navigation may need manual review after a move even though strict same-page navigation references are kept valid.

## Related Docs

- [Localization](localization.md)
- [Users And Permissions](users-and-permissions.md)
- [Operations](operations.md)
