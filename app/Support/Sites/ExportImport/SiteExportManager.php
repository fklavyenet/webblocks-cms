<?php

namespace App\Support\Sites\ExportImport;

use App\Models\Site;
use App\Models\SiteExport;
use App\Support\System\InstalledVersionStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SiteExportManager
{
    public const ARCHIVE_DISK = 'site-transfers';

    public function __construct(
        private readonly SiteExportDataBuilder $dataBuilder,
        private readonly ExportArchiveBuilder $archiveBuilder,
        private readonly InstalledVersionStore $installedVersionStore,
        private readonly SiteTransferPathGuard $pathGuard,
    ) {}

    public function export(Site $site, bool $includesMedia, ?int $userId = null): SiteExport
    {
        $export = SiteExport::query()->create([
            'site_id' => $site->id,
            'user_id' => $userId,
            'status' => SiteExport::STATUS_RUNNING,
            'includes_media' => $includesMedia,
            'archive_disk' => self::ARCHIVE_DISK,
        ]);

        $output = [];

        try {
            $payload = $this->dataBuilder->build($site, $includesMedia);
            $timestamp = now();
            $archiveName = sprintf('webblocks-cms-site-export-%s-%s.zip', $site->handle, $timestamp->format('Y-m-d-His'));
            $archivePath = $timestamp->format('exports/Y/m/d/').Str::lower(Str::random(8)).'-'.$archiveName;
            $manifest = $this->manifestFor($site, $payload, $includesMedia, $timestamp);
            $size = $this->archiveBuilder->build(Storage::disk(self::ARCHIVE_DISK)->path($archivePath), $manifest, $payload, $includesMedia, $output);

            $export->forceFill([
                'status' => SiteExport::STATUS_COMPLETED,
                'archive_path' => $archivePath,
                'archive_name' => $archiveName,
                'archive_size_bytes' => $size,
                'summary_json' => $payload['counts'],
                'manifest_json' => $manifest,
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => null,
            ])->save();

            return $export->fresh(['site', 'user']);
        } catch (Throwable $throwable) {
            $output[] = 'Export failed: '.$throwable->getMessage();

            $export->forceFill([
                'status' => SiteExport::STATUS_FAILED,
                'output_log' => implode(PHP_EOL, $output),
                'failure_message' => $throwable->getMessage(),
            ])->save();

            throw new RuntimeException($throwable->getMessage(), previous: $throwable);
        }
    }

    public function downloadResponse(SiteExport $siteExport): BinaryFileResponse
    {
        if (! $siteExport->isCompleted() || ! $siteExport->archive_path || ! $siteExport->archive_name) {
            abort(404);
        }

        $this->pathGuard->assertSafeRelativePath($siteExport->archive_path, 'Export archive path');
        $disk = Storage::disk($siteExport->archive_disk ?: self::ARCHIVE_DISK);
        $path = $disk->path($siteExport->archive_path);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $siteExport->archive_name, ['Content-Type' => 'application/zip']);
    }

    public function delete(SiteExport $siteExport): void
    {
        if ($siteExport->archive_path) {
            $this->pathGuard->assertSafeRelativePath($siteExport->archive_path, 'Export archive path');
            Storage::disk($siteExport->archive_disk ?: self::ARCHIVE_DISK)->delete($siteExport->archive_path);
        }

        $siteExport->delete();
    }

    private function manifestFor(Site $site, array $payload, bool $includesMedia, $timestamp): array
    {
        return [
            'product' => SiteTransferPackage::PRODUCT,
            'package_type' => SiteTransferPackage::PACKAGE_TYPE,
            'feature_version' => SiteTransferPackage::FEATURE_VERSION,
            'format_version' => SiteTransferPackage::FORMAT_VERSION,
            'exported_at' => $timestamp->toIso8601String(),
            'source_app_version' => $this->installedVersionStore->currentVersion(),
            'source_site_id' => $site->id,
            'source_site_name' => $site->name,
            'source_site_handle' => $site->handle,
            'source_site_domain' => $site->domain,
            'locales' => collect($payload['locales'])->pluck('code')->values()->all(),
            'counts_summary' => $payload['counts'],
            'includes_media' => $includesMedia,
        ];
    }
}
