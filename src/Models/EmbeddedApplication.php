<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmbeddedApplication extends CmsModel
{
  use HasFactory;

  protected $fillable = ['handle', 'name', 'description', 'version', 'render_mode', 'entry_url', 'mount_element', 'mount_classes', 'css_assets', 'js_assets', 'supports', 'settings_schema', 'is_enabled', 'created_by_user_id', 'updated_by_user_id'];

  protected function casts(): array
  {
    return ['css_assets' => 'array', 'js_assets' => 'array', 'supports' => 'array', 'settings_schema' => 'array', 'is_enabled' => 'boolean'];
  }
}
