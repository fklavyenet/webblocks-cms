<?php

namespace App\Support\System;

class BackupRestoreInspection
{
    public function __construct(
        public readonly array $manifest,
        public readonly bool $includesDatabase,
        public readonly bool $includesUploads,
        public readonly string $databaseSqlPath,
        public readonly ?string $uploadsRootPath,
    ) {}

    public function restoredParts(): array
    {
        return array_values(array_filter([
            $this->includesDatabase ? 'database' : null,
            $this->includesUploads ? 'uploads' : null,
        ]));
    }
}
