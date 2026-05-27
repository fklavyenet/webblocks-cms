# Package Migrations

This directory is the reserved package boundary for package-owned WebBlocks CMS migrations.

During the boundary pilot phase:

- active root `database/migrations/` remains authoritative for source-maintained installs
- WebBlocks UI Manager ships the first package-owned pilot plugin migrations for release, artifact, and publish-run metadata
- package migration loading remains disabled by default unless a package consumer explicitly enables the package migration boundary
- package consumer System Updates use the dedicated `database/migrations/updates` path and do not run host Laravel application migrations
