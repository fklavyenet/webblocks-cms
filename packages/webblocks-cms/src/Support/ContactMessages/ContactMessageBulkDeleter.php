<?php

namespace WebBlocks\Cms\Support\ContactMessages;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class ContactMessageBulkDeleter
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
  ) {}

  public function deleteSelected(User $user, array $ids): SelectedBulkDeleteResult
  {
    $ids = collect($ids)
      ->map(fn ($id): int => (int) $id)
      ->filter(fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

    $messages = $this->authorization
      ->scopeContactMessagesForUser(ContactMessage::query(), $user)
      ->whereIn('id', $ids)
      ->get()
      ->keyBy('id');

    $deletedIds = [];
    $failed = [];

    foreach ($ids as $id) {
      $message = $messages->get($id);

      if (! $message instanceof ContactMessage) {
        $failed[] = [
          'id' => $id,
          'message' => 'The message is no longer available to this user.',
        ];

        continue;
      }

      try {
        $message->delete();
        $deletedIds[] = $id;
      } catch (Throwable $throwable) {
        Log::warning('Contact message could not be deleted during bulk delete.', [
          'contact_message_id' => $id,
          'exception' => $throwable::class,
          'message' => $throwable->getMessage(),
        ]);

        $failed[] = [
          'id' => $id,
          'message' => 'Review the logs for details.',
        ];
      }
    }

    return new SelectedBulkDeleteResult('message', 'messages', $deletedIds, $failed);
  }
}
