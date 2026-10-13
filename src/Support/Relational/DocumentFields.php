<?php

namespace WebBlocks\Cms\Support\Relational;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

final class DocumentFields
{
  public static function for(CmsModel $model, string $attribute): ?array
  {
    if ($model instanceof Page && $attribute === 'settings') {
      return self::page();
    }
    if ($model instanceof Block && $attribute === 'settings') {
      return self::block();
    }
    if ($model instanceof Site && $attribute === 'notification_settings') {
      return self::notification();
    }

    return null;
  }

  public static function page(): array
  {
    return [
      'table' => 'wbcms_page_runtime_settings', 'owner' => 'page_id', 'owner_table' => 'wbcms_pages',
      'groups' => ['staged_update', 'revision_restore_candidate', 'source_sync'],
      'fields' => [
        'public_shell' => ['layout_handle', 'string'],
        'shared_slot_id' => ['shared_slot_reference', 'integer'],
        'shared_slot_handle' => ['shared_slot_handle', 'string'],
        'staged_update.type' => ['staged_type', 'string'],
        'staged_update.source_page_id' => ['staged_source_reference', 'integer'],
        'staged_update.source_path' => ['staged_source_path', 'text'],
        'staged_update.source_updated_at' => ['staged_source_updated_at', 'text'],
        'staged_update.state' => ['staged_state', 'string'],
        'staged_update.created_at' => ['staged_created_at', 'text'],
        'staged_update.reused_at' => ['staged_reused_at', 'text'],
        'staged_update.promoted_at' => ['staged_promoted_at', 'text'],
        'staged_update.promoted_to_page_id' => ['staged_promoted_reference', 'integer'],
        'revision_restore_candidate.state' => ['restore_state', 'string'],
        'revision_restore_candidate.source_page_id' => ['restore_source_reference', 'integer'],
        'revision_restore_candidate.revision_id' => ['restore_revision_reference', 'integer'],
        'revision_restore_candidate.candidate_id' => ['restore_candidate_reference', 'integer'],
        'revision_restore_candidate.created_at' => ['restore_created_at', 'text'],
        'source_sync.type' => ['sync_type', 'string'],
        'source_sync.source_id' => ['sync_source_id', 'text'],
        'source_sync.source_path' => ['sync_source_path', 'text'],
        'source_sync.source_sha256' => ['sync_source_sha256', 'string'],
        'source_sync.last_synced_at' => ['sync_last_synced_at', 'text'],
      ],
      'lists' => ['staged_update.managed_slots', 'source_sync.managed_slots'],
      'relations' => ['resolved_layout_id' => 'wbcms_page_layouts', 'resolved_shared_slot_id' => 'wbcms_shared_slots', 'resolved_staged_source_id' => 'wbcms_pages', 'resolved_restore_candidate_id' => 'wbcms_page_revision_candidates', 'resolved_restore_source_id' => 'wbcms_pages', 'resolved_restore_revision_id' => 'wbcms_page_revisions', 'resolved_promoted_page_id' => 'wbcms_pages'],
    ];
  }

  public static function block(): array
  {
    return [
      'table' => 'wbcms_block_application_references', 'owner' => 'block_id', 'owner_table' => 'wbcms_blocks',
      'groups' => [], 'fields' => ['application_handle' => ['application_handle', 'string']], 'lists' => [],
      'relations' => ['resolved_application_id' => 'wbcms_embedded_applications'],
    ];
  }

  public static function notification(): array
  {
    return [
      'table' => 'wbcms_site_notification_policies', 'owner' => 'site_id', 'owner_table' => 'wbcms_sites',
      'groups' => [],
      'fields' => [
        'notification_mode' => ['notification_mode', 'string'],
        'notification_frequency' => ['notification_frequency', 'string'],
        'batch_minutes' => ['batch_minutes', 'integer'],
        'daily_summary' => ['daily_summary', 'boolean'],
        'summary_hour' => ['summary_hour', 'integer'],
      ],
      'lists' => [], 'relations' => [],
    ];
  }
}
