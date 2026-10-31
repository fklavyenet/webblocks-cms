<?php

namespace WebBlocks\Cms\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\Auth\ManageTwoFactor;
use WebBlocks\Cms\Actions\Auth\UpdateTwoFactorPolicy;
use WebBlocks\Cms\Http\Requests\Auth\TwoFactorRequest;
use WebBlocks\Cms\Policies\TwoFactorPolicy;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;

final class TwoFactorController extends Controller
{
  public function __construct(
    private readonly TwoFactorAuthentication $auth,
    private readonly TwoFactorSession $sessions,
    private readonly ManageTwoFactor $manage,
  ) {}

  public function challenge(Request $request): View|RedirectResponse
  {
    $user = $this->challengeUser($request);
    if (! $user) {
      return to_route('webblocks.auth.login');
    }
    $credential = $this->auth->credential($user);
    if ($credential?->confirmed_at === null) {
      return to_route('webblocks.two-factor.show');
    }
    if ($request->user('web') && $this->sessions->verified($request, $user, $credential)) {
      return $this->sessions->destination($request);
    }

    return view('webblocks-cms::auth.two-factor-challenge', [
      'recipient' => $this->maskedEmail($credential->email),
      'emailCodeActive' => app(EmailTwoFactorCodes::class)->hasActiveCode($request, $user, 'login', $credential->email, $this->sessions->fingerprint($user, $credential)),
    ]);
  }

  public function verify(TwoFactorRequest $request): RedirectResponse
  {
    $user = $this->challengeUser($request);
    if (! $user) {
      return to_route('webblocks.auth.login');
    }
    if (! $this->manage->verify($user, $request->validated('code') ?? '', $request->validated('recovery_code') ?? '', $this->challengeId($request, 'login'), 'login')) {
      return $this->invalid('webblocks.two-factor.challenge');
    }
    if (! $request->user('web')) {
      $remember = (bool) $request->session()->get(TwoFactorSession::PENDING.'.remember', false);
      Auth::guard('web')->login($user, $remember);
      $user->forceFill(['last_login_at' => now()])->save();
    }
    $this->sessions->mark($request, $user, $this->auth->credential($user));

    return $this->sessions->destination($request);
  }

  public function show(Request $request): View|RedirectResponse
  {
    if (! $request->user('web')) {
      return to_route('webblocks.auth.login');
    }
    abort_unless(app(TwoFactorPolicy::class)->manage($request->user('web')), 403);
    abort_unless($this->auth->available(), 503);
    $credential = $this->auth->credential($request->user('web'));
    if ($credential?->confirmed_at !== null && ! $this->sessions->verified($request, $request->user('web'), $credential)) {
      $request->session()->put('url.intended', route('webblocks.two-factor.show'));

      return to_route('webblocks.two-factor.challenge');
    }

    return $this->securityView($request);
  }

  public function start(TwoFactorRequest $request): RedirectResponse
  {
    $generation = $this->manage->start($request->user('web'), $request->validated('code') ?? '', $request->validated('recovery_code') ?? '', $this->challengeId($request));
    if ($generation === null) {
      return $this->invalid('webblocks.two-factor.show', ['tab' => 'replace']);
    }
    if ($credential = $this->auth->credential($request->user('web'))) {
      $this->sessions->mark($request, $request->user('web'), $credential);
    }
    $request->session()->regenerate();
    $request->session()->put(TwoFactorSession::SETUP, $generation);

    return $this->send($request);
  }

  public function confirm(TwoFactorRequest $request): View|RedirectResponse
  {
    $codes = $this->manage->confirm($request->user('web'), (string) $request->session()->get(TwoFactorSession::SETUP, ''), $request->validated('code'), $this->challengeId($request, 'setup'));
    if ($codes === null) {
      return $this->invalid();
    }
    $this->sessions->mark($request, $request->user('web'), $this->auth->credential($request->user('web')));

    return $this->securityView($request, $codes);
  }

  public function regenerate(TwoFactorRequest $request): View|RedirectResponse
  {
    $codes = $this->manage->regenerate($request->user('web'), $request->validated('code') ?? '', $request->validated('recovery_code') ?? '', $this->challengeId($request));
    if ($codes === null) {
      return $this->invalid();
    }
    $this->sessions->mark($request, $request->user('web'), $this->auth->credential($request->user('web')));

    return $this->securityView($request, $codes);
  }

  public function disable(TwoFactorRequest $request): RedirectResponse
  {
    if (! $this->manage->disable($request->user('web'), $request->validated('code') ?? '', $request->validated('recovery_code') ?? '', $this->challengeId($request))) {
      return $this->invalid('webblocks.two-factor.show', ['tab' => 'disable']);
    }
    $request->session()->forget([TwoFactorSession::VERIFIED, TwoFactorSession::SETUP, EmailTwoFactorCodes::SESSION]);
    $request->session()->regenerate();

    return to_route('webblocks.two-factor.show')->with('status', __('webblocks-cms::two-factor.disabled'));
  }

  public function policy(TwoFactorRequest $request, UpdateTwoFactorPolicy $update): RedirectResponse
  {
    if ($this->auth->credential($request->user('web'))?->confirmed_at !== null
      && ! $this->manage->verify($request->user('web'), $request->validated('code') ?? '', $request->validated('recovery_code') ?? '', $this->challengeId($request))) {
      return $this->invalid('admin.system.settings.edit', ['tab' => 'security']);
    }
    if ($credential = $this->auth->credential($request->user('web'))) {
      $this->sessions->mark($request, $request->user('web'), $credential);
    }
    $update->handle($request->user('web'), $request->validated());

    return to_route('admin.system.settings.edit', ['tab' => 'security'])->with('status', __('webblocks-cms::two-factor.policy_saved'));
  }

  private function challengeUser(Request $request): ?Authenticatable
  {
    $user = $request->user('web');
    if ($user) {
      return app(TwoFactorPolicy::class)->manage($user) ? $user : null;
    }
    $pending = $request->session()->get(TwoFactorSession::PENDING);
    if (! is_array($pending) || ($pending['expires_at'] ?? 0) <= now()->timestamp) {
      $request->session()->forget([TwoFactorSession::PENDING, TwoFactorSession::RECOVERY]);

      return null;
    }
    $user = Auth::guard('web')->getProvider()->retrieveById($pending['user_id']);
    $credential = $user ? $this->auth->credential($user) : null;
    if (! app(TwoFactorPolicy::class)->manage($user) || $credential?->confirmed_at === null
      || ! hash_equals((string) $pending['credential'], $this->sessions->fingerprint($user, $credential))) {
      $request->session()->forget([TwoFactorSession::PENDING, TwoFactorSession::RECOVERY]);

      return null;
    }

    return $user;
  }

  public function sendChallenge(TwoFactorRequest $request): RedirectResponse
  {
    $user = $this->challengeUser($request);
    if (! $user) {
      return to_route('webblocks.auth.login');
    }
    $credential = $this->auth->credential($user);
    if ($credential?->confirmed_at === null) {
      return to_route('webblocks.two-factor.show');
    }
    $status = app(EmailTwoFactorCodes::class)->send($request, $user, 'login', $credential->email, $this->sessions->fingerprint($user, $credential));

    return $this->deliveryResult('webblocks.two-factor.challenge', $status);
  }

  public function send(TwoFactorRequest $request): RedirectResponse
  {
    $user = $request->user('web');
    $purpose = $request->validated('purpose') ?? 'setup';
    if ($purpose === 'setup') {
      $enrollment = $this->auth->enrollment($user);
      if (! $enrollment || strtotime($enrollment->expires_at) <= now()->timestamp
        || ! hash_equals($enrollment->generation, (string) $request->session()->get(TwoFactorSession::SETUP, ''))) {
        return $this->invalid();
      }
      $email = $enrollment->email;
      $binding = $this->sessions->setupBinding($user, $enrollment->generation);
    } else {
      $credential = $this->auth->credential($user);
      if ($credential?->confirmed_at === null) {
        return $this->invalid();
      }
      $email = $credential->email;
      $binding = $this->sessions->fingerprint($user, $credential);
    }
    $tab = $request->validated('tab') ?? 'recovery';
    if ($tab === 'user-reset') {
      $target = Auth::guard('web')->getProvider()->retrieveById($request->validated('target_user_id'));
      abort_unless(app(TwoFactorPolicy::class)->reset($user, $target), 403);
    }
    $status = app(EmailTwoFactorCodes::class)->send($request, $user, $purpose, $email, $binding);
    if ($tab === 'user-reset') {
      return $this->deliveryResult('admin.users.edit', $status, ['user' => $target->getAuthIdentifier()]);
    }

    return $this->deliveryResult($tab === 'policy' ? 'admin.system.settings.edit' : 'webblocks.two-factor.show', $status,
      ['tab' => $tab === 'policy' ? 'security' : $tab]);
  }

  private function deliveryResult(string $route, string $status, array $parameters = []): RedirectResponse
  {
    $response = to_route($route, $parameters);

    return $status === 'sent' ? $response->with('status', __('webblocks-cms::two-factor.sent'))
      : $response->withErrors(['code' => __('webblocks-cms::two-factor.'.$status)]);
  }

  private function challengeId(Request $request, string $purpose = 'action'): string
  {
    return (string) $request->session()->get(EmailTwoFactorCodes::SESSION.'.'.$purpose, '');
  }

  private function maskedEmail(string $email): string
  {
    $parts = explode('@', $email, 2);

    return mb_substr($parts[0], 0, 1).'***@'.($parts[1] ?? '');
  }

  private function securityView(Request $request, #[\SensitiveParameter] array $codes = []): View
  {
    $user = $request->user('web');
    $credential = $this->auth->credential($user);
    $enrollment = $this->auth->enrollment($user);
    $setup = $enrollment && strtotime($enrollment->expires_at) > now()->timestamp
      && hash_equals($enrollment->generation, (string) $request->session()->get(TwoFactorSession::SETUP, ''));
    $panel = app(TwoFactorPolicy::class)->viewPanel($user, $request);

    return view('webblocks-cms::auth.two-factor', [
      'continueUrl' => route($request->session()->get(TwoFactorSession::RECOVERY, false) || app(PluginRecoveryMode::class)->active() ? 'admin.plugins.recovery.index' : 'admin.dashboard'),
      'layout' => $panel ? 'webblocks-cms::layouts.admin' : 'webblocks-cms::layouts.guest', 'inPanel' => $panel,
      'enabled' => $credential?->confirmed_at !== null, 'required' => $this->auth->required($user),
      'setupEmail' => $setup ? $enrollment->email : null, 'recipient' => $credential?->email ?? $user->email,
      'recoveryCodes' => $codes, 'canConfigurePolicy' => $panel && app(TwoFactorPolicy::class)->configure($user),
      'recoveryCount' => DB::table('wbcms_two_factor_recovery_codes')->where('user_id', $user->getAuthIdentifier())->count(),
    ]);
  }

  private function invalid(string $route = 'webblocks.two-factor.show', array $parameters = []): RedirectResponse
  {
    // Never flash OTPs, recovery codes, passwords or enrollment secrets as old input.
    return to_route($route, $parameters)->withErrors(['code' => __('webblocks-cms::two-factor.invalid')]);
  }
}
