<?php

namespace WebBlocks\Cms\Actions\Applications;

use WebBlocks\Cms\Models\EmbeddedApplication;

class CreateEmbeddedApplication
{
  public function handle(array $data, ?int $userId): EmbeddedApplication
  {
    return EmbeddedApplication::query()->create($data + ['created_by_user_id' => $userId, 'updated_by_user_id' => $userId]);
  }
}
