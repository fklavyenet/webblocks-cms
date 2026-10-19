<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class CmsRole extends Model
{
  protected $table = 'wbcms_acl_roles';

  protected $guarded = ['id'];

  protected $casts = ['is_protected' => 'boolean', 'version' => 'integer'];
}
