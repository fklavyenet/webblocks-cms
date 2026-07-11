# Package Seeders

This directory is the package boundary for CMS-owned seeders that can move ahead of broader runtime migration.

During this phase:

- catalog seeders, including the package-owned `CoreCatalogSeeder` aggregator, may live here under the `WebBlocks\Cms\Database\Seeders\` namespace
- root `Database\Seeders\...` classes remain as compatibility entrypoints for existing installs and tests
- `PageLayoutSeeder`, `BlockTypeSeeder`, `DatabaseSeeder`, and update flow behavior remain root-owned until a later focused phase
