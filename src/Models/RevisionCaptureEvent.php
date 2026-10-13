<?php

namespace WebBlocks\Cms\Models;

class RevisionCaptureEvent extends CmsModel
{
  protected $guarded = ['id'];

  protected function casts(): array
  {
    return ['snapshot_created' => 'boolean'];
  }
}
