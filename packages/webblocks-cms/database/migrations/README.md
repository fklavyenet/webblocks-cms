# Reserved Package Migrations

This directory is the reserved package boundary for future package-owned WebBlocks CMS migrations.

During the boundary pilot phase:

- active root `database/migrations/` remains authoritative
- no executable schema-changing package migrations are shipped here
- package migration loading remains inert unless a later focused runtime phase intentionally wires it
- legacy root migrations remain the compatibility path for existing installs until package migration ownership and post-update wiring are explicitly redesigned
