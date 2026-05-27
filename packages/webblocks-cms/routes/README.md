# Package Routes

This directory is the package boundary for package-owned WebBlocks CMS route files.

During the package transition, active root `routes/` remains authoritative for CMS core routes unless routes are intentionally moved and wired in a focused migration phase. First-party plugin route files may live here under `routes/plugins/{plugin-handle}.php` and must remain under `/webadmin/plugins/{plugin-handle}/...` when active.
