# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel-based, block-driven CMS for managing sites, pages, media, navigation, and editorial publishing from one admin interface. It includes install-level administration for users, updates, backups, site transfer tools, and system settings.

## Feature Summary

- block-based page building with reusable layouts, slots, and blocks
- multisite and locale-aware page management
- install-level user management with `super_admin`, `site_admin`, and `editor` roles
- editorial workflow for pages with review and publishing states
- page revisions and in-place restore
- media library and site-scoped navigation management
- install wizard for first-run setup
- system updates, backups, and site export/import tools

## Installation

For a fresh install, first get the source code locally:

```bash
git clone git@github.com:fklavyenet/webblocks-cms.git
cd webblocks-cms
```

If you already created an empty target directory, use `git clone git@github.com:fklavyenet/webblocks-cms.git .` instead. After the source code is present locally, continue with one of the install paths below.

### DDEV Quick Start

```bash
ddev config --project-type=laravel --docroot=public --project-name=<your-project-name>
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
```

For a fresh install with the browser flow, open `/install` after the source code, dependencies, and local environment are in place. The install wizard guides database setup, environment creation, core install steps, and first super admin creation.

Typical local URLs:

- installer: `/install`
- public site: `/`
- admin: `/admin`

### Manual CLI Install

```bash
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
ddev artisan db:seed
ddev artisan storage:link
```

If you are not using DDEV, the equivalent Laravel dev server flow is:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Then open:

- installer: `http://127.0.0.1:8000/install`
- public site: `http://127.0.0.1:8000/`
- admin: `http://127.0.0.1:8000/admin`

See `docs/installation.md` for the complete install guide.

## Quick Start

1. Install the CMS with the browser wizard or the CLI flow.
2. Sign in to `/admin`.
3. Create or edit a site if your install uses more than one site.
4. Create a page. New pages start as `draft`.
5. Build page structure with `Section`, `Container`, `Cluster`, or `Grid`, then add `Header`, `Plain Text`, `Rich Text`, `Button Link`, `Card`, `Stat Card`, or `Breadcrumb` blocks inside that layout tree. `Rich Text` is for safe body copy formatting; keep headings, layouts, buttons, media, tables, and raw HTML composition as dedicated first-class blocks or features. Rich Text is edited visually in the admin through a small dependency-free safe HTML editor with bold, italic, inline code, links, paragraphs, and simple lists. Stored Rich Text content is a restricted safe HTML fragment, and public output still renders through the shipped WebBlocks UI `wb-rich-text` primitive using `wb-rich-text wb-rich-text-readable`. Selected description fields still support safe inline code formatting with backticks. Use `Breadcrumb` for header and context bars, and use `navigation-auto` only for actual menus. `Public Shell` is page-level and controls the outer public shell. Slot wrappers are resolved automatically from the page shell and slot name, so blocks stay focused on content instead of shell markup. For docs layouts, set the page `Public Shell` to `Docs`; header, sidebar, and main slots then map to the docs wrappers automatically. For card actions, prefer `Card > Cluster > Button Link`; the legacy single card action fields remain available as a fallback.
6. For a docs-style context bar, add `Breadcrumb` and `Header Actions` to the `Header` slot. `Header Actions` renders system-owned theme utilities such as color mode and accent controls without requiring raw HTML blocks.
7. For a docs-style sidebar, add a `Sidebar` slot and set the page `Public Shell` to `Docs`, then add `Sidebar Brand`, `Sidebar Navigation`, and `Sidebar Footer` as top-level sidebar blocks. Inside `Sidebar Navigation`, add `Sidebar Nav Item` and optional `Sidebar Nav Group` blocks.
8. Publish the page as a `site_admin` or `super_admin`.
9. Open the public URL or preview link to confirm the live result.

On the Edit Page screen, page settings and slot structure are managed separately, and slot additions are available from a compact `Add Slot` dropdown.

In the admin slot editor, the Edit Slot Blocks list stays structure-focused as a compact one-row-per-block table with block type, a single primary summary, a dedicated children-count column, status, and actions. The Block Picker supports search, category filtering, and sortable catalog-style rows so larger block catalogs remain manageable. On narrow screens the table remains one-row-per-block and scrolls horizontally instead of collapsing labels into vertical letter stacks. Full content should be edited in the block edit modal or block edit page instead of being previewed in the slot list.

See `docs/getting-started.md` for the first-use workflow.

## Documentation

- See the full documentation in the `docs/` directory:
- [Installation](docs/installation.md)
- [Getting Started](docs/getting-started.md)
- [Core Concepts](docs/core-concepts.md)
- [Users And Permissions](docs/users-and-permissions.md)
- [Editorial Workflow](docs/editorial-workflow.md)
- [Revisions](docs/revisions.md)
- [Operations](docs/operations.md)
- [Updates](docs/updates.md)
- [Multisite](docs/multisite.md)
- [Localization](docs/localization.md)
- [Public Assets](docs/public-assets.md)
- [Renderer Contracts](docs/block-ui-renderer-contract.md)
- [Development Workflow](DEVELOPMENT.md)

## Project Layer

- Project-specific console commands belong under `project/`.
- `webblocks:sync-ui-docs-getting-started` idempotently syncs the existing WebBlocks UI docs `Getting Started` page into the CMS-managed `main` slot content for that page without duplicating its generated blocks.

## Developer Notes

- Refresh the product block catalog on an existing install with `ddev artisan db:seed --class=BlockTypeSeeder`. The seeder safely upserts product-owned block types such as `Rich Text` without duplicating rows.
- In the admin layout, the mobile or narrow sidebar uses the standard WebBlocks UI sidebar contract, including a shell-local `data-wb-sidebar-backdrop`, so outside clicks close the sidebar without inline Blade scripts.
- Public rendering ownership is split intentionally: page controls the outer shell (`default` or `docs`), slot name controls the public wrapper semantics, and blocks render content inside those slot wrappers.
- `default` uses standard semantic wrappers such as `header`, `main`, `aside`, and `footer`. `docs` automatically maps header, sidebar, and main slots to the docs navbar, sidebar, and main wrappers.

## Build Artifacts

- The repository keeps `dist/` referenced as release-output space in the GitHub release workflow, while application layouts and docs also reference the WebBlocks UI package `dist` bundles loaded from CDN or published package paths.
- The local repo `dist/` path is ignored by git and is not part of normal application source.

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
