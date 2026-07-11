<?php

namespace WebBlocks\Cms\Support\Admin;

use WebBlocks\Cms\Support\System\SystemSettings;

class AdminPagination
{
  public static function perPage(): int
  {
    return app(SystemSettings::class)->adminListingPerPage();
  }
}
