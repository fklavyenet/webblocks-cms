<?php

namespace WebBlocks\Cms\Actions\Applications;

use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;

class DeleteEmbeddedApplication
{
  public function handle(EmbeddedApplication $application): void
  {
    $used = Block::query()->where('settings->application_handle', $application->handle)->exists();
    if ($used) {
      throw ValidationException::withMessages(['application' => 'Disable applications that are in use; remove their blocks before deleting them.']);
    }

    $application->delete();
  }
}
