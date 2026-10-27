<?php

namespace WebBlocks\Cms\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Auth\EmailTwoFactorCodes;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Auth\TwoFactorSession;

final class ManageTwoFactor
{
  public function start(Authenticatable $user, #[\SensitiveParameter] string $code = '', #[\SensitiveParameter] string $recoveryCode = '', string $challenge = ''): ?string
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode, $challenge): ?string {
      if ($credential && ! $this->consume($user, $credential, $code, $recoveryCode, $challenge)) {
        return null;
      }
      $generation = (string) Str::uuid();
      DB::table('wbcms_two_factor_enrollments')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'email' => strtolower(trim((string) $user->email)),
        'generation' => $generation, 'expires_at' => now()->addMinutes(10),
      ]);

      return $generation;
    });
  }

  public function confirm(Authenticatable $user, string $generation, #[\SensitiveParameter] string $code, string $challenge = ''): ?array
  {
    return $this->locked($user, function () use ($user, $generation, $code, $challenge): ?array {
      $setup = DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
      if (! $setup || $setup->generation !== $generation || strtotime($setup->expires_at) <= now()->timestamp) {
        return null;
      }
      if (! app(EmailTwoFactorCodes::class)->consume($user, 'setup', $setup->email, app(TwoFactorSession::class)->setupBinding($user, $generation), $challenge, $code)) {
        return null;
      }
      DB::table('wbcms_two_factor_credentials')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'email' => $setup->email, 'generation' => $generation, 'confirmed_at' => now(),
      ]);
      DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->delete();
      DB::table('wbcms_two_factor_account_states')->where('user_id', $user->getAuthIdentifier())->update(['enrollment_required' => false]);

      return $this->replaceRecoveryCodes($user);
    });
  }

  public function verify(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode = '', string $challenge = '', string $purpose = 'action'): bool
  {
    return $this->locked($user, fn ($credential): bool => $credential && $credential->confirmed_at !== null
      && $this->consume($user, $credential, $code, $recoveryCode, $challenge, $purpose));
  }

  public function regenerate(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode, string $challenge = ''): ?array
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode, $challenge): ?array {
      if (! $credential || $credential->confirmed_at === null || ! $this->consume($user, $credential, $code, $recoveryCode, $challenge)) {
        return null;
      }

      return $this->replaceRecoveryCodes($user);
    });
  }

  public function disable(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode, string $challenge = ''): bool
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode, $challenge): bool {
      if (app(TwoFactorAuthentication::class)->required($user) || ! $credential || $credential->confirmed_at === null
        || ! $this->consume($user, $credential, $code, $recoveryCode, $challenge)) {
        return false;
      }
      DB::table('wbcms_two_factor_email_codes')->where('user_id', $user->getAuthIdentifier())->delete();
      DB::table('wbcms_two_factor_credentials')->where('user_id', $user->getAuthIdentifier())->delete();
      DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->delete();

      return true;
    });
  }

  private function locked(Authenticatable $user, callable $operation): mixed
  {
    return DB::transaction(function () use ($user, $operation) {
      DB::table('users')->where('id', $user->getAuthIdentifier())->lockForUpdate()->first();
      $credential = DB::table('wbcms_two_factor_credentials')->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();

      return $operation($credential);
    });
  }

  private function consume(Authenticatable $user, object $credential, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode, string $challenge, string $purpose = 'action'): bool
  {
    if ($recoveryCode !== '') {
      return DB::table('wbcms_two_factor_recovery_codes')->where('user_id', $credential->user_id)
        ->where('code_hash', hash('sha256', strtolower(trim($recoveryCode))))->delete() === 1;
    }

    return app(EmailTwoFactorCodes::class)->consume($user, $purpose, $credential->email,
      app(TwoFactorSession::class)->fingerprint($user, $credential), $challenge, $code);
  }

  private function replaceRecoveryCodes(Authenticatable $user): array
  {
    DB::table('wbcms_two_factor_recovery_codes')->where('user_id', $user->getAuthIdentifier())->delete();
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
      $code = implode('-', str_split(bin2hex(random_bytes(16)), 8));
      DB::table('wbcms_two_factor_recovery_codes')->insert(['user_id' => $user->getAuthIdentifier(), 'code_hash' => hash('sha256', $code)]);
      $codes[] = $code;
    }

    return $codes;
  }
}
