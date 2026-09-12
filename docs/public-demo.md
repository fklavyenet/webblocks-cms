# Public demo mode

WebBlocks CMS can run as a disposable public demo when the host application is
fully isolated from every production install. Demo mode is an additional safety
boundary, not a substitute for a separate site user, database, storage tree,
application key, session cookie, cache, queue, logs, or credentials.

## Required host configuration

Set all of these values only in the isolated demo installation:

```dotenv
APP_ENV=demo
APP_DEBUG=false
APP_URL=https://demo.example.com
SESSION_DRIVER=database
SESSION_COOKIE=webblocks_demo_session
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log

WEBBLOCKS_CMS_PUBLIC_DEMO_ENABLED=true
WEBBLOCKS_CMS_PUBLIC_DEMO_ENVIRONMENT=demo
WEBBLOCKS_CMS_PUBLIC_DEMO_HOST=demo.example.com
WEBBLOCKS_CMS_PUBLIC_DEMO_USER_EMAIL=demo@example.com
WEBBLOCKS_CMS_PUBLIC_DEMO_LOGIN_RATE_LIMIT=10

WEBBLOCKS_CMS_PUBLIC_DEMO_RESET_ENABLED=true
WEBBLOCKS_CMS_PUBLIC_DEMO_DATABASE=replace_with_the_exact_demo_database_name
WEBBLOCKS_CMS_PUBLIC_DEMO_SEEDER=Database\\Seeders\\PublicDemoSeeder
WEBBLOCKS_CMS_PUBLIC_DEMO_MINIMUM_SITES=1
WEBBLOCKS_CMS_PUBLIC_DEMO_MINIMUM_PAGES=1
```

The configured user must be active, have the `editor` role, and be assigned to
at least one demo site. One-click login refuses any other identity. The demo
middleware permits page, translation, block, navigation, preview, and publish
workflows. Media stays readable but uploads, remote fetches, replacement,
deletion, profile changes, tokens, users, roles, system settings, plugins,
updates, backups, imports, exports, site administration, and unlisted writes
are denied.

Public contact, rating, comment, and privacy-consent writes are disabled. Public
responses send `X-Robots-Tag: noindex, nofollow, noarchive`, sitemaps return 404,
and `robots.txt` disallows crawling.

## Reset contract

Create `storage/app/public-demo.marker` containing only the exact configured
database name. The host seeder is responsible for recreating fictional sites,
locales, pages, safe blocks, the editor account and repository-owned media. It
must not make network calls or copy credentials from another installation.

Validate the reset boundary before enabling the scheduler:

```bash
php artisan public-demo:reset --dry-run
```

The package schedules `public-demo:reset` hourly when both demo switches are
enabled. Herne only needs to provision and enable the normal per-site Laravel
scheduler. The command enters maintenance mode, runs a forced fresh migration
with the configured seeder, and verifies the demo identity plus minimum site and
page counts. Any reset or health-check failure leaves the application in
maintenance mode.

Never reuse this configuration or marker in a production installation.
