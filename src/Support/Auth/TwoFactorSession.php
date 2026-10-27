<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;

final class TwoFactorSession
{
  public const VERIFIED = 'wbcms.two_factor.verified';

  public const PENDING = 'wbcms.two_factor.pending';

  public const SETUP = 'wbcms.two_factor.setup';

  public const RECOVERY = 'wbcms.two_factor.recovery';

  public function fingerprint(Authenticatable $user, object $credential): string
  {
    return hash('sha256', $user->getAuthIdentifier().'|'.$credential->generation.'|'.$credential->email.'|'.$user->getAuthPassword());
  }

  public function setupBinding(Authenticatable $user, string $generation): string
  {
    return hash('sha256', $generation.'|'.$user->getAuthPassword());
  }

  public function verified(Request $request, Authenticatable $user, object $credential): bool
  {
    return hash_equals($this->fingerprint($user, $credential), (string) $request->session()->get(self::VERIFIED, ''));
  }

  public function mark(Request $request, Authenticatable $user, object $credential): void
  {
    $request->session()->regenerate();
    $request->session()->put(self::VERIFIED, $this->fingerprint($user, $credential));
    $request->session()->forget([self::PENDING, self::SETUP, EmailTwoFactorCodes::SESSION]);
  }

  public function begin(Request $request, Authenticatable $user, bool $remember): void
  {
    $request->session()->regenerate();
    $request->session()->forget([self::VERIFIED, self::SETUP, EmailTwoFactorCodes::SESSION]);
    $request->session()->put(self::PENDING, [
      'user_id' => $user->getAuthIdentifier(),
      'expires_at' => now()->addMinutes(5)->timestamp,
      'remember' => $remember,
      'credential' => $this->fingerprint($user, app(TwoFactorAuthentication::class)->credential($user)),
    ]);
    $request->session()->put(self::RECOVERY, app(PluginRecoveryMode::class)->active());
  }

  public function destination(Request $request): RedirectResponse
  {
    if ($request->session()->pull(self::RECOVERY, false) || app(PluginRecoveryMode::class)->active()) {
      $request->session()->forget('url.intended');

      return to_route('admin.plugins.recovery.index');
    }

    return redirect()->intended(route('admin.dashboard'));
  }
}
