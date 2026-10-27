<?php

namespace WebBlocks\Cms\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Auth\Totp;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;

final class ManageTwoFactor
{
  public function start(Authenticatable $user, #[\SensitiveParameter] string $code = '', #[\SensitiveParameter] string $recoveryCode = ''): ?string
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode): ?string {
      if ($credential && ! $this->consume($credential, $code, $recoveryCode)) {
        return null;
      }
      $generation = (string) Str::uuid();
      DB::table('wbcms_two_factor_enrollments')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'secret' => Crypt::encryptString((new Totp)->generateSecret()),
        'generation' => $generation, 'expires_at' => now()->addMinutes(10),
      ]);

      return $generation;
    });
  }

  public function confirm(Authenticatable $user, string $generation, #[\SensitiveParameter] string $code): ?array
  {
    return $this->locked($user, function () use ($user, $generation, $code): ?array {
      $setup = DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
      if (! $setup || $setup->generation !== $generation || strtotime($setup->expires_at) <= now()->timestamp) {
        return null;
      }
      $step = $this->totpStep($setup->secret, $code, -1);
      if ($step === false) {
        return null;
      }
      DB::table('wbcms_two_factor_credentials')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'secret' => $setup->secret, 'generation' => $generation, 'confirmed_at' => now(), 'last_used_step' => $step,
      ]);
      DB::table('wbcms_two_factor_enrollments')->where('user_id', $user->getAuthIdentifier())->delete();

      return $this->replaceRecoveryCodes($user);
    });
  }

  public function verify(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode = ''): bool
  {
    return $this->locked($user, fn ($credential): bool => $credential && $credential->confirmed_at !== null
      && $this->consume($credential, $code, $recoveryCode));
  }

  public function regenerate(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode): ?array
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode): ?array {
      if (! $credential || $credential->confirmed_at === null || ! $this->consume($credential, $code, $recoveryCode)) {
        return null;
      }

      return $this->replaceRecoveryCodes($user);
    });
  }

  public function disable(Authenticatable $user, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode): bool
  {
    return $this->locked($user, function ($credential) use ($user, $code, $recoveryCode): bool {
      if (app(TwoFactorAuthentication::class)->required($user) || ! $credential || $credential->confirmed_at === null
        || ! $this->consume($credential, $code, $recoveryCode)) {
        return false;
      }
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

  private function consume(object $credential, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode): bool
  {
    if ($recoveryCode !== '') {
      return DB::table('wbcms_two_factor_recovery_codes')->where('user_id', $credential->user_id)
        ->where('code_hash', hash('sha256', strtolower(trim($recoveryCode))))->delete() === 1;
    }

    return $this->consumeTotp($credential, $code);
  }

  private function consumeTotp(object $credential, #[\SensitiveParameter] string $code): bool
  {
    $step = $this->totpStep($credential->secret, $code, (int) $credential->last_used_step);
    if ($step === false) {
      return false;
    }
    DB::table('wbcms_two_factor_credentials')->where('user_id', $credential->user_id)->update(['last_used_step' => $step]);

    return true;
  }

  private function totpStep(#[\SensitiveParameter] string $encryptedSecret, #[\SensitiveParameter] string $code, int $lastStep): int|false
  {
    return (new Totp)->verify(Crypt::decryptString($encryptedSecret), $code, $lastStep, now()->timestamp);
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
