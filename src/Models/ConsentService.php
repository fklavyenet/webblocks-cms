<?php

namespace WebBlocks\Cms\Models;

class ConsentService extends CmsModel
{
  protected $table = 'wbcms_consent_services';

  protected $fillable = ['site_id', 'handle', 'provider', 'category', 'name', 'description', 'privacy_url', 'script_url', 'enabled', 'revision'];

  protected static function booted(): void
  {
    $clear = fn (self $record) => request()->attributes->remove('cms.consent.records.'.$record->site_id);
    static::saved($clear);
    static::deleted($clear);
  }

  protected function casts(): array
  {
    return ['enabled' => 'boolean'];
  }
}
