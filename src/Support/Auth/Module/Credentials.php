<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class Credentials
{
  private const ENROLLMENT_MINUTES = 10;

  private const RECOVERY_CODE_COUNT = 10;

  public function __construct(
    private readonly Storage $storage,
    private readonly EmailCodes $codes,
    private readonly VerificationSession $sessions,
  ) {}

  public function available(): bool
  {
    return Schema::hasTable($this->storage->tableName('credentials'));
  }

  public function credential(Authenticatable $user): ?object
  {
    return $this->available() ? $this->storage->table('credentials')->where('user_id', $user->getAuthIdentifier())->first() : null;
  }

  public function enrollment(Authenticatable $user): ?object
  {
    return $this->storage->table('enrollments')->where('user_id', $user->getAuthIdentifier())->first();
  }

  public function activeEnrollment(Authenticatable $user, string $generation): ?object
  {
    $enrollment = $this->enrollment($user);

    return $this->matchesEnrollment($enrollment, $generation) ? $enrollment : null;
  }

  public function recoveryCount(Authenticatable $user): int
  {
    return $this->storage->table('recovery_codes')->where('user_id', $user->getAuthIdentifier())->count();
  }

  public function start(Authenticatable $user, string $email, #[\SensitiveParameter] string $code = '', #[\SensitiveParameter] string $recoveryCode = '', string $challenge = ''): ?string
  {
    return $this->locked($user, function ($credential) use ($user, $email, $code, $recoveryCode, $challenge): ?string {
      if ($credential && ! $this->consume($user, $credential, $code, $recoveryCode, $challenge)) {
        return null;
      }
      $generation = (string) Str::uuid();
      $this->storage->table('enrollments')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'email' => strtolower(trim($email)),
        'generation' => $generation, 'expires_at' => now()->addMinutes(self::ENROLLMENT_MINUTES),
      ]);

      return $generation;
    });
  }

  public function confirm(Authenticatable $user, string $generation, #[\SensitiveParameter] string $code, string $challenge = ''): ?array
  {
    return $this->locked($user, function () use ($user, $generation, $code, $challenge): ?array {
      $setup = $this->storage->table('enrollments')->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
      if (! $this->matchesEnrollment($setup, $generation)) {
        return null;
      }
      if (! $this->codes->consume($user, 'setup', $setup->email, $this->sessions->setupBinding($user, $generation), $challenge, $code)) {
        return null;
      }
      $this->storage->table('credentials')->updateOrInsert(['user_id' => $user->getAuthIdentifier()], [
        'email' => $setup->email, 'generation' => $generation, 'confirmed_at' => now(),
      ]);
      $this->storage->table('enrollments')->where('user_id', $user->getAuthIdentifier())->delete();
      $this->storage->table('account_states')->where('user_id', $user->getAuthIdentifier())->update(['enrollment_required' => false]);

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

  /** @param callable(Authenticatable): bool $required Host enforcement policy checked under the account lock. */
  public function disable(Authenticatable $user, callable $required, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode, string $challenge = ''): bool
  {
    return $this->locked($user, function ($credential) use ($user, $required, $code, $recoveryCode, $challenge): bool {
      if ($required($user) || ! $credential || $credential->confirmed_at === null
        || ! $this->consume($user, $credential, $code, $recoveryCode, $challenge)) {
        return false;
      }
      $this->storage->table('email_codes')->where('user_id', $user->getAuthIdentifier())->delete();
      $this->storage->table('credentials')->where('user_id', $user->getAuthIdentifier())->delete();
      $this->storage->table('enrollments')->where('user_id', $user->getAuthIdentifier())->delete();

      return true;
    });
  }

  /**
   * Drop the factor and revoke its existing sessions. The host authorizes the
   * reset and rotates any remember-me credentials in the same transaction.
   */
  public function reset(Authenticatable $user): bool
  {
    return $this->locked($user, function ($credential) use ($user): bool {
      $id = $user->getAuthIdentifier();
      foreach (['recovery_codes', 'email_codes', 'enrollments', 'credentials'] as $table) {
        $this->storage->table($table)->where('user_id', $id)->delete();
      }
      $this->storage->table('account_states')->updateOrInsert(['user_id' => $id], [
        'session_version' => (string) Str::uuid(), 'enrollment_required' => true, 'reset_at' => now(),
      ]);

      return $credential !== null;
    });
  }

  private function matchesEnrollment(?object $enrollment, string $generation): bool
  {
    return $enrollment && strtotime($enrollment->expires_at) > now()->timestamp && hash_equals($enrollment->generation, $generation);
  }

  private function locked(Authenticatable $user, callable $operation): mixed
  {
    return DB::transaction(function () use ($user, $operation) {
      $this->storage->lockUser($user);
      $credential = $this->storage->table('credentials')->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();

      return $operation($credential);
    });
  }

  private function consume(Authenticatable $user, object $credential, #[\SensitiveParameter] string $code, #[\SensitiveParameter] string $recoveryCode, string $challenge, string $purpose = 'action'): bool
  {
    if ($recoveryCode !== '') {
      return $this->storage->table('recovery_codes')->where('user_id', $credential->user_id)
        ->where('code_hash', hash('sha256', strtolower(trim($recoveryCode))))->delete() === 1;
    }

    return $this->codes->consume($user, $purpose, $credential->email,
      $this->sessions->fingerprint($user, $credential), $challenge, $code);
  }

  private function replaceRecoveryCodes(Authenticatable $user): array
  {
    $this->storage->table('recovery_codes')->where('user_id', $user->getAuthIdentifier())->delete();
    $codes = [];
    for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
      $code = implode('-', str_split(bin2hex(random_bytes(16)), 8));
      $this->storage->table('recovery_codes')->insert(['user_id' => $user->getAuthIdentifier(), 'code_hash' => hash('sha256', $code)]);
      $codes[] = $code;
    }

    return $codes;
  }
}
