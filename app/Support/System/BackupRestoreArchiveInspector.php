<?php

namespace App\Support\System;

use RuntimeException;
use ZipArchive;

class BackupRestoreArchiveInspector
{
    public function inspect(string $archivePath): BackupRestoreInspection
    {
        $archive = new ZipArchive;
        $result = $archive->open($archivePath);

        if ($result !== true) {
            throw new RuntimeException('Backup archive could not be opened for restore validation.');
        }

        try {
            if ($archive->locateName('manifest.json') === false) {
                throw new RuntimeException('Backup archive is missing manifest.json.');
            }

            if ($archive->locateName('database/database.sql') === false) {
                throw new RuntimeException('Backup archive is missing database/database.sql.');
            }

            $manifestContents = $archive->getFromName('manifest.json');

            if (! is_string($manifestContents) || trim($manifestContents) === '') {
                throw new RuntimeException('Backup archive manifest.json is empty.');
            }

            $manifest = json_decode($manifestContents, true);

            if (! is_array($manifest)) {
                throw new RuntimeException('Backup archive manifest.json is not valid JSON.');
            }

            $manifestIncludesUploads = (bool) data_get($manifest, 'included_parts.uploads', false);
            $archiveHasUploads = $this->archiveHasPath($archive, 'uploads/public/');

            if ($manifestIncludesUploads && ! $archiveHasUploads) {
                throw new RuntimeException('Backup archive manifest says uploads are included, but uploads/public is missing.');
            }

            return new BackupRestoreInspection(
                manifest: $manifest,
                includesDatabase: true,
                includesUploads: $manifestIncludesUploads || $archiveHasUploads,
                databaseSqlPath: 'database/database.sql',
                uploadsRootPath: $manifestIncludesUploads || $archiveHasUploads ? 'uploads/public' : null,
            );
        } finally {
            $archive->close();
        }
    }

    private function archiveHasPath(ZipArchive $archive, string $path): bool
    {
        if ($archive->locateName($path) !== false) {
            return true;
        }

        $prefix = rtrim($path, '/').'/';

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
