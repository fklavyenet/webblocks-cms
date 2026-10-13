<?php

namespace WebBlocks\Cms\Support\Relational;

use WebBlocks\Cms\Models\CmsModel;

final class PersistentFields
{
  /** Closed registry of CMS-owned legacy persistence; API field names remain virtual. */
  public const MODELS = [
    'PluginLifecycle' => ['plugin_lifecycle_id', 'wbcms_plugin_lifecycles', ['setup_result' => 'array', 'previous_state' => 'array', 'legacy_state' => 'array']],
    'SitePromotionRecord' => ['promotion_plan_id', 'wbcms_site_promotion_plans', ['plan' => 'array']],
    'Page' => ['page_id', 'wbcms_pages', ['settings' => 'array']],
    'PageSlot' => ['page_slot_id', 'wbcms_page_slots', ['settings' => 'array']],
    'PageLayout' => ['page_layout_id', 'wbcms_page_layouts', ['slot_schema' => 'array', 'wrapper_schema' => 'array']],
    'PageRevision' => ['page_revision_id', 'wbcms_page_revisions', ['snapshot' => 'array']],
    'SharedSlotRevision' => ['shared_slot_revision_id', 'wbcms_shared_slot_revisions', ['snapshot' => 'array']],
    'Block' => ['block_id', 'wbcms_blocks', ['meta' => 'text']],
    'BlockTextTranslation' => ['block_text_translation_id', 'wbcms_block_text_translations', ['meta' => 'text']],
    'Site' => ['site_id', 'wbcms_sites', ['notification_settings' => 'array']],
    'CmsApiToken' => ['token_id', 'wbcms_cms_api_tokens', ['capabilities' => 'array', 'allowed_site_ids' => 'array', 'allowed_ip_ranges' => 'array']],
    'SiteExport' => ['site_export_id', 'wbcms_site_exports', ['summary_json' => 'array', 'manifest_json' => 'array']],
    'SiteImport' => ['site_import_id', 'wbcms_site_imports', ['summary_json' => 'array', 'manifest_json' => 'array', 'resume_state' => 'array']],
    'SystemBackupRestore' => ['backup_restore_id', 'wbcms_system_backup_restores', ['manifest' => 'array', 'restored_parts' => 'array']],
    'SystemUpdateRequest' => ['update_request_id', 'wbcms_system_update_requests', ['result' => 'array']],
    'IconCatalogItem' => ['icon_id', 'wbcms_icon_catalog_items', ['categories' => 'array', 'contexts' => 'array', 'keywords' => 'array']],
    'ContactMessage' => ['contact_message_id', 'wbcms_contact_messages', ['spam_reasons' => 'array']],
    'CommentEntry' => ['comment_entry_id', 'wbcms_comment_entries', ['spam_reasons' => 'array']],
  ];

  public static function definition(CmsModel $model): ?array
  {
    foreach (self::MODELS as $name => $definition) {
      if (is_a($model, 'WebBlocks\\Cms\\Models\\'.$name)) {
        return $definition;
      }
    }

    return null;
  }

  public static function attributes(CmsModel $model): array
  {
    return array_keys(self::definition($model)[2] ?? []);
  }
}
