<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class BackupRetentionSchema
{
  public static function ready(): bool
  {
    return Schema::hasColumns('wbcms_system_backups', ['is_protected', 'retention_mode', 'expires_at']);
  }

  public static function migrate(): void
  {
    if (! Schema::hasTable('wbcms_system_backups')) {
      return;
    }
    foreach (['is_protected', 'retention_mode', 'expires_at'] as $column) {
      if (! Schema::hasColumn('wbcms_system_backups', $column)) {
        Schema::table('wbcms_system_backups', function (Blueprint $table) use ($column): void {
          match ($column) {
            'is_protected' => $table->boolean($column)->default(false),
            'retention_mode' => $table->string($column, 16)->default('policy'),
            'expires_at' => $table->dateTime($column)->nullable(),
          };
        });
      }
    }
  }
}
