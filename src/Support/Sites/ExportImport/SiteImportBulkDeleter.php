<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;

class SiteImportBulkDeleter
{
  public function __construct(
  private readonly SiteImportManager $siteImportManager,
  ) {}

  public function deleteSelected(array $ids): SelectedBulkDeleteResult
  {
  $requestedIds = collect($ids)
      ->map(fn (mixed $id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

  $imports = SiteImport::query()
      ->whereIn('id', $requestedIds)
      ->get()
      ->keyBy('id');

  $deleted = [];
  $failed = [];

  foreach ($requestedIds as $id) {
      $siteImport = $imports->get($id);

      if (! $siteImport) {
    $failed[] = ['id' => $id, 'message' => 'not found'];

    continue;
      }

      try {
    $this->siteImportManager->delete($siteImport);
    $deleted[] = $id;
      } catch (Throwable $throwable) {
    Log::warning('Site import bulk delete failed.', [
          'site_import_id' => $id,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
    ]);

    $failed[] = ['id' => $id, 'message' => 'delete failed; check system logs'];
      }
  }

  return new SelectedBulkDeleteResult('site import', 'site imports', $deleted, $failed);
  }
}
