<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Support\Facades\Schema;

class PublicSearchSchema
{
  public function tableExists(): bool
  {
    return Schema::hasTable('wbcms_public_search_index');
  }
}
