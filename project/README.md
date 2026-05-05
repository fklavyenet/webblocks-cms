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
- `project:webblocksui-setup-site` creates the project-layer WebBlocks UI docs site plus the docs Home, Getting Started, and sidebar-navigation dependencies needed by JSON-backed imports.
- Canonical site domain: `ui.docs.webblocksui.com`.
- Local DDEV preview host: `ui.docs.webblocksui.com.ddev.site`.
- `project:webblocksui-local-resolver` edits DDEV config only. It does not touch CMS core routing and it does not edit the hosts file.
- After `project:webblocksui-local-resolver` updates DDEV config, run `ddev restart` to apply the router alias.
- `project:webblocksui-setup-site` uses the DDEV host alias as the persisted local site domain on local installs so the core host resolver can match requests without adding website-specific CMS core logic.
- `project:webblocksui-import docs-architecture` imports the Architecture page from the project payload in `storage/project/webblocksui.com/docs-architecture.json` sourced from `https://webblocksui.com/docs/architecture.html`.
- `project:webblocksui-import docs-foundation` imports the Foundation page from the project payload in `storage/project/webblocksui.com/docs-foundation.json` sourced from `https://webblocksui.com/docs/foundation.html`.
- Local workflow:
- `ddev artisan project:webblocksui-local-resolver`
- `ddev restart`
- `ddev artisan project:webblocksui-setup-site`
- `ddev artisan project:webblocksui-import docs-architecture`
- `ddev artisan project:webblocksui-import docs-foundation`
- Open `https://ui.docs.webblocksui.com.ddev.site/p/architecture`
- Or open `https://ui.docs.webblocksui.com.ddev.site/p/foundation`
- Canonical source page URL: `https://webblocksui.com/docs/architecture.html`.
- Canonical Foundation source page URL: `https://webblocksui.com/docs/foundation.html`.
- CMS local Architecture path: `/p/architecture`.
- CMS local Foundation path: `/p/foundation`.
- Architecture local preview URL: `https://ui.docs.webblocksui.com.ddev.site/p/architecture`.
- Foundation local preview URL: `https://ui.docs.webblocksui.com.ddev.site/p/foundation`.
- Source page URL and CMS preview URL are separate: source content comes from `https://webblocksui.com/docs/architecture.html`, while local CMS preview uses the DDEV host and current CMS path model.
