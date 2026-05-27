# WebBlocks UI Manager

Internal operator plugin for WebBlocks UI release metadata and first-party local CDN preparation workflows.

## Manual Lifecycle

1. Build the ZIP artifact with `php plugins/webblocks-ui-manager/build-plugin.php`.
2. Upload it through `System -> Plugins`.
3. Review the plugin detail screen.
4. Enable the plugin.
5. If health reports `Setup required` or `Plugin migrations pending`, run `Run Plugin Migrations` from the plugin detail screen.
6. Open `/webadmin/plugins/webblocks-ui-manager/releases` after setup completes.
7. Disable before uninstalling. Uninstall removes the uploaded package and enabled state, but preserves `webblocks_ui_manager_*` tables.

The Releases screen checks for `webblocks_ui_manager_releases`, `webblocks_ui_manager_artifacts`, and `webblocks_ui_manager_publish_runs` before querying. Missing tables render controlled setup guidance instead of a raw database error.
