# Package Seeders

This directory is the package boundary for low-risk CMS-owned seeders that can move ahead of broader runtime migration.

During this phase:

- low-risk catalog seeders may live here under the `WebBlocks\Cms\Database\Seeders\` namespace
- root `Database\Seeders\...` classes remain as compatibility entrypoints for existing installs and tests
- `CoreCatalogSeeder`, `PageLayoutSeeder`, `BlockTypeSeeder`, `DatabaseSeeder`, and update flow behavior remain root-owned until a later focused phase
