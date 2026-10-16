<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;

final class AutomaticPageAdoption extends Model
{
  protected $table = 'wbcms_automatic_page_adoptions';

  protected $guarded = ['id'];
}
