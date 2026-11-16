<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;

class CmsRole extends Model
{
  protected $table = 'wbcms_acl_roles';

  protected $guarded = ['id'];

  protected $casts = ['is_protected' => 'boolean', 'version' => 'integer'];

  public function displayName(): ?string
  {
    return match ($this->is_protected ? $this->handle : null) {
      'site_admin' => __('webblocks-cms::admin-feedback.panel_copy.role_site_admin'),
      'editor' => __('webblocks-cms::admin-feedback.panel_copy.role_editor'),
      default => $this->name,
    };
  }
}
