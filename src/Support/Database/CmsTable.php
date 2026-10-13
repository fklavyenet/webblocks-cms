<?php

namespace WebBlocks\Cms\Support\Database;

final class CmsTable
{
  public const PREFIX = 'wbcms_';

  /**
   * @var array<int, string>
   */
  public const TABLES = [
    'api_token_capabilities',
    'api_token_sites',
    'api_token_networks',
    'block_application_references',
    'page_runtime_settings',
    'page_runtime_items',
    'site_notification_policies',
    'scheduler_health',
    'plugin_lifecycles',
    'site_promotion_plans',
    'persistent_documents',
    'persistent_nodes',
    'snapshot_headers',
    'snapshot_pages',
    'snapshot_source_pages',
    'snapshot_shared_slots',
    'snapshot_page_translations',
    'snapshot_slots',
    'snapshot_assets',
    'snapshot_blocks',
    'snapshot_block_media',
    'snapshot_media_translations',
    'snapshot_text_translations',
    'snapshot_button_translations',
    'snapshot_image_translations',
    'snapshot_contact_translations',
    'snapshot_block_appearance_settings',
    'snapshot_block_media_settings',
    'snapshot_block_navigation_settings',
    'snapshot_block_content_settings',
    'snapshot_block_content_bindings',
    'snapshot_block_content_collections',
    'snapshot_block_applications',
    'snapshot_application_values',
    'snapshot_page_settings',
    'persistent_items',
    'persistent_metadata',
    'import_identity_maps',
    'import_copied_files',
    'setting_documents',
    'setting_nodes',
    'block_appearance_settings',
    'block_media_settings',
    'block_navigation_settings',
    'block_content_settings',
    'block_content_bindings',
    'block_content_collections',
    'block_application_values',
    'application_assets',
    'application_supports',
    'application_setting_fields',
    'application_setting_options',
    'asset_folders',
    'assets',
    'block_assets',
    'block_button_translations',
    'block_contact_form_translations',
    'block_gallery_item_translations',
    'block_image_translations',
    'block_media',
    'block_plugin_translations',
    'block_text_translations',
    'block_types',
    'blocks',
    'cms_api_token_activity_logs',
    'cms_api_tokens',
    'comment_entries',
    'contact_messages',
    'content_ratings',
    'demo_asset_references',
    'demo_media_references',
    'embedded_applications',
    'icon_catalog_items',
    'layout_types',
    'layouts',
    'locales',
    'media',
    'media_folders',
    'navigation_item_translations',
    'navigation_items',
    'page_assets',
    'page_layout_slots',
    'page_layouts',
    'page_revisions',
    'page_revision_candidates',
    'page_slots',
    'page_translations',
    'page_types',
    'pages',
    'public_search_index',
    'revision_capture_events',
    'shared_slot_blocks',
    'shared_slot_revisions',
    'shared_slots',
    'site_domains',
    'site_exports',
    'site_imports',
    'site_locales',
    'site_user',
    'site_variables',
    'sites',
    'slot_types',
    'system_backup_restores',
    'system_backups',
    'system_settings',
    'system_update_runs',
    'system_update_run_steps',
    'system_update_requests',
    'visitor_events',
    'visitor_daily_totals',
  ];

  public static function name(string $table): string
  {
    if (str_starts_with($table, self::PREFIX)) {
      return $table;
    }

    if (! in_array($table, self::TABLES, true)) {
      return $table;
    }

    return self::PREFIX.$table;
  }

  /**
   * @return array<string, string>
   */
  public static function renameMap(): array
  {
    return array_combine(self::TABLES, array_map(self::name(...), self::TABLES));
  }
}
