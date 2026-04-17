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

## License

WebBlocks CMS is open-sourced software licensed under the MIT license.

## Trademark

"WebBlocks CMS" and related logos are the property of Fklavyenet.

Fklavyenet operates https://fklavye.net.

You may use, modify, and distribute the code under the MIT license.
However, you may not use the name "WebBlocks CMS" or its logos for derived products without permission.

If you fork or redistribute this project, you must remove or replace all branding.
