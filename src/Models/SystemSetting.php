<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemSetting extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'key',
    'value',
  ];
}
