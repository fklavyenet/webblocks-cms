<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use App\Models\SystemBackupRestore;

class BackupRestoreResult
{
    public function __construct(
        public readonly ?SystemBackup $sourceBackup,
        public readonly string $sourceArchivePath,
        public readonly string $sourceArchiveFilename,
        public readonly BackupRestoreInspection $inspection,
        public readonly ?SystemBackup $safetyBackup,
        public readonly array $output,
        public readonly ?SystemBackupRestore $restoreRecord,
    ) {}

    public function summary(): string
    {
        $parts = implode(' + ', $this->inspection->restoredParts());

        return 'Restore completed from '.$this->sourceArchiveFilename.' for '.($parts !== '' ? $parts : 'no parts').'.';
    }
}
