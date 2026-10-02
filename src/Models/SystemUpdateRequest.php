<?php

namespace WebBlocks\Cms\Models;

class SystemUpdateRequest extends CmsModel
{
  public $incrementing = false;

  protected $keyType = 'string';

  protected $guarded = [];

  protected function casts(): array
  {
    return ['result' => 'array', 'finished_at' => 'datetime'];
  }
}
