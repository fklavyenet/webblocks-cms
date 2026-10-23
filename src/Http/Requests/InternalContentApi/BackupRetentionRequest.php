<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use WebBlocks\Cms\Http\Requests\Admin\SystemBackupRetentionRequest;

class BackupRetentionRequest extends SystemBackupRetentionRequest
{
  public function authorize(): bool
  {
    return true; // Installation-wide backups.settings.write capability middleware.
  }
}
