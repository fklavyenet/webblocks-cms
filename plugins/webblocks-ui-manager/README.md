# WebBlocks UI Manager

Internal operator plugin for WebBlocks UI release metadata and first-party local CDN preparation workflows.

Current artifact version: `0.1.1`.

## Manual Lifecycle

1. Build the ZIP artifact with `php plugins/webblocks-ui-manager/build-plugin.php`.
2. Upload it through `System -> Plugins`.
3. Review the plugin detail screen.
4. Enable the plugin.
5. If health reports `Setup required` or `Plugin migrations pending`, run `Run Plugin Migrations` from the plugin detail screen.
6. Open `/webadmin/plugins/webblocks-ui-manager/releases` from the sidebar or directly after setup completes.
7. Disable before uninstalling. Uninstall removes the uploaded package and enabled state, but preserves `webblocks_ui_manager_*` tables.

The Releases screen checks for `webblocks_ui_manager_releases`, `webblocks_ui_manager_artifacts`, and `webblocks_ui_manager_publish_runs` before querying. Missing tables render controlled setup guidance on the releases URL, including a plugin detail link and super-admin migration action, instead of a raw database error or dashboard redirect.

Enabled admin routes use plugin-owned permissions. `webblocks-ui-manager.view` protects release listing/detail pages, `webblocks-ui-manager.manage` protects release metadata changes and the settings page, and `webblocks-ui-manager.publish` protects publish actions. CMS `super_admin` users are allowed for these active plugin permissions; other roles require an explicit CMS permission grant before accessing the plugin.

CMS v1.32.75+ bridges the full WebBlocks UI Manager admin release/settings route tree through CMS core, including New Release, edit/update, dry-run, and publish actions. This keeps enabled compatible manual plugin actions on `/webadmin/plugins/webblocks-ui-manager/...` even when the uploaded artifact copy has stale route/source context.
