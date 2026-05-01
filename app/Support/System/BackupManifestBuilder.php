<?php

namespace App\Support\System;

use App\Models\SystemBackup;

class BackupManifestBuilder
{
    public function build(SystemBackup $backup, array $databaseMeta, string $archiveFilename): array
    {
        return [
            'product' => SystemBackupArchivePackage::PRODUCT,
            'package_type' => SystemBackupArchivePackage::PACKAGE_TYPE,
            'feature_version' => SystemBackupArchivePackage::FEATURE_VERSION,
            'format_version' => SystemBackupArchivePackage::FORMAT_VERSION,
            'app_name' => config('app.name'),
            'app_version' => config('app.version'),
            'backup_id' => $backup->id,
            'backup_type' => $backup->type,
            'created_at' => $backup->started_at?->toIso8601String(),
            'db_driver' => $databaseMeta['driver'] ?? config('database.default'),
            'database_dump_strategy' => $databaseMeta['strategy'] ?? 'unknown',
            'included_parts' => [
                'database' => $backup->includes_database,
                'uploads' => $backup->includes_uploads,
            ],
            'uploads_root' => 'storage/app/public',
            'archive_format' => 'zip',
            'archive_filename' => $archiveFilename,
        ];
    }
}
