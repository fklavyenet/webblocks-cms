<?php

namespace WebBlocks\Cms\Support\System;

final class CleanupSettingsFields
{
  private const MAINTENANCE = [
    'asset_revision_days' => [SystemSettings::CLEANUP_ASSET_REVISION_DAYS, 3650],
    'keep_latest_asset_revisions' => [SystemSettings::CLEANUP_KEEP_LATEST_ASSET_REVISIONS, 1000],
    'temporary_workspace_hours' => [SystemSettings::CLEANUP_TEMPORARY_WORKSPACE_HOURS, 8760],
    'page_revision_days' => [SystemSettings::CLEANUP_PAGE_REVISION_DAYS, 3650],
    'keep_latest_page_revisions' => [SystemSettings::CLEANUP_KEEP_LATEST_PAGE_REVISIONS, 1000],
    'shared_slot_revision_days' => [SystemSettings::CLEANUP_SHARED_SLOT_REVISION_DAYS, 3650],
    'keep_latest_shared_slot_revisions' => [SystemSettings::CLEANUP_KEEP_LATEST_SHARED_SLOT_REVISIONS, 1000],
  ];

  private const BACKUPS = [
    'enabled' => [SystemSettings::BACKUP_CLEANUP_ENABLED, null],
    'pre_update_days' => [SystemSettings::BACKUP_CLEANUP_PRE_UPDATE_DAYS, 3650],
    'keep_latest_pre_update' => [SystemSettings::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE, 100],
    'restore_safety_days' => [SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS, 3650],
    'content_apply_days' => [SystemSettings::BACKUP_CLEANUP_CONTENT_APPLY_DAYS, 3650],
  ];

  public static function maintenanceRules(): array
  {
    return [
      'automatic' => ['sometimes', 'array:'.implode(',', array_keys(SystemSettings::CLEANUP_AUTOMATIC_KEYS))],
      'automatic.*' => ['required', 'boolean'],
      ...self::rules(self::MAINTENANCE),
    ];
  }

  public static function backupRules(string $prefix = ''): array
  {
    return self::rules(self::BACKUPS, $prefix);
  }

  public static function maintenancePayload(array $input): array
  {
    $values = self::payload(self::MAINTENANCE, $input);
    foreach ($input['automatic'] ?? [] as $category => $enabled) {
      $values[SystemSettings::CLEANUP_AUTOMATIC_KEYS[$category]] = $enabled;
    }

    return $values;
  }

  public static function backupPayload(array $input, string $prefix = ''): array
  {
    return BackupRetentionSettings::payload($input) + self::payload(self::BACKUPS, $input, $prefix);
  }

  private static function rules(array $fields, string $prefix = ''): array
  {
    $rules = [];
    foreach ($fields as $field => [, $max]) {
      $rules[$prefix.$field] = $max === null ? ['required', 'boolean'] : ['required', 'integer', 'min:1', 'max:'.$max];
    }

    return $rules;
  }

  private static function payload(array $fields, array $input, string $prefix = ''): array
  {
    $values = [];
    foreach ($fields as $field => [$setting]) {
      $values[$setting] = $input[$prefix.$field];
    }

    return $values;
  }
}
