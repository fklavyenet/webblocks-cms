<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use RuntimeException;
use ZipArchive;

class ImportArchiveInspector
{
  public function __construct(
    private readonly SiteTransferPathGuard $pathGuard,
  ) {}

  public function inspect(string $archivePath): SiteImportInspection
  {
    $archive = new ZipArchive;
    $result = $archive->open($archivePath);

    if ($result !== true) {
      throw new RuntimeException('Import package could not be opened.');
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

        $this->pathGuard->assertSafeRelativePath($trimmed, 'Archive entry path');
      }

      $manifest = $this->decodeJsonFile($archive, 'manifest.json');

      if (($manifest['product'] ?? null) !== SiteTransferPackage::PRODUCT) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.site_import_product_unsupported'));
      }

      if (($manifest['package_type'] ?? null) !== SiteTransferPackage::PACKAGE_TYPE) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.site_import_type_unsupported'));
      }

      if ((int) ($manifest['format_version'] ?? 0) !== SiteTransferPackage::FORMAT_VERSION) {
        throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.site_import_format_unsupported'));
      }

      foreach (SiteTransferPackage::REQUIRED_DATA_FILES as $file) {
        $this->decodeJsonFile($archive, $file);
      }

      $includesMedia = (bool) ($manifest['includes_media'] ?? false);

      return new SiteImportInspection(
        manifest: $manifest,
        includesMedia: $includesMedia,
      );
    } finally {
      $archive->close();
    }
  }

  public function decodeJsonFile(ZipArchive $archive, string $path): array
  {
    $resolvedPath = $this->resolveJsonPath($archive, $path);

    if ($resolvedPath === null) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.site_import_entry_missing', ['path' => $path]));
    }

    $contents = $archive->getFromName($resolvedPath);

    if (! is_string($contents) || trim($contents) === '') {
      throw new RuntimeException($resolvedPath.' is empty.');
    }

    $decoded = json_decode($contents, true);

    if (! is_array($decoded)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.panel_copy.site_import_entry_invalid_json', ['path' => $resolvedPath]));
    }

    return $decoded;
  }

  private function resolveJsonPath(ZipArchive $archive, string $path): ?string
  {
    if ($archive->locateName($path) !== false) {
      return $path;
    }

    foreach (SiteTransferPackage::DATA_FILE_ALIASES[$path] ?? [] as $alias) {
      if ($archive->locateName($alias) !== false) {
        return $alias;
      }
    }

    return null;
  }
}
