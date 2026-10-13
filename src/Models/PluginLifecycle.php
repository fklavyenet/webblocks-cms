<?php

namespace WebBlocks\Cms\Models;

class PluginLifecycle extends CmsModel
{
  public $timestamps = false;

  protected $guarded = [];

  protected function casts(): array
  {
    return ['setup_result' => 'array', 'previous_state' => 'array', 'legacy_state' => 'array'];
  }
}
