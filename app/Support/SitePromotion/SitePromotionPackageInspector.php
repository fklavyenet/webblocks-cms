<?php

namespace App\Support\SitePromotion;

use App\Support\Sites\ExportImport\SiteTransferPackage;
use App\Support\Sites\ExportImport\SiteTransferPathGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SitePromotionPackageInspector
{
    public const DISK = 'site-promotions';

    public function __construct(
        private readonly SiteTransferPathGuard $pathGuard,
        private readonly SitePromotionPreservePolicy $preservePolicy,
    ) {}

    public function inspectUpload(UploadedFile $file): SitePromotionPackageInspection
    {
        $originalName = trim($file->getClientOriginalName()) ?: 'site-promotion.zip';
        $archiveName = Str::lower(Str::random(8)).'-'.$originalName;
        $archivePath = $file->storeAs(now()->format('uploads/Y/m/d'), $archiveName, self::DISK);

        return $this->inspectStoredArchive($archivePath, $originalName);
    }

    public function inspectStoredArchive(string $archivePath, ?string $archiveName = null): SitePromotionPackageInspection
    {
        $this->pathGuard->assertSafeRelativePath($archivePath, 'Promotion archive path');

        $disk = Storage::disk(self::DISK);
        $absolutePath = $disk->path($archivePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Promotion package archive was not found.');
        }

        $archive = new ZipArchive;
        $result = $archive->open($absolutePath);

        if ($result !== true) {
            throw new RuntimeException('Promotion package could not be opened.');
        }

        try {
            $entryNames = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name)) {
                    continue;
                }

                $trimmed = rtrim($name, '/');

                if ($trimmed === '') {
                    continue;
                }

                $this->pathGuard->assertSafeRelativePath($trimmed, 'Promotion archive entry path');
                $entryNames[] = $trimmed;
            }

            $manifest = $this->decodeJsonFile($archive, 'manifest.json');

            if (($manifest['product'] ?? null) !== SiteTransferPackage::PRODUCT) {
                throw new RuntimeException('Promotion package product is not supported.');
            }

            if (! in_array((string) ($manifest['package_type'] ?? ''), ['site_export', 'site_promotion'], true)) {
                throw new RuntimeException('Promotion package type is not supported.');
            }

            if ((int) ($manifest['format_version'] ?? 0) !== SiteTransferPackage::FORMAT_VERSION) {
                throw new RuntimeException('Promotion package format version is not supported.');
            }

            $payload = [];

            foreach (SiteTransferPackage::REQUIRED_DATA_FILES as $file) {
                $payload[pathinfo($file, PATHINFO_FILENAME)] = $this->decodeJsonFile($archive, $file);
            }

            foreach (SiteTransferPackage::OPTIONAL_DATA_FILES as $file) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                $payload[$key] = $archive->locateName($file) === false
                    ? []
                    : $this->decodeJsonFile($archive, $file);
            }

            $errors = [];

            foreach ($this->preservePolicy->blockedArchiveEntries() as $blockedEntry) {
                if (in_array($blockedEntry, $entryNames, true)) {
                    $errors[] = 'Promotion package contains unsupported preserved data entry ['.$blockedEntry.'].';
                }
            }

            return new SitePromotionPackageInspection(
                archiveDisk: self::DISK,
                archivePath: $archivePath,
                archiveName: $archiveName ?: basename($archivePath),
                manifest: $manifest,
                payload: $payload,
                includesAssets: (bool) ($manifest['includes_media'] ?? false),
                warnings: [],
                errors: $errors,
            );
        } finally {
            $archive->close();
        }
    }

    public function decodeJsonFile(ZipArchive $archive, string $path): array
    {
        if ($archive->locateName($path) === false) {
            throw new RuntimeException('Promotion package is missing '.$path.'.');
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
