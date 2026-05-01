<?php

namespace App\Support\System;

use App\Support\Sites\ExportImport\SiteTransferPackage;
use RuntimeException;
use ZipArchive;

class BackupRestoreArchiveInspector
{
    public function __construct(
        private readonly SqlDumpContentValidator $sqlDumpContentValidator,
    ) {}

    public function inspect(string $archivePath): BackupRestoreInspection
    {
        $archive = new ZipArchive;
        $result = $archive->open($archivePath);

        if ($result !== true) {
            throw new RuntimeException('Backup archive could not be opened for restore validation.');
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name)) {
                    continue;
                }

                $trimmed = rtrim($name, '/');

                if ($trimmed === '') {
                    continue;
                }

                $this->assertSafeArchiveEntryPath($trimmed);
            }

            if ($archive->locateName('manifest.json') === false) {
                throw new RuntimeException('Backup archive is missing manifest.json.');
            }

            $manifestContents = $archive->getFromName('manifest.json');

            if (! is_string($manifestContents) || trim($manifestContents) === '') {
                throw new RuntimeException('Backup archive manifest.json is empty.');
            }

            $manifest = json_decode($manifestContents, true);

            if (! is_array($manifest)) {
                throw new RuntimeException('Backup archive manifest.json is not valid JSON.');
            }

            if (($manifest['product'] ?? null) === SiteTransferPackage::PRODUCT
                && ($manifest['package_type'] ?? null) === SiteTransferPackage::PACKAGE_TYPE) {
                throw new RuntimeException('This archive is a site export/import package, not a WebBlocks CMS backup archive.');
            }

            $this->assertValidBackupManifest($manifest);

            if ($archive->locateName('database/database.sql') === false) {
                throw new RuntimeException('Backup archive is missing database/database.sql.');
            }

            $this->sqlDumpContentValidator->assertValidArchiveEntry($archive, 'database/database.sql');

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

    private function assertSafeArchiveEntryPath(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException('Backup archive contains an invalid entry path.');
        }

        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new RuntimeException('Backup archive contains an invalid entry path.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Backup archive contains an invalid entry path.');
            }
        }
    }

    private function assertValidBackupManifest(array $manifest): void
    {
        $packageType = $manifest['package_type'] ?? null;
        $formatVersion = $manifest['format_version'] ?? null;
        $featureVersion = $manifest['feature_version'] ?? null;
        $product = $manifest['product'] ?? null;
        $isLegacyBackupManifest = array_key_exists('backup_id', $manifest)
            || array_key_exists('backup_type', $manifest)
            || array_key_exists('included_parts', $manifest)
            || array_key_exists('archive_format', $manifest);

        if ($packageType === null && $formatVersion === null && $featureVersion === null && $product === null) {
            if (! $isLegacyBackupManifest) {
                throw new RuntimeException('Backup archive manifest metadata is not supported.');
            }

            return;
        }

        if ($product !== SystemBackupArchivePackage::PRODUCT) {
            throw new RuntimeException('Backup archive product is not supported.');
        }

        if ($packageType !== SystemBackupArchivePackage::PACKAGE_TYPE) {
            throw new RuntimeException('Backup archive package type is not supported.');
        }

        if ((int) $formatVersion !== SystemBackupArchivePackage::FORMAT_VERSION) {
            throw new RuntimeException('Backup archive format version is not supported.');
        }

        if ((int) $featureVersion !== SystemBackupArchivePackage::FEATURE_VERSION) {
            throw new RuntimeException('Backup archive feature version is not supported.');
        }
    }
}
