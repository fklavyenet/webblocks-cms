<?php

namespace App\Support\Sites\ExportImport;

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
                throw new RuntimeException('Import package product is not supported.');
            }

            if (($manifest['package_type'] ?? null) !== SiteTransferPackage::PACKAGE_TYPE) {
                throw new RuntimeException('Import package type is not supported.');
            }

            if ((int) ($manifest['format_version'] ?? 0) !== SiteTransferPackage::FORMAT_VERSION) {
                throw new RuntimeException('Import package format version is not supported.');
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
        if ($archive->locateName($path) === false) {
            throw new RuntimeException('Import package is missing '.$path.'.');
        }

        $contents = $archive->getFromName($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException($path.' is empty.');
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException($path.' is not valid JSON.');
        }

        return $decoded;
    }
}
