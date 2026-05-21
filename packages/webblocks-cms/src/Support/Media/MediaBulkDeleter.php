<?php

namespace WebBlocks\Cms\Support\Media;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class MediaBulkDeleter
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly MediaDeleter $mediaDeleter,
  ) {}

  public function deleteSelected(User $user, array $ids): SelectedBulkDeleteResult
  {
    $ids = collect($ids)
      ->map(fn ($id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

    $mediaItems = $this->authorization
      ->scopeMediaForUser(Media::query(), $user)
      ->whereIn('id', $ids)
      ->get()
      ->keyBy('id');

    $deletedIds = [];
    $failed = [];

    foreach ($ids as $id) {
      $media = $mediaItems->get($id);

      if (! $media instanceof Media) {
        $failed[] = [
          'id' => $id,
          'message' => 'The media item is no longer available to this user.',
        ];

        continue;
      }

      try {
        $this->mediaDeleter->delete($media);
        $deletedIds[] = $id;
      } catch (MediaInUseException) {
        $failed[] = [
          'id' => $id,
          'message' => 'Media is in use.',
        ];
      } catch (Throwable $throwable) {
        Log::warning('Media could not be deleted during bulk delete.', [
          'media_id' => $id,
          'disk' => $media->disk,
          'path' => $media->path,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
        ]);

        $failed[] = [
          'id' => $id,
          'message' => 'Review the logs for details.',
        ];
      }
    }

    return new SelectedBulkDeleteResult('media item', 'media items', $deletedIds, $failed);
  }
}
