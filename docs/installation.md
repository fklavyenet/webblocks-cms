# Installation

## Overview

WebBlocks CMS supports a browser-based install wizard for fresh installs, a manual Laravel CLI install path, and an optional DDEV local setup flow.

For a fresh install, start by getting the WebBlocks CMS source code onto your machine. Run Composer, create `.env`, use Artisan, start DDEV, and open the browser install wizard only after the source code exists locally.

An install is considered complete when the application has a working CMS baseline:

- an app key exists
- the database is reachable
- required tables exist
- core seed data exists
- the first active `super_admin` exists
- an install completion marker is stored in `system_settings`

## Get the Source Code

Before you run any install commands, make sure the WebBlocks CMS repository is present locally.

Clone into a new directory:

```bash
git clone git@github.com:fklavyenet/webblocks-cms.git
cd webblocks-cms
```

Clone into an already-created empty directory:

```bash
git clone git@github.com:fklavyenet/webblocks-cms.git .
```

After the source code is present locally, continue with one of the fresh install paths below.

## Browser Install Wizard

Use the browser wizard for a fresh install.

After the source code is present locally, start with:

```bash
composer install
cp .env.example .env
php artisan serve
```

Then open `http://127.0.0.1:8000/install`.

The wizard covers:

- environment readiness checks
- database configuration and connection validation
- core CMS installation
- first `super_admin` creation
- install locking after completion

Notes:

- the installer is for fresh installs
- if setup is incomplete, the wizard can be reopened and resumed safely
- after completion, install routes are locked and normal auth/admin flow takes over
- the installer writes the selected database configuration into `.env`

## Manual CLI Installation

Use the CLI flow when you prefer a standard Laravel setup path for a fresh install.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Notes:

- `php artisan db:seed` installs the core CMS catalogs and records the current app version as the installed version for a fresh install
- `php artisan storage:link` is required if public file serving should use `storage/app/public`
- runtime directories under `storage/framework`, `storage/logs`, and `bootstrap/cache` are created automatically on first run

## Optional DDEV Local Install

After the source code is present locally:

```bash
ddev config --project-type=laravel --docroot=public --project-name=<your-project-name>
ddev start
ddev composer install
cp .env.example .env
ddev artisan key:generate
```

Notes:

- `ddev config --project-type=laravel --docroot=public --project-name=<your-project-name>` is required on a fresh clone to create `.ddev/config.yaml`
- without `.ddev/config.yaml`, `ddev start` fails with a `no project found` error

Then open:

- public site: `https://<your-project>.ddev.site`
- admin: `https://<your-project>.ddev.site/admin`
- installer on a fresh install: `https://<your-project>.ddev.site/install`

Complete the fresh install in the browser wizard after those setup steps are done.

## Accessing the Install Wizard

- fresh installs automatically redirect to `/install`
- you can also open the wizard manually at `/install`
- you can open `/install/core` to jump directly to the core install step when earlier requirements are already satisfied
- the wizard may advance steps automatically as requirements are completed
- opening `/` and `/install` in multiple browser tabs can show different wizard steps; this is expected because the installer tracks progress and routes accordingly

## First Super Admin Creation

The first `super_admin` is required for a completed install.

- in the browser wizard, create the first admin during the final setup step
- in a manual install, ensure at least one active `super_admin` account exists before considering the CMS fully installed

`super_admin` is the install-level role that can access Users, sites, locales, settings, updates, backups, export/import, and all site content.

## Common Setup Notes

- the installer is locked after completion
- `/admin` is the canonical admin entry point
- `/admin/dashboard` redirects to `/admin`
- new pages start in `draft`
- if install-level features such as revisions, backups, or updates report missing tables, run `php artisan migrate`

## Post-Install Next Steps

1. Sign in to `/admin`.
2. Review your site and locale configuration.
3. Create or edit a site if needed.
4. Create your first page.
5. Add media, navigation, and blocks.
6. Publish content through the editorial workflow.
