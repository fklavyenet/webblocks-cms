<?php

namespace WebBlocks\Cms\Actions\ContactMessages;

use App\Models\User;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class UpdateSelectedContactMessageReadStatus
{
  public function __construct(private readonly AdminAuthorization $authorization) {}

  public function execute(User $user, array $ids, string $status): int
  {
    return $this->authorization->scopeContactMessageStatusUpdatesForUser(ContactMessage::query(), $user)
      ->whereKey($ids)
      ->where('status', $status === 'read' ? 'new' : 'read')
      ->update(['status' => $status]);
  }
}
