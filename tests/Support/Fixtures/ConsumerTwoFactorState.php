<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use WebBlocks\Cms\Actions\Auth\ManageTwoFactor;
use WebBlocks\Cms\Notifications\System\TwoFactorEmail;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\Module\Credentials;
use WebBlocks\Cms\Support\Auth\Module\EmailCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAccountState;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;

final class ConsumerTwoFactorState
{
  public static function seed(string $file): void
  {
    Carbon::setTestNow('2026-01-02 12:00:00');
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);

    $user = User::query()->firstOrFail();
    $action = app(ManageTwoFactor::class);
    $sessions = app(TwoFactorSession::class);
    $delivery = app(EmailTwoFactorCodes::class);
    $generation = $action->start($user);
    $request = self::request();
    self::expect($delivery->send($request, $user, 'setup', $user->email, $sessions->setupBinding($user, $generation)) === 'sent', 'seed enrollment');
    $recovery = $action->confirm($user, $generation, self::lastCode(), $request->session()->get(EmailTwoFactorCodes::SESSION.'.setup'));
    self::expect(count($recovery ?? []) === 10, 'seed recovery codes');
    $credential = app(TwoFactorAuthentication::class)->credential($user);
    $user->forceFill(['email' => 'changed-address@example.test'])->save();
    DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => true]);
    DB::table('wbcms_two_factor_account_states')->insert([
      'user_id' => $user->id, 'session_version' => (string) Str::uuid(), 'enrollment_required' => false, 'reset_at' => now(),
    ]);
    app(TwoFactorAccountState::class)->stamp($request, $user);
    $sessions->mark($request, $user, $credential);
    $verified = $request->session()->all();
    $sessions->begin($request, $user, false);
    self::expect($delivery->send($request, $user, 'login', $credential->email, $sessions->fingerprint($user, $credential)) === 'sent', 'seed pending login');

    file_put_contents($file, json_encode([
      'user_id' => $user->id, 'credential' => $credential, 'verified' => $verified,
      'pending' => $request->session()->all(), 'email_code' => self::lastCode(), 'recovery_code' => $recovery[0],
    ], JSON_THROW_ON_ERROR));
  }

  public static function verify(string $file): void
  {
    Carbon::setTestNow('2026-01-02 12:01:00');
    $state = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $user = User::query()->findOrFail($state['user_id']);
    $request = self::request();
    $auth = app(TwoFactorAuthentication::class);
    $sessions = app(TwoFactorSession::class);
    $credential = $auth->credential($user);
    self::expect((array) $credential === $state['credential'] && $credential->email !== $user->email, 'preserve the original verified address and generation');
    self::expect($auth->required($user), 'preserve the Super admin policy');
    $request->session()->replace($state['verified']);
    self::expect(app(TwoFactorAccountState::class)->current($request, $user), 'retain the session revocation stamp');
    self::expect($sessions->verified($request, $user, $credential), 'retain a verified session');
    $request->session()->replace($state['pending']);
    $pending = $sessions->pending($request);
    self::expect($pending['user_id'] === $user->id && $pending['credential'] === $sessions->fingerprint($user, $credential), 'retain a pending login');

    $emailCodes = app(EmailCodes::class);
    $credentials = app(Credentials::class);
    $challenge = $emailCodes->challengeId($request, 'login');
    self::expect($emailCodes->hasActiveCode($request, $user, 'login', $credential->email, $pending['credential']), 'retain the active challenge');
    self::expect($credentials->verify($user, $state['email_code'], '', $challenge, 'login'), 'accept the pre-update email code');
    self::expect(! $credentials->verify($user, $state['email_code'], '', $challenge, 'login'), 'reject reuse of the email code');
    self::expect($credentials->verify($user, '', $state['recovery_code']), 'accept a pre-update recovery code');
    self::expect(! $credentials->verify($user, '', $state['recovery_code']), 'reject reuse of the recovery code');
  }

  private static function request(): Request
  {
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));

    return $request;
  }

  private static function lastCode(): string
  {
    return Notification::sent(new AnonymousNotifiable, TwoFactorEmail::class)->last()->code;
  }

  private static function expect(bool $condition, string $check): void
  {
    if (! $condition) {
      throw new RuntimeException('Two-factor upgrade fixture could not '.$check.'.');
    }
  }
}
