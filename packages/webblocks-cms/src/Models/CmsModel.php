<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use WebBlocks\Cms\Support\Database\CmsTable;

abstract class CmsModel extends Model
{
  public function getTable()
  {
    return CmsTable::name(parent::getTable());
  }
}
