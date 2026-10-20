<?php

namespace WebBlocks\Cms\Tests\Support;

use WebBlocks\Cms\Tests\CmsSqlTestUser;

class MaintenanceTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
    return 'user_id';
  }
}
