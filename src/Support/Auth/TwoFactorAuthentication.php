<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Auth\Module\Credentials;

final class TwoFactorAuthentication
{
  public function __construct(private readonly Credentials $credentials) {}

  public function available(): bool
  {
    return $this->credentials->available();
  }

  public function credential(Authenticatable $user): ?object
  {
    return $this->credentials->credential($user);
  }

  public function enrollment(Authenticatable $user): ?object
  {
    return $this->credentials->enrollment($user);
  }

  public function required(Authenticatable $user): bool
  {
    if (! $this->available()) {
      return false;
    }
    if ((bool) app(TwoFactorAccountState::class)->state($user)?->enrollment_required) {
      return true;
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
