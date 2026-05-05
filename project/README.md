# Project Layer

Use `project/` for site-specific code that must survive CMS core updates.

## Structure

- `Providers/`
- `Routes/`
- `Console/Commands/`
- `Support/`
- `config/`
- `resources/views/`
- `tests/`

## Rules

- Keep instance-specific code here instead of core `app/`, `routes/`, `resources/`, or `config/`.
- Project config loads under the `project.*` namespace.
- Project views are available through the `project::` namespace.
- Project console commands can be registered in `project/Routes/console.php`.
- This layer is for one install or site instance. It is not the plugin system.
- These files are safe to keep locally, but they are not part of WebBlocks CMS core release packages.

## Site-Specific Docs Commands

- This project layer contains install-specific WebBlocks UI docs migration helpers.
- These commands are not part of WebBlocks CMS core releases.
- `project:sync-ui-docs-navigation`
- `project:sync-ui-docs-home-main`
- `webblocks:sync-ui-docs-getting-started` syncs the existing Getting Started page main slot with idempotent, marker-based WebBlocks UI docs content blocks.
- `project:webblocksui-local-resolver` prepares the local DDEV router alias for the WebBlocks UI docs preview host by writing `.ddev/config.webblocksui.local-resolver.yaml` with `additional_hostnames`.
- `project:webblocksui-setup-site` now targets the CMS default site and ensures the docs Home, Getting Started, and sidebar-navigation dependencies needed by the JSON-backed imports.
- WebBlocks UI project import payloads live under `storage/project/webblocksui.com`.
- The manifest and page payloads now use an explicit site convention of `{ "target": "default_site" }` so Architecture, Foundation, and future docs/page imports reconcile against the CMS default site by default.
- `project:webblocksui-local-resolver` edits DDEV config only. It does not touch CMS core routing and it does not edit the hosts file.
- After `project:webblocksui-local-resolver` updates DDEV config, run `ddev restart` to apply the router alias.
- `project:webblocksui-import docs-architecture` imports the Architecture page from the project payload in `storage/project/webblocksui.com/docs-architecture.json` sourced from `https://webblocksui.com/docs/architecture.html`.
- `project:webblocksui-import docs-foundation` imports the Foundation page from the project payload in `storage/project/webblocksui.com/docs-foundation.json` sourced from `https://webblocksui.com/docs/foundation.html`.
- Safe local workflow:
- `ddev export-db --file=before-webblocksui-docs-reimport-and-db-guard.sql.gz`
- `ddev artisan project:webblocksui-setup-site`
- `ddev artisan project:webblocksui-import docs-architecture`
- `ddev artisan project:webblocksui-import docs-foundation`
- Open `https://webblocks-cms.ddev.site/p/architecture`
- Or open `https://webblocks-cms.ddev.site/p/foundation`
- Canonical source page URL: `https://webblocksui.com/docs/architecture.html`.
- Canonical Foundation source page URL: `https://webblocksui.com/docs/foundation.html`.
- CMS local Architecture path: `/p/architecture`.
- CMS local Foundation path: `/p/foundation`.
- Default local preview URL: `https://webblocks-cms.ddev.site/p/architecture`.
- Default Foundation preview URL: `https://webblocks-cms.ddev.site/p/foundation`.
- Source page URL and CMS preview URL are separate: source content comes from `https://webblocksui.com/docs/architecture.html` and `https://webblocksui.com/docs/foundation.html`, while local CMS preview uses the default site host and current CMS path model.
- Destructive database reset commands are blocked by the CMS safety guard in normal local, development, and production environments. The blocked commands include `migrate:fresh`, `migrate:reset`, `migrate:refresh`, and `db:wipe`.
- Set `WEBBLOCKS_ALLOW_DESTRUCTIVE_DB_COMMANDS=true` only when you intentionally need to bypass that guard.
