<?php

namespace WebBlocks\Cms\Actions\Applications;

use WebBlocks\Cms\Models\EmbeddedApplication;

class UpdateEmbeddedApplication
{
  public function handle(EmbeddedApplication $application, array $data, ?int $userId): EmbeddedApplication
  {
    $application->update($data + ['updated_by_user_id' => $userId]);

    return $application->refresh();
  }
}
