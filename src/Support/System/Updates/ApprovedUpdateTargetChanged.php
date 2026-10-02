<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

class ApprovedUpdateTargetChanged extends UpdateException
{
  public function __construct()
  {
    parent::__construct('The installed version or offered release changed. Check updates and obtain approval again.');
  }
}
