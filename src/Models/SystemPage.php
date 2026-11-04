<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemPage extends CmsModel
{
  protected $fillable = ['site_id', 'key', 'page_id'];

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class);
  }
}
