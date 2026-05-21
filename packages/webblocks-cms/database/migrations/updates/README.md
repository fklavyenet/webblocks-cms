# Package Update Migrations

This directory is the explicit package-owned migration path used by System Update in package consumer installs.

Package-native updates run PHP migrations placed here with `artisan migrate --path=... --realpath --force`.
They do not run the host Laravel application's root `database/migrations` directory.

The historical package migrations and fresh-install schema remain separate boundaries:

- `database/migrations/fresh` is for clean `webblocks:install` schema creation.
- `database/migrations` preserves historical source-maintained migrations.
- `database/migrations/updates` is for safe package-owned update migrations after a package consumer install already exists.
