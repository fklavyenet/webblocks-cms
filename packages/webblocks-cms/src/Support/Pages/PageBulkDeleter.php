<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageBulkDeleter
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageDeleter $pageDeleter,
  ) {}

  public function deleteSelected(User $user, array $ids): SelectedBulkDeleteResult
  {
    $requestedIds = collect($ids)
      ->map(fn (mixed $id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

    $pages = $this->authorization->scopePagesForUser(Page::query(), $user)
      ->whereIn('id', $requestedIds)
      ->get()
      ->keyBy('id');

    $deleted = [];
    $failed = [];

    foreach ($requestedIds as $id) {
      $page = $pages->get($id);

      if (! $page) {
        $failed[] = ['id' => $id, 'message' => 'not accessible in your assigned site scope'];
        continue;
      }

      try {
        $this->pageDeleter->delete($page);
        $deleted[] = $id;
      } catch (Throwable $throwable) {
        Log::warning('Page bulk delete failed.', [
          'page_id' => $id,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
        ]);

        $failed[] = ['id' => $id, 'message' => 'delete failed; check system logs'];
      }
    }

    return new SelectedBulkDeleteResult('page', 'pages', $deleted, $failed);
  }
}
