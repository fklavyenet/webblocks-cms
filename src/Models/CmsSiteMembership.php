<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

class CmsSiteMembership extends Pivot
{
  protected static function booted(): void
  {
    static::creating(function (self $membership): void {
      if (app(CmsAuthorization::class)->ready() && ! $membership->role_id) {
        $role = DB::table('users')->where('id', $membership->user_id)->value('role');
        $membership->role_id = DB::table('wbcms_acl_roles')->where('is_protected', true)->where('handle', $role)->value('id');
      }
    });
  }
}
