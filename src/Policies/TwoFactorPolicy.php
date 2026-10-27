<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;

final class TwoFactorPolicy
{
  public function manage(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true)
      && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin();
  }

  public function viewPanel(?Authenticatable $user, Request $request): bool
  {
    if (! $this->manage($user) || ! Route::has('admin.profile.edit')
      || app(PluginRecoveryMode::class)->active() || $request->session()->get(TwoFactorSession::RECOVERY, false)) {
      return false;
    }
    $auth = app(TwoFactorAuthentication::class);
    $credential = $auth->credential($user);

    return $credential?->confirmed_at !== null ? app(TwoFactorSession::class)->verified($request, $user, $credential) : ! $auth->required($user);
  }

  public function managedTarget(?Authenticatable $user): bool
  {
    return $user !== null && (DB::table('wbcms_acl_system_administrators')->where('user_id', $user->getAuthIdentifier())->exists()
      || DB::table('wbcms_site_user')->where('user_id', $user->getAuthIdentifier())->exists());
  }

  public function resetTarget(?Authenticatable $actor, ?Authenticatable $target): bool
  {
    return $this->manage($actor)
      && DB::table('wbcms_acl_system_administrators')->where('user_id', $actor->getAuthIdentifier())->exists()
      && method_exists($actor, 'can') && $actor->can('access-system') && $actor->can('manage-users')
      && $this->managedTarget($target)
      && (string) $actor->getAuthIdentifier() !== (string) $target->getAuthIdentifier();
  }

  public function reset(?Authenticatable $actor, ?Authenticatable $target): bool
  {
    return $this->resetTarget($actor, $target) && app(TwoFactorAuthentication::class)->credential($actor)?->confirmed_at !== null;
  }

  public function configure(?Authenticatable $user): bool
  {
    return $this->manage($user) && app(CmsAuthorization::class)->isSuperAdmin($user);
  }
}
