<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Database\CmsTable;

return new class extends Migration
{
  public function up(): void
  {
    foreach ($this->tables() as $table) {
      $prefixed = CmsTable::name($table);

      if (Schema::hasTable($table) && ! Schema::hasTable($prefixed)) {
        Schema::rename($table, $prefixed);
      }
    }
  }

  public function down(): void
  {
    foreach (array_reverse($this->tables()) as $table) {
      $prefixed = CmsTable::name($table);

      if (Schema::hasTable($prefixed) && ! Schema::hasTable($table)) {
        Schema::rename($prefixed, $table);
      }
    }
  }

  /**
   * @return array<int, string>
   */
  private function tables(): array
  {
    return [
      'page_types',
      'layout_types',
      'slot_types',
      'block_types',
      'system_settings',
      'navigation_items',
      'media_folders',
      'media',
      'sites',
      'locales',
      'site_locales',
      'site_user',
      'site_domains',
      'site_variables',
      'layouts',
      'page_layouts',
      'page_layout_slots',
      'pages',
      'page_translations',
      'page_assets',
      'page_revisions',
      'shared_slots',
      'page_slots',
      'blocks',
      'block_media',
      'shared_slot_blocks',
      'shared_slot_revisions',
      'block_text_translations',
      'block_button_translations',
      'block_image_translations',
      'block_contact_form_translations',
      'block_gallery_item_translations',
      'public_search_index',
      'site_exports',
      'site_imports',
      'contact_messages',
      'visitor_events',
      'icon_catalog_items',
      'system_update_runs',
      'system_backups',
      'system_backup_restores',
      'cms_api_tokens',
      'cms_api_token_activity_logs',
      'comment_entries',
      'content_ratings',
      'demo_media_references',
    ];
  }
};
