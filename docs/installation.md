---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/installation
cms_title: Installation
cms_layout: docs
cms_source_id: webblocks-cms:docs/installation.md
---

# Installation

## Overview

WebBlocks CMS supports a package-consumer install flow for fresh Laravel applications, a browser-based install wizard for fresh maintenance-repo installs, and a manual Laravel CLI install path.

For a fresh install, start by getting the WebBlocks CMS source code onto your machine. Run Composer, create `.env`, use Artisan, and open the browser install wizard only after the source code exists locally.

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
git clone https://github.com/fklavyenet/webblocks-cms.git
cd webblocks-cms
git remote set-url --push origin DISABLED
```

Clone into an already-created empty directory:

```bash
git clone https://github.com/fklavyenet/webblocks-cms.git .
git remote set-url --push origin DISABLED
```

After the source code is present locally, continue with one of the fresh install paths below.

WebBlocks CMS installations are update consumers only. They may fetch, pull, or download CMS updates, but they must not push commits or tags back to the canonical CMS upstream. For existing local installation clones, run `git remote set-url --push origin DISABLED` once in the installation working copy.

## Package Consumer Install

Use this flow when WebBlocks CMS is installed into a fresh Laravel application through Composer.

```bash
composer require fklavyenet/webblocks-cms
php artisan webblocks:install --name="Admin User" --email="admin@example.com" --password="secret-password"
```

Supported options:

- `--name=` first super admin display name
- `--email=` first super admin email address
- `--password=` first super admin password
- `--site-name=` default site name
- `--site-handle=` default site handle
- `--repair-partial` rename empty partial CMS tables before fresh-install migrations
- `--skip-starter-content` publish an empty home page instead of the shipped starter page
- `--force` overwrite package-owned CMS assets or published config files when needed

What `webblocks:install` does:

- publishes `config/webblocks-cms.php` when the host app does not already have it
- removes the untouched fresh Laravel welcome route from `routes/web.php` when it is safe, with a timestamped backup first, so CMS public routes can serve `/`
- patches `app/Models/User.php` with `WebBlocks\Cms\Auth\Concerns\HasWebBlocksCmsAccess`
- creates a timestamped backup before modifying `User.php`
- skips patching when the trait is already present
- fails clearly if `User.php` is not a recognizable `App\Models\User extends Authenticatable` class
- runs the package fresh-install migration path for clean consumer installs
- detects partial CMS schemas before running fresh-install migrations and reports existing CMS tables, row counts, related migration rows, and known foreign key conflicts
- repairs empty partial CMS schemas only when `--repair-partial` is provided, by renaming empty CMS tables with a timestamped `_before_cms_install_...` suffix before continuing
- refuses automatic repair when any partial CMS table contains rows
- skips rerunning that fresh schema when CMS tables already exist
- creates Laravel support tables without running host application migrations, currently covering CMS password reset tokens plus `sessions`, `cache`, and `cache_locks` when those database-backed drivers are configured
- does not run the host application's normal Laravel migration set as part of package install, so it avoids conflicts with the already-created CMS-compatible `users` table
- prepares the `backups` filesystem disk root used by Backup / Restore
- installs package-owned CMS assets into `public/cms`
- creates `public/storage` when it is missing and the environment allows it
- seeds locales, sites, slot types, page layouts, and core block types idempotently
- seeds the complete WebBlocks UI icon catalog from the manifest shipped with the package, so icon fields work with no outbound network and no manual sync
- provisions the published home page served at `/`, including its layout slots
- fills that home page with the shipped starter content, unless it is turned off
- records the installed version and install completion marker in `system_settings`
- creates the first active `super_admin` only when one does not already exist

Package auth is Laravel-native and does not require Breeze, Jetstream, Laravel UI, or Fortify. After install, sign in at `/webadmin/login` when CMS package auth routes are active. CMS-owned auth views and admin guest redirects use package route names such as `webblocks.auth.login` and `webblocks.auth.logout`, so a host product can keep its own global `login` route, for example `/quiztem/login`, without stealing CMS form actions or `/webadmin` redirects.

For the current `v1.32.x` package-consumer boundary, the host application's `App\Models\User` remains the auth model and install-time patch target.

### Partial Install Recovery

If `webblocks:install` stops after a failed or interrupted prior run, rerun it without repair first and read the partial-install diagnostic. Empty CMS tables can be moved aside explicitly:

```bash
php artisan webblocks:install --repair-partial --name="Admin User" --email="admin@example.com" --password="secret-password"
```

The repair mode only renames empty CMS-owned candidate tables. It does not drop tables, does not alter non-empty tables automatically, and does not assume CMS owns the host application.

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

- `php artisan db:seed` installs the core CMS catalogs, provisions the home page with its starter content, and records the current app version as the installed version for a fresh install
- `php artisan storage:link` is required if public file serving should use `storage/app/public`
- runtime directories under `storage/framework`, `storage/logs`, and `bootstrap/cache` are created automatically on first run
- Backup / Restore stores archives on the `backups` filesystem disk, defaulting to `storage/app/backups`. The PHP runtime user should own that directory or share a deployment group with read/write access; avoid broad `777` modes.

## Native Local Install

For a fresh Laravel project running with locally installed PHP and Composer:

```bash
composer require fklavyenet/webblocks-cms
php artisan webblocks:install --name="Admin User" --email="admin@example.com" --password="secret-password"
```

Then open:

- public site: `/`
- admin login: `/webadmin/login`
- admin: `/webadmin`

After the source code is present locally:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Notes:

- trusted local development should use `.test` domains and HTTPS, with `https://webblocks-cms.test` as the canonical CMS development URL
- `php artisan serve` remains useful for quick CLI-only checks, but trusted browser workflows should use the native Nginx/PHP-FPM setup documented in `docs/native-local-development.md`
- local contact form email notifications should use a local SMTP catcher or trusted SMTP test account; the usual local SMTP values depend on the installed tool
- Contact Form notification recipients resolve in this order: block-level `recipient_email`, current site's default contact recipient, `CONTACT_RECIPIENT_EMAIL`, then `MAIL_FROM_ADDRESS` as the last safe fallback
- contact submissions are stored independently from notification delivery, so a public `Message sent` response confirms storage success even if admin later shows notification `Failed`, `Skipped`, or `Not configured`
- `MAIL_MAILER=log`, `MAIL_MAILER=array`, and `MAIL_MAILER=null` are not real outbound delivery and are shown as not configured for Contact Message notification
- Contact Form blocks render a hidden wrapper (shipped `wb-sr-only` class) with `inert`, `aria-hidden="true"`, a renderer-generated `form_check_{token}` field, `tabindex="-1"`, and `autocomplete="off"`; when that generated check field is filled, the server returns the same generic success redirect and does not store a Contact Message or attempt notification
- submissions that pass the generated check field can still be classified as `spam` by conservative stored signals such as commercial outreach language, link density, repeated same-IP submissions, or a free-mail sales pitch with a generic subject; this status is durable admin classification and is separate from email notification state
- when notification delivery fails, admins can inspect the saved message under `Admin -> Contact Messages` to see the compact failure state in the list and the stored failure detail on the message detail screen

Then open:

- public site: `https://webblocks-cms.test`
- admin: `https://webblocks-cms.test/webadmin`
- installer on a fresh install: `https://webblocks-cms.test/install`

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
- in the package-consumer CLI flow, provide `--name`, `--email`, and `--password` to `webblocks:install`
- in a manual install, ensure at least one active `super_admin` account exists before considering the CMS fully installed

`super_admin` is the install-level role that can access Users, sites, locales, settings, updates, backups, export/import, and all site content.

## Starter Content

A fresh install publishes a home page at `/` and fills it with starter content: a brand lockup with the product mark beside the heading, a short feature grid, and a closing call to action. These are ordinary blocks, not a hard-coded welcome screen, so the first page a new admin opens in the editor is a working page they can rewrite, reorder, or delete.

The logo is imported into the site's own Media Library at install time and bound to the block like any other image, so it is served from the site's own origin and the operator can replace or delete it in the admin. It is never hot-linked from a remote host: that would make every public visitor issue a third-party request and tie the customer's home page to another host's uptime. An image that cannot be imported — read-only disk, missing file — leaves the block without media instead of failing the install.

Rules that make this safe to ship on every install path:

- starter content is written only into a page that has no blocks at all, so it can never overwrite existing content
- it is install-time only; System Update never adds or restores it
- a missing or unreadable blueprint is reported and skipped, never fatal to an install
- an unknown block type skips that block and its children instead of failing

Controls:

```dotenv
WEBBLOCKS_CMS_STARTER_CONTENT=true
WEBBLOCKS_CMS_STARTER_CONTENT_PATH=
```

- `WEBBLOCKS_CMS_STARTER_CONTENT=false` installs an empty published home page. `php artisan webblocks:install --skip-starter-content` does the same for a single run.
- `WEBBLOCKS_CMS_STARTER_CONTENT_PATH` points at a directory of your own blueprints when a product should ship its own starter page.

### Adding Starter Content To An Existing Install

An install created before this feature keeps its empty home page: System Update never adds content. To fill it once:

```bash
php artisan webblocks:starter-content
```

On a multisite install, `--site=handle` picks the site; without it the primary site is used.

It provisions the site's page at `/` if it is missing, then writes the starter blocks only if that page has no blocks. On a site whose home page already has content it does nothing and reports why, and it never touches any other page. Running it twice is safe.

The command takes no class name and does not ask for production confirmation, because both of those break the runners this is for. A hosting panel's Artisan screen cannot answer `db:seed`'s production prompt, and one has been seen to strip the backslashes out of a namespaced `--class` value, leaving Laravel to resolve `Database\Seeders\WebBlocksCmsDatabaseSeedersStarterContentSeeder`. The guarantee that would justify a confirmation prompt is structural here instead: starter content is only ever written into a page with no blocks.

The same work is reachable as a seeder, which is what `db:seed` runs on a fresh install:

```bash
php artisan db:seed --class="WebBlocks\Cms\Database\Seeders\StarterContentSeeder" --force
```

Blueprints are JSON files under the package's `database/content/starter` directory, using the `webblocks.cms.starter-content.v1` schema and the same block vocabulary as `docs/ai-page-building-guide.md`. `home.json` is the shipped default; a locale-specific `home.{locale}.json` beside it wins for an install whose default locale matches. The directory's `README.md` documents the file format.

## Common Setup Notes

- the installer is locked after completion
- `/webadmin` is the canonical CMS admin entry point
- package-consumer installs may sign in through `/webadmin/login`; co-installed apps may keep host-owned `/login`
- `/webadmin/dashboard` redirects to `/webadmin`
- CMS assets stay under `/cms`, for example `/cms/css`, `/cms/js`, and `/cms/brand`
- package-owned `/webadmin/login` uses package Blade views, the WebBlocks UI guest auth shell, pinned WebBlocks UI assets, `/cms/css/guest.css`, and CMS product brand assets from `/cms/brand`, including normal, dark-surface, on-accent/inverse, and high-contrast favicon/browser-tab variants
- `/cms` is reserved for CMS-owned static public assets and must not be used as a CMS admin route prefix, alias, or redirect
- `/admin` is not CMS-owned and must not be restored as a CMS admin route
- new pages start in `draft`
- if install-level features such as revisions, backups, or updates report missing tables, run `php artisan migrate`

The `/webadmin` and `/cms` split avoids the Nginx `try_files` collision where `/cms/` can be resolved as the physical `public/cms/` asset directory before Laravel handles a route. Do not solve admin access by adding a `public/cms/index.php` handoff; that front-controller bridge must stay absent from root and package public assets.

## Email And Contact Form Readiness

Configure Laravel mail delivery before publishing a public contact page. A typical SMTP `.env` setup looks like:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Site Name"

CONTACT_RECIPIENT_EMAIL=contact@example.com
```

`MAIL_*` controls Laravel mail delivery for Contact Form notification attempts. `MAIL_FROM_ADDRESS` is the safe fallback sender/from address and is also the last safe Contact Form recipient fallback when no more specific recipient is configured. `CONTACT_RECIPIENT_EMAIL` is optional and acts as an environment-level fallback recipient. Prefer configuring the site-level Contact recipient in `Site -> Edit -> Contact` when available, so contact routing lives with the site instead of only in `.env`.

After changing production or package-install `.env` mail settings, clear cached configuration if the install uses config caching:

```bash
php artisan optimize:clear
```

Contact Form notifications resolve recipients in this order:

1. Contact Form block `recipient_email`
2. Site default contact recipient from `Site -> Edit -> Contact`
3. `.env` `CONTACT_RECIPIENT_EMAIL`
4. safe `MAIL_FROM_ADDRESS` fallback

Accepted real Contact Form submissions are stored before email notification is attempted. Notification failure does not mean the public form submission failed. Admins should check `/webadmin/contact-messages` for stored messages, notification status, and safe failure details. Public visitors should only see normal success or validation feedback, not mail diagnostics.

`Sent` means the CMS handed the notification to the configured mail transport without an exception; it does not guarantee inbox delivery. `Skipped` or `Not configured` means no real notification send was attempted.

Use the native `contact_form` block for contact pages. Do not replace it with Trusted HTML, raw `<form>` markup, or `mailto:` fallbacks. The CMS renderer generates the hidden anti-spam check field automatically; do not create it manually. The old `website` honeypot field is no longer the public contract.

Practical Contact Form smoke test:

1. Publish or preview a page containing the native `contact_form` block.
2. Submit a test message with name, email, subject, and message.
3. Open `/webadmin/contact-messages`.
4. Confirm the message was stored.
5. Review notification status.
6. If email did not arrive, inspect the safe failure details and run mail diagnostics.

Diagnostic commands:

```bash
php artisan contact:mail-diagnose
php artisan contact:mail-diagnose --block=ID
php artisan contact:mail-diagnose --send-test=you@example.com
```

The diagnostic command must not print passwords, tokens, or mail secrets. Use `--block=ID` to inspect the recipient fallback chain for one Contact Form block. Use `--send-test=` only for a controlled SMTP send check to an intentional test address.

## Post-Install Next Steps

1. Sign in to `/webadmin`.
2. Review your site and locale configuration.
3. Configure site identity and domains.
4. Configure Laravel mail settings or approved system mail settings.
5. Configure a Contact Form recipient, preferably on `Site -> Edit -> Contact`.
6. Run `php artisan contact:mail-diagnose`.
7. Submit a test native Contact Form and confirm it stores a Contact Message.
8. Review email notification status for that test message.
9. Open the starter home page in the editor and make it your own.
10. Create your first page.
11. Add media, navigation, and blocks.
12. Publish content through the editorial workflow.
