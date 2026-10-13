<?php

namespace WebBlocks\Cms\Models;

class SitePromotionRecord extends CmsModel
{
  protected $table = 'wbcms_site_promotion_plans';

  protected $guarded = [];

  protected function casts(): array
  {
    return ['plan' => 'array'];
  }
}
