<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Support\Auth\Module\VerificationSession;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;

final class TwoFactorSession extends VerificationSession
{
  public const VERIFIED = 'wbcms.two_factor.verified';

  public const PENDING = 'wbcms.two_factor.pending';

  public const SETUP = 'wbcms.two_factor.setup';

  public const RECOVERY = 'wbcms.two_factor.recovery';

  public function begin(Request $request, Authenticatable $user, object $credential, bool $remember): void
  {
    $this->beginPending($request, $user, $credential, $remember);
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
