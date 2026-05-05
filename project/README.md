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
- `project:webblocksui-setup-site` creates the project-layer WebBlocks UI docs site plus the docs Home, Getting Started, and sidebar-navigation dependencies needed by JSON-backed imports.
- Canonical site domain: `ui.docs.webblocksui.com`.
- Local DDEV preview host: `ui.docs.webblocksui.com.ddev.site`.
- `project:webblocksui-setup-site` uses the DDEV host alias as the persisted local site domain on local installs so the core host resolver can match requests without adding website-specific CMS core logic.
- `project:webblocksui-import docs-architecture` imports the Architecture page from the project payload in `storage/project/webblocksui.com/docs-architecture.json` sourced from `https://webblocksui.com/docs/architecture.html`.
- Architecture local preview URL: `https://ui.docs.webblocksui.com.ddev.site/p/architecture`.
- Source page URL and CMS preview URL are separate: source content comes from `https://webblocksui.com/docs/architecture.html`, while local CMS preview uses the DDEV host and current CMS path model.
