# WebBlocks CMS

> A modern block-based CMS

WebBlocks CMS is a Laravel application aligned with the WebBlocks UI philosophy.

## Overview

This project provides:

- a public-facing site shell
- an admin dashboard shell
- authentication flows
- reusable page, layout, block, and navigation management
- consistent Blade views and layout patterns for the WebBlocks CMS experience
- direct WebBlocks UI CDN integration

## Getting Started

Install dependencies and bootstrap the project:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Installation Modes

The repository now separates core CMS setup from optional starter/showcase installs.

- Default seed path: `php artisan db:seed`
- Result: core CMS catalogs only
- Included catalogs: page types, layout types, slot types, and block types

Optional install seeders:

- Core catalogs only:

```bash
php artisan db:seed
```

- Starter install:

```bash
php artisan db:seed --class=Database\\Seeders\\StarterInstallSeeder
```

- Showcase install:

```bash
php artisan db:seed --class=Database\\Seeders\\ShowcaseInstallSeeder
```

- Development/demo users:

```bash
php artisan db:seed --class=Database\\Seeders\\DevelopmentUserSeeder
```

Recommended local demo setup:

```bash
php artisan db:seed
php artisan db:seed --class=Database\\Seeders\\DevelopmentUserSeeder
php artisan db:seed --class=Database\\Seeders\\StarterInstallSeeder
```

The full showcase site is no longer installed by the default seed path.

## Boundary Model

### Core

- reusable CMS engine and admin infrastructure
- public rendering engine and shared neutral templates
- core schema and product-owned catalogs

### Site/Application

- install-specific branding and env values
- runtime pages, blocks, navigation, media, and contact submissions
- any custom install-specific block types

### Starter/Showcase

- optional starter content
- optional showcase content
- demo media config/import tooling

## Catalog Ownership

The following catalogs are treated as product-owned core definitions:

- `PageType`
- `LayoutType`
- `SlotType`
- system `BlockType` records

First-pass rule:

- the CMS seeds and maintains these records as core catalogs
- runtime site content should reference them, not treat them as ordinary page/content data
- system block types are read-only in the admin
- non-system block types may still be created as install-specific extensions

## Stack

- This is a Laravel application.
- WebBlocks UI assets are loaded via CDN in the layout templates.
- The application uses server-rendered Blade views.

## Application Identity

Project metadata is normalized to the CMS brand:

- Composer package: `fklavyenet/webblocks-cms`
- Application name: `WebBlocks CMS`
- Application slogan: `A modern block-based CMS`

## Navigation Items Note

- Navigation Items now use a tree editor instead of a CRUD table.
- `menu_key`, `parent_id`, and `position` define menu structure and ordering.
- Navigation Auto blocks render data from `navigation_items` by `menu_key`.
- Drag and drop reordering auto-saves immediately after drop.
- Cycle protection and a maximum depth of 3 levels are enforced.

## Contact Form Block

- `contact_form` is a reusable block type, not a special page feature.
- Public submissions are always stored in `contact_messages` before email delivery is attempted.
- Notification recipients resolve from block-level `recipient_email`, then `CONTACT_RECIPIENT_EMAIL`.
- Message statuses are `new`, `read`, `replied`, `archived`, and `spam`.
- Anti-spam protection uses a honeypot field, a minimum submit-time check, and Laravel rate limiting on the submission route.
- In local DDEV development, Mailpit is available at the DDEV mail URL and is the preferred way to verify contact notifications.
- Set `CONTACT_RECIPIENT_EMAIL` to a local inbox target, submit `/p/contact`, then confirm both the saved `contact_messages` row and the notification state in the admin detail view.
- If mail delivery fails locally, the message record remains saved and the detail view shows the notification recipient, status, and captured error text.

## Demo Media

- Demo media import remains available through `php artisan demo:import-media`.
- Demo-only source tracking is isolated from the core `assets` schema.
- Starter/showcase media refresh logic is intentionally separate from core migrations.

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
