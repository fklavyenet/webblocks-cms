# WebBlocks CMS

WebBlocks CMS is a Laravel-native, block-based CMS distributed as a Composer package. It adds multisite content, block-based pages, media, navigation, editorial workflows, and an operator admin under `/webadmin` to a host Laravel application.

This repository is package source. It is not a complete deployable Laravel application and does not include a host `.env`, application bootstrap, or web server configuration.

## Requirements

- PHP `^8.4`
- Laravel Framework `^13.0`
- Composer 2
- PHP extensions: `mbstring`, `sodium`, and `zip`
- A database supported by the host Laravel application
- Optional: GD for CMS image and media transformations

## Install

Start with a fresh or existing Laravel 13 application whose database and application key are configured. The normal host `App\Models\User` model must exist and be writable during installation.

```bash
composer require fklavyenet/webblocks-cms
php artisan webblocks:install \
  --name="Admin User" \
  --email="admin@example.com" \
  --password="use-a-strong-password"
```

Laravel discovers `WebBlocks\Cms\WebBlocksCmsServiceProvider` through the package manifest. The install command publishes missing CMS configuration, patches the host User model with CMS access behavior, removes only Laravel's untouched welcome route, runs the package-owned fresh schema, creates Laravel support tables when needed, installs static assets under `public/cms`, prepares storage, seeds the core catalog, and creates the first site and super administrator. Repeating the command is safe; existing schema and administrator state are preserved.

The host remains responsible for its application bootstrap, `.env`, database, mail/queue configuration, deployment, backups, and public document root.

## Publishing

The package supports these tags:

```bash
php artisan vendor:publish --tag=webblocks-cms-config
php artisan vendor:publish --tag=webblocks-cms-assets
php artisan vendor:publish --tag=webblocks-cms-stubs
```

Views, translations, and migrations load from the installed package and do not have separate publish tags. Avoid `--force` unless you intentionally want to replace package-owned published files in a controlled environment.

## Upgrades

Read [UPGRADING.md](UPGRADING.md) before changing installation topology. Existing full-repository clones must not pull across a future package-only repository transition. Composer/package-native updates and Publisher/System Updates are distinct workflows.

## Compatibility evidence

The package targets Laravel 13. CI performs a complete install/runtime smoke against the currently resolved Laravel 13 graph. The declared `13.0.*` floor has a separate dependency-resolution check; that check is not presented as a complete Laravel 13.0 application installation and is not a recommendation to pin production applications to an old patch release.

## Development

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer check-platform-reqs
composer format:test
composer test
```

See [CONTRIBUTING.md](CONTRIBUTING.md). Package CI also validates a temporary current-Laravel consumer, the Laravel `13.0.*` dependency floor, documentation, and the exported distribution boundary.

## Support and security

Use the [issue tracker](https://github.com/fklavyenet/webblocks-cms/issues) for reproducible bugs and feature requests. See [SUPPORT.md](SUPPORT.md) for support boundaries. Do not disclose vulnerabilities publicly; follow [SECURITY.md](SECURITY.md).

## License

WebBlocks CMS is licensed under the [MIT License](LICENSE).
