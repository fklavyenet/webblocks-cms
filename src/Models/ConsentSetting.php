<?php

namespace WebBlocks\Cms\Models;

class ConsentSetting extends CmsModel
{
  protected $table = 'wbcms_consent_settings';

  protected $primaryKey = 'site_id';

  public $incrementing = false;

  protected $fillable = ['site_id', 'enabled', 'lifetime_days', 'privacy_url', 'position'];

  protected static function booted(): void
  {
    $clear = fn (self $record) => request()->attributes->remove('cms.consent.settings.'.$record->site_id);
    static::saved($clear);
    static::deleted($clear);
  }

  protected function casts(): array
  {
    return ['enabled' => 'boolean', 'lifetime_days' => 'integer'];
  }
}
