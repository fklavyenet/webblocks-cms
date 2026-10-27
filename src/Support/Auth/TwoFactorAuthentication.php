<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class TwoFactorAuthentication
{
  public function available(): bool
  {
    return Schema::hasTable('wbcms_two_factor_credentials');
  }

  public function credential(Authenticatable $user): ?object
  {
    return $this->available() ? DB::table('wbcms_two_factor_credentials')->where('user_id', $user->getAuthIdentifier())->first() : null;
  }

  public function enrollment(Authenticatable $user): ?object
  {
    return DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->first();
  }

  public function required(Authenticatable $user): bool
  {
    if (! $this->available()) {
      return false;
    }
    if (app(CmsAuthorization::class)->isSuperAdmin($user)) {
      return (bool) DB::table('wbcms_two_factor_policy')->where('id', 1)->value('require_super_admin');
    }

    return DB::table('wbcms_site_user as membership')
      ->join('wbcms_two_factor_role_requirements as requirement', 'requirement.role_id', '=', 'membership.role_id')
      ->where('membership.user_id', $user->getAuthIdentifier())->exists();
  }

  public function policy(): array
  {
    return [
      'require_super_admin' => (bool) DB::table('wbcms_two_factor_policy')->where('id', 1)->value('require_super_admin'),
      'role_ids' => DB::table('wbcms_two_factor_role_requirements')->pluck('role_id')->all(),
    ];
  }
}
