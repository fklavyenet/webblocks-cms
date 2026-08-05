<?php

namespace WebBlocks\Cms\Support\Database;

final class CmsTable
{
  public const PREFIX = 'wbcms_';

  /**
   * @var array<int, string>
   */
  public const TABLES = [
    'asset_folders',
    'assets',
    'block_assets',
    'block_button_translations',
    'block_contact_form_translations',
    'block_gallery_item_translations',
    'block_image_translations',
    'block_media',
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
    'page_slots',
    'page_translations',
    'page_types',
    'pages',
    'public_search_index',
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
    'visitor_events',
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
