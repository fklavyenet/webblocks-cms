<?php

namespace WebBlocks\Cms\Models;

class AdminUpdateOperation extends CmsModel
{
  protected $table = 'wbcms_admin_update_operations';

  public $incrementing = false;

  protected $keyType = 'string';

  protected $guarded = [];

  protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'heartbeat_at' => 'datetime'];
}
