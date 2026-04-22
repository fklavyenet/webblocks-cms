<?php

namespace App\Support\Sites\ExportImport;

use App\Models\SiteImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class SiteImportManager
{
    public const ARCHIVE_DISK = 'site-transfers';

    public function __construct(
        private readonly ImportArchiveInspector $archiveInspector,
        private readonly ImportDataMapper $dataMapper,
        private readonly SiteTransferPathGuard $pathGuard,
    ) {}

    public function inspectUpload(UploadedFile $file, ?int $userId = null): SiteImport
    {
        $import = SiteImport::query()->create([
            'user_id' => $userId,
            'status' => SiteImport::STATUS_RUNNING,
            'source_archive_name' => $file->getClientOriginalName(),
            'archive_disk' => self::ARCHIVE_DISK,
        ]);

        $output = [];

        try {
            $originalName = trim($file->getClientOriginalName());
            $archiveName = Str::lower(Str::random(8)).'-'.($originalName !== '' ? $originalName : 'import-package.zip');
            $archivePath = $file->storeAs(now()->format('imports/Y/m/d'), $archiveName, self::ARCHIVE_DISK);
            $inspection = $this->archiveInspector->inspect(Storage::disk(self::ARCHIVE_DISK)->path($archivePath));

            $output[] = 'Import package validated successfully.';

            $import->forceFill([
                'status' => SiteImport::STATUS_VALIDATED,
                'archive_path' => $archivePath,
                'manifest_json' => $inspection->manifest,
                'summary_json' => $inspection->counts(),
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => null,
            ])->save();

            return $import->fresh(['targetSite', 'user']);
        } catch (Throwable $throwable) {
            $output[] = 'Import validation failed: '.$throwable->getMessage();
            $import->forceFill([
                'status' => SiteImport::STATUS_FAILED,
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => $throwable->getMessage(),
            ])->save();

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        }
    }

    public function import(SiteImport $siteImport, SiteImportOptions $options): SiteImport
    {
        if (! $siteImport->archive_path) {
            throw new RuntimeException('Import package archive is missing.');
        }

        $this->pathGuard->assertSafeRelativePath($siteImport->archive_path, 'Import archive path');
        $archivePath = Storage::disk($siteImport->archive_disk ?: self::ARCHIVE_DISK)->path($siteImport->archive_path);
        $inspection = $this->archiveInspector->inspect($archivePath);
        $output = array_filter(explode(PHP_EOL, (string) $siteImport->output_log));
        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException('Import package could not be reopened.');
        }

        try {
            $payload = $this->loadPayload($archive);
            $site = $this->dataMapper->import($siteImport, $options, $archive, $payload, $output);

            $siteImport->forceFill([
                'status' => SiteImport::STATUS_COMPLETED,
                'target_site_id' => $site->id,
                'imported_site_handle' => $site->handle,
                'imported_site_domain' => $site->domain,
                'manifest_json' => $inspection->manifest,
                'summary_json' => $inspection->counts(),
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => null,
            ])->save();

            return $siteImport->fresh(['targetSite', 'user']);
        } catch (Throwable $throwable) {
            $output[] = 'Import failed: '.$throwable->getMessage();
            $siteImport->forceFill([
                'status' => SiteImport::STATUS_FAILED,
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => $throwable->getMessage(),
            ])->save();

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        } finally {
            $archive->close();
        }
    }

    public function delete(SiteImport $siteImport): void
    {
        if ($siteImport->archive_path) {
            $this->pathGuard->assertSafeRelativePath($siteImport->archive_path, 'Import archive path');
            Storage::disk($siteImport->archive_disk ?: self::ARCHIVE_DISK)->delete($siteImport->archive_path);
        }

        $siteImport->delete();
    }

    private function loadPayload(ZipArchive $archive): array
    {
        $payload = [];

        foreach (SiteTransferPackage::REQUIRED_DATA_FILES as $file) {
            $payload[pathinfo($file, PATHINFO_FILENAME)] = $this->archiveInspector->decodeJsonFile($archive, $file);
        }

        return $payload;
    }
}
