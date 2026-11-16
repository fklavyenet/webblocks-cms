<?php

namespace WebBlocks\Cms\Support\System;

use RuntimeException;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferPackage;
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
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.validation_open'));
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
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.manifest_missing'));
      }

      $manifestContents = $archive->getFromName('manifest.json');

      if (! is_string($manifestContents) || trim($manifestContents) === '') {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.manifest_empty'));
      }

      $manifest = json_decode($manifestContents, true);

      if (! is_array($manifest)) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.manifest_invalid'));
      }

      if (($manifest['product'] ?? null) === SiteTransferPackage::PRODUCT
        && ($manifest['package_type'] ?? null) === SiteTransferPackage::PACKAGE_TYPE) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.site_package'));
      }

      $this->assertValidBackupManifest($manifest);

      if ($archive->locateName('database/database.sql') === false) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.entry_missing', ['entry' => 'database/database.sql']));
      }

      $this->sqlDumpContentValidator->assertValidArchiveEntry($archive, 'database/database.sql');

      $manifestIncludesUploads = (bool) data_get($manifest, 'included_parts.uploads', false);
      $archiveHasUploads = $this->archiveHasPath($archive, 'uploads/public/');
      $manifestIncludesSitePublic = (bool) data_get($manifest, 'included_parts.site_public', false);
      $archiveHasSitePublic = $this->archiveHasPath($archive, 'site-public/');

      if ($manifestIncludesUploads && ! $archiveHasUploads) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.uploads_missing'));
      }

      if ($manifestIncludesSitePublic && ! $archiveHasSitePublic) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.site_files_missing'));
      }

      return new BackupRestoreInspection(
        manifest: $manifest,
        includesDatabase: true,
        includesUploads: $manifestIncludesUploads || $archiveHasUploads,
        databaseSqlPath: 'database/database.sql',
        uploadsRootPath: $manifestIncludesUploads || $archiveHasUploads ? 'uploads/public' : null,
        includesSitePublic: $manifestIncludesSitePublic || $archiveHasSitePublic,
        sitePublicRootPath: $manifestIncludesSitePublic || $archiveHasSitePublic ? 'site-public' : null,
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
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.entry_invalid'));
    }

    $normalized = str_replace('\\', '/', trim($path));

    if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.entry_invalid'));
    }

    foreach (explode('/', $normalized) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.entry_invalid'));
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
        throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.manifest_unsupported'));
      }

      return;
    }

    if ($product !== SystemBackupArchivePackage::PRODUCT) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.product_unsupported'));
    }

    if ($packageType !== SystemBackupArchivePackage::PACKAGE_TYPE) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.package_type_unsupported'));
    }

    if ((int) $formatVersion !== SystemBackupArchivePackage::FORMAT_VERSION) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.format_unsupported'));
    }

    if ((int) $featureVersion !== SystemBackupArchivePackage::FEATURE_VERSION) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.feature_unsupported'));
    }
  }
}
