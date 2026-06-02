# Native Local Development

This document describes the supported local development path for WebBlocks CMS using locally installed PHP, Composer, Nginx, MySQL or MariaDB, and Redis. Container-based local tooling is no longer part of the project workflow.

## Local URL

Use trusted HTTPS and `.test` domains for browser workflows.

- CMS URL: `https://webblocks-cms.test`
- Admin: `https://webblocks-cms.test/webadmin`
- Installer: `https://webblocks-cms.test/install`

`php artisan serve` is still useful for quick CLI or route checks, but the normal browser target should be the trusted Nginx/PHP-FPM setup above.

## Readiness Check

Run:

```bash
composer native:doctor
```

The doctor command checks local runtime readiness without installing services or changing local files.

Useful manual checks:

```bash
php -v
composer --version
nginx -t
mysql --version
redis-cli ping
```

On Apple Silicon Homebrew installs, service files and logs usually live under `/opt/homebrew`. On Intel Homebrew installs, they usually live under `/usr/local`.

## Fresh Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Open `https://webblocks-cms.test/install` for the browser wizard when the install is not complete yet.

## Environment

Typical native local values:

```dotenv
APP_ENV=local
APP_URL=https://webblocks-cms.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webblocks_cms
DB_USERNAME=webblocks_cms
DB_PASSWORD=
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
CMS_BACKUP_EXECUTION=auto
```

Use a separate local database and user for the CMS install. If your machine already has an occupied MySQL/MariaDB instance, use a separate port and matching `.env` values.

## Backups And Restores

Backup and restore commands use local database CLIs in native mode:

- MySQL/MariaDB backups require `mysqldump` or `mariadb-dump`.
- MySQL/MariaDB restores require `mysql` or `mariadb`.
- SQLite backups and restores use PHP/PDO paths.

`CMS_BACKUP_EXECUTION=auto` and `CMS_BACKUP_EXECUTION=direct` both resolve to direct local CLI execution for MySQL/MariaDB. The project does not require a container command for backup or restore.

## Smoke Check

After service restarts, restores, or environment changes, run:

```bash
composer native:smoke
```

For focused tests:

```bash
php artisan test --filter=PluginCatalogBrowserTest
composer test:release-fast
composer format:changed
```

## Troubleshooting

If the browser reaches the wrong site, confirm that the Nginx server block owns `webblocks-cms.test`, that the certificate is trusted, and that `/etc/hosts` points the domain at `127.0.0.1`.

If database commands fail, confirm `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`, then run:

```bash
php artisan config:clear
php artisan migrate:status
```

If Redis-backed features fail, confirm the local Redis service is running:

```bash
redis-cli ping
```
