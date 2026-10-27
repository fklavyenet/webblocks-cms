<?php

namespace WebBlocks\Cms\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Policies\TwoFactorPolicy;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAccountState;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;

final class ResetTwoFactor
{
  public function fromPanel(Request $request, Authenticatable $target, #[\SensitiveParameter] array $data): bool
  {
    return app(WriteCmsAccess::class)->locked(function () use ($request, $target, $data): bool {
      $actor = $request->user('web');
      DB::table('users')->whereIn('id', [$actor->getAuthIdentifier(), $target->getAuthIdentifier()])->orderBy('id')->lockForUpdate()->get();
      $actor = Auth::guard('web')->getProvider()->retrieveById($actor->getAuthIdentifier());
      $target = Auth::guard('web')->getProvider()->retrieveById($target->getAuthIdentifier());
      // The policy rechecks SQL authority after the shared ACL lock.
      abort_unless(app(TwoFactorPolicy::class)->reset($actor, $target), 403);
      $credential = app(TwoFactorAuthentication::class)->credential($actor);
      if (! app(TwoFactorAccountState::class)->current($request, $actor)
        || ! app(TwoFactorSession::class)->verified($request, $actor, $credential)
        || ! Hash::check($data['current_password'], $actor->getAuthPassword())
        || ! $this->validInput($target, $data['reason'], $data['recovery_email'] ?? null)) {
        return false;
      }
      if (! app(ManageTwoFactor::class)->verify($actor, $data['code'] ?? '', $data['recovery_code'] ?? '',
        (string) $request->session()->get(EmailTwoFactorCodes::SESSION.'.action', ''))) {
        return false;
      }
      $this->perform($target, $actor, 'panel', $data['reason'], $data['recovery_email'] ?? null);

      return true;
    });
  }

  public function fromConsole(Authenticatable $target, string $reason, ?string $email = null): bool
  {
    abort_unless(app()->runningInConsole(), 403);

    return app(WriteCmsAccess::class)->locked(function () use ($target, $reason, $email): bool {
      DB::table('users')->where('id', $target->getAuthIdentifier())->lockForUpdate()->first();
      $target = Auth::guard('web')->getProvider()->retrieveById($target->getAuthIdentifier());
      if (! app(TwoFactorPolicy::class)->managedTarget($target) || ! $this->validInput($target, $reason, $email)) {
        return false;
      }
      $this->perform($target, null, 'console', $reason, $email);

      return true;
    });
  }

  public function validInput(Authenticatable $target, string $reason, ?string $email): bool
  {
    return trim($reason) !== '' && mb_strlen($reason) <= 500 && ($email === null || $email === ''
      || (mb_strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL)
        && ! DB::table('users')->where('email', strtolower(trim($email)))->where('id', '!=', $target->getAuthIdentifier())->exists()));
  }

  private function perform(Authenticatable $target, ?Authenticatable $actor, string $source, string $reason, ?string $email): void
  {
    $id = $target->getAuthIdentifier();
    $enabled = DB::table('wbcms_two_factor_credentials')->where('user_id', $id)->exists();
    foreach (['recovery_codes', 'email_codes', 'enrollments', 'credentials'] as $table) {
      DB::table('wbcms_two_factor_'.$table)->where('user_id', $id)->delete();
    }
    DB::table('wbcms_two_factor_account_states')->updateOrInsert(['user_id' => $id], [
      'session_version' => (string) Str::uuid(), 'enrollment_required' => true, 'reset_at' => now(),
    ]);
    if ($target->getRememberTokenName() !== '') {
      // Honor the host provider's token column and model casts.
      Auth::guard('web')->getProvider()->updateRememberToken($target, Str::random(60));
    }
    $changes = [];
    if ($email !== null && trim($email) !== '') {
      $changes['email'] = strtolower(trim($email));
      // A replacement mailbox has not yet been proven by the target account.
      if (Schema::hasColumn('users', 'email_verified_at')) {
        $changes['email_verified_at'] = null;
      }
    }
    if ($changes !== []) {
      DB::table('users')->where('id', $id)->update($changes);
    }
    DB::table('wbcms_acl_events')->insert([
      'actor_id' => $actor?->getAuthIdentifier(), 'user_id' => $id,
      'action' => $source === 'panel' ? 'user.two_factor.reset' : 'user.two_factor.recovered',
      'before_value' => $enabled ? 'enabled' : 'not_enrolled', 'after_value' => 'enrollment_required',
      'source' => $source, 'reason' => trim($reason), 'created_at' => now(),
    ]);
  }
}
