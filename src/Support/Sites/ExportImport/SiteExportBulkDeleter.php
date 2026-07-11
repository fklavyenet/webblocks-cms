<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SiteExport;
use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;

class SiteExportBulkDeleter
{
  public function __construct(
  private readonly SiteExportManager $siteExportManager,
  ) {}

  public function deleteSelected(array $ids): SelectedBulkDeleteResult
  {
  $requestedIds = collect($ids)
      ->map(fn (mixed $id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

  $exports = SiteExport::query()
      ->whereIn('id', $requestedIds)
      ->get()
      ->keyBy('id');

  $deleted = [];
  $failed = [];

  foreach ($requestedIds as $id) {
      $siteExport = $exports->get($id);

      if (! $siteExport) {
    $failed[] = ['id' => $id, 'message' => 'not found'];

    continue;
      }

      try {
    $this->siteExportManager->delete($siteExport);
    $deleted[] = $id;
      } catch (Throwable $throwable) {
    Log::warning('Site export bulk delete failed.', [
          'site_export_id' => $id,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
    ]);

    $failed[] = ['id' => $id, 'message' => 'delete failed; check system logs'];
      }
  }

  return new SelectedBulkDeleteResult('site export', 'site exports', $deleted, $failed);
  }
}
