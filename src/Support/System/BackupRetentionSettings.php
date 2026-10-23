<?php

namespace WebBlocks\Cms\Support\System;

final class BackupRetentionSettings
{
  public static function rules(): array
  {
    return [
      'manual_enabled' => ['sometimes', 'boolean'],
      'manual_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
      'keep_latest_manual' => ['sometimes', 'integer', 'min:1', 'max:100'],
      'keep_latest_restore_safety' => ['sometimes', 'integer', 'min:1', 'max:100'],
      'keep_latest_content_apply' => ['sometimes', 'integer', 'min:1', 'max:100'],
      'budget_gb' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:10240'],
    ];
  }

  public static function payload(array $validated): array
  {
    $values = [];
    foreach (SystemSettings::BACKUP_LIFECYCLE_KEYS as $field => $key) {
      if (array_key_exists($field, $validated)) {
        $values[$key] = $validated[$field] ?? 0;
      }
    }

    return $values;
  }
}
