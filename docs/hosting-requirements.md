---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/hosting-requirements
cms_title: Hosting Requirements
cms_layout: docs
cms_source_id: webblocks-cms:docs/hosting-requirements.md
---

# Hosting Requirements

This page defines the hosting contract for a production WebBlocks CMS installation. It is intended for agencies, server operators, and hosting providers evaluating a server before deployment.

WebBlocks CMS is a Composer package installed into a Laravel host application. The host application owns its environment, web server, database, mail, queues, scheduled tasks, backups, and deployment. Meeting the package requirements alone does not make an otherwise incomplete Laravel application deployable.

## Required platform

| Area | Minimum requirement |
| --- | --- |
| PHP | PHP `8.4` or newer within the Composer-supported `^8.4` range, with CLI and the web runtime using the same compatible release |
| Framework | Laravel Framework `13.x` |
| Dependency manager | Composer 2, available during install and package-native System Updates |
| PHP extensions declared by the package | `mbstring`, `sodium`, and `zip` |
| Production database baseline | MySQL 8.0 with InnoDB, a database and credentials the application may use for tables, indexes, foreign keys, and transactions |
| Web server | Nginx, Apache, or an equivalent server that can route Laravel requests through `public/index.php` |
| Document root | The host application's `public/` directory, never the application root |
| TLS | HTTPS for production administration and public traffic |

Composer also resolves Laravel's own platform requirements. The provider must allow `composer check-platform-reqs` to pass for the complete host application; the table above does not replace that check.

## Provisional measured memory profile

No production-certified universal PHP memory minimum or request timeout has been established yet. The current partial local qualification produced these provisional planning values:

| Tested workload | Provisional planning value |
| --- | --- |
| CMS core, without GD image transformations | **PHP `memory_limit` of at least 128 MiB** |
| A PHP worker that may transform images with GD | **At least 512 MiB of actual process capacity per concurrent worker**, qualified for source images up to 6,000 × 4,000 pixels (24 MP) |

The 128 MiB value is a provisional core candidate: the complete feature suite passed five of five times at that limit and failed at 96 MiB. It is not enough by itself for GD image processing. In the 24 MP WebP test, PHP reported only 32.5 MiB while the worker's actual peak resident memory reached 312.26 MiB; GD allocates significant memory outside PHP's tracked `memory_limit`. Applying the qualification protocol's safety margin produces a provisional planning profile of 512 MiB of real memory for each simultaneously transforming PHP worker.

The 512 MiB figure is worker capacity, not total server RAM. The server must additionally accommodate the operating system, web server, database, caches, and other concurrent PHP workers. Because the CMS currently limits uploaded file size but not raster pixel dimensions, 512 MiB cannot guarantee arbitrary images larger than the tested 24 MP workload. See [Hosting Capacity Results](hosting-capacity-results.md) for the measurements and qualification boundary.

SQLite is used by the package test suite and is supported by specific backup/restore code paths, but it is not the production hosting baseline. MariaDB-aware backup and restore paths exist, but a MariaDB version is not currently part of the production acceptance matrix. PostgreSQL must not be offered as a fully supported production target until the complete install, migration, operation, backup, and restore flows are covered and documented.

## PHP capabilities

The required extensions must be enabled in both PHP-FPM/Apache PHP and PHP CLI. A hosting panel that exposes different configurations for web and CLI PHP must keep them aligned.

The following capabilities are conditional:

- `gd`, including the codecs for the media formats in use, enables thumbnail and responsive image generation. Without it, eligible transformations fall back to original media URLs.
- The database PDO driver must match the selected database; the production MySQL profile therefore needs `pdo_mysql`.
- Standard Laravel and Composer platform requirements, commonly including OpenSSL and file information support, must remain enabled as required by the resolved host application.
- `proc_open` and permission to run PHP and Composer processes are required for the package-native System Update workflow. If the provider disables process execution, deployments and updates must be operator-managed outside the CMS.

Do not infer an extension requirement from a development-only doctor command. The authoritative install check is Composer platform resolution for the complete host application, followed by the CMS readiness checks.

## Filesystem and permissions

The deployed code must be readable by the PHP runtime. The PHP runtime user, or a shared deployment group, needs read/write access to:

- `storage/`, including `storage/framework`, `storage/logs`, public media, temporary workspaces, and the configured backups disk;
- `bootstrap/cache`;
- `public/site`, where site and page override assets may be created;
- the application root and configured update workspace only when package-native System Updates will modify the installed package; and
- any other Laravel disk configured by the host for CMS media or backups.

Use ownership or a narrowly scoped deployment group; do not use world-writable `777` permissions. The server should permit Laravel's `public/storage` symbolic link when public media uses the standard `storage/app/public` disk. If symlinks are unavailable, the host must provide an equivalent public-disk serving arrangement.

The application root, `.env`, `vendor/`, `storage/`, `.git/`, and source files must not be directly web-accessible. Requests such as `/.env` and `/.git/config` must return `404`.

## Web server and URL behavior

The server must:

- serve existing files below `public/` directly and send other requests to Laravel's `public/index.php`;
- preserve HTTPS and host information through any reverse proxy so Laravel generates correct secure URLs;
- allow the CMS admin under `/webadmin` and static package assets under `/cms` to coexist;
- avoid adding a `public/cms/index.php` front-controller or treating `/cms` as an admin route; and
- support the upload body sizes and request timeouts selected by the operator for the site's media policy.

WebBlocks CMS does not yet publish a production-certified minimum for PHP memory or request timeout. The current [capacity results](hosting-capacity-results.md) establish a provisional core PHP `memory_limit` candidate of 128 MiB and a tested 512 MiB process-capacity profile for one 24 MP GD-transforming worker. The latter is not universal because raster pixel dimensions are currently unbounded. The current CMS media validation ceiling is 50 MiB per upload, and package-native System Update has an absolute 500 MiB free-space preflight floor; neither value alone is a general memory, request, or storage capacity guarantee. A provider proposal must state its actual limits. Use [Hosting Capacity Validation](hosting-capacity-validation.md) to qualify and publish workload-backed minimums.

## Feature-dependent services

| Capability | Hosting dependency |
| --- | --- |
| Image variants | PHP GD with the required JPEG, PNG, or WebP codec |
| Contact notifications and password-reset mail | A working Laravel mail transport and provider credentials |
| Scheduled housekeeping | Laravel's normal `schedule:run` cron entry; optional when post-update/manual cleanup is sufficient |
| Queues | Owned by the host application; not required for synchronous CMS image generation or public search indexing |
| Package-native System Updates | Outbound HTTPS, ZIP and sodium, Composer 2, `proc_open`, executable PHP/Composer, writable application/update paths, and enough free space for download, extraction, backup, and rollback |
| Native MySQL backup/restore | `mysqldump` or `mariadb-dump` for export and `mysql` or `mariadb` for import, available to the PHP process |
| Remote media import | Outbound HTTP/HTTPS subject to the CMS network-safety checks and any hosting firewall policy |

A server may run the CMS without optional capabilities only when the corresponding feature is disabled or handled operationally elsewhere. The limitation must be recorded during handoff.

## Production configuration and operations

The host application must have a strong `APP_KEY`, `APP_DEBUG=false`, a correct public `APP_URL`, secure session cookies over HTTPS, working database credentials, and production-appropriate session/cache/mail settings. Secrets belong in the environment and must not be committed or exposed through the document root.

The hosting arrangement must also define:

- how database and uploaded files are backed up outside the application;
- how releases are deployed when in-app updates are unavailable;
- how PHP-FPM or the relevant service is reloaded when OPcache does not validate timestamps;
- where logs are retained and how disk exhaustion is monitored; and
- who owns TLS renewal, database maintenance, restore tests, and incident response.

Application-level backups do not replace provider or infrastructure backups.

## Acceptance

Before approving a host, review the current [Hosting Capacity Results](hosting-capacity-results.md), select or qualify a tested profile using [Hosting Capacity Validation](hosting-capacity-validation.md), then complete the [Hosting Readiness Checklist](hosting-readiness-checklist.md). After deployment, run:

```bash
composer check-platform-reqs
php artisan about
php artisan webblocks:install --help
```

Then verify the public site, `/webadmin/login`, static `/cms` assets, media upload, and any enabled feature-dependent service. System Update has its own pass/fail preflight and must remain unavailable when one of its requirements fails.

See also [Installation](installation.md), [Security](security.md), [Operations](operations.md), and [Media Image Variants](media-image-variants.md).
