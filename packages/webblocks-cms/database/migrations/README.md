# Reserved Package Migrations

This directory is the reserved package boundary for future package-owned WebBlocks CMS migrations.

During the boundary pilot phase:

- active root `database/migrations/` remains authoritative for source-maintained installs
- no executable schema-changing package migrations are shipped here
- package migration loading remains inert for the historical migration directory unless a later focused runtime phase intentionally wires it
- package consumer System Updates use the dedicated `database/migrations/updates` path and do not run host Laravel application migrations
