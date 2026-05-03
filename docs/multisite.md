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

## Related Docs

- [Localization](localization.md)
- [Users And Permissions](users-and-permissions.md)
- [Operations](operations.md)
