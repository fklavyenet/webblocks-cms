<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class EmailCodes
{
  public const LIFETIME_MINUTES = 5;

  private const MAX_ATTEMPTS = 5;

  private const RESEND_SECONDS = 60;

  private const ACCOUNT_SEND_LIMIT = 3;

  public function __construct(private readonly Storage $storage) {}

  public function challengeId(Request $request, string $purpose): string
  {
    return (string) $request->session()->get($this->storage->sessionKey('email_codes.'.$purpose), '');
  }

  /** @param callable(string, string): bool $deliver Receives the address and plaintext code for synchronous delivery. */
  public function send(Request $request, Authenticatable $user, string $purpose, string $email, string $binding, callable $deliver): string
  {
    if (! in_array($purpose, ['login', 'setup', 'action'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return 'send_failed';
    }
    $id = (string) Str::uuid();
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $created = DB::transaction(function () use ($user, $purpose, $email, $binding, $id, $code): bool {
      $this->storage->lockUser($user);
      // Limit resends across sessions, with a separate account-wide send ceiling.
      $recent = $this->storage->table('email_codes')->where('user_id', $user->getAuthIdentifier())->where('sent_at', '>', now()->subSeconds(self::RESEND_SECONDS));
      if ((clone $recent)->count() >= self::ACCOUNT_SEND_LIMIT || (clone $recent)->where('purpose', $purpose)->exists()) {
        return false;
      }
      $this->storage->table('email_codes')->where('user_id', $user->getAuthIdentifier())->where('expires_at', '<=', now())->delete();
      $this->storage->table('email_codes')->insert([
        'id' => $id, 'user_id' => $user->getAuthIdentifier(), 'purpose' => $purpose, 'email' => $email,
        'binding' => $binding, 'code_hash' => $this->hash($id, $code), 'attempts' => 0,
        'sent_at' => now(), 'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES), 'consumed_at' => null,
      ]);

      return true;
    });
    if (! $created) {
      return 'send_throttled';
    }
    try {
      $delivered = $deliver($email, $code);
    } catch (\Throwable $exception) {
      // Transport errors may contain the plaintext code.
      Log::warning('Verification code delivery failed.', ['exception_class' => $exception::class]);
      $delivered = false;
    }
    if (! $delivered) {
      $this->storage->table('email_codes')->where('id', $id)->update(['consumed_at' => now()]);

      return 'send_failed';
    }
    $previous = $this->challengeId($request, $purpose);
    if ($previous !== '') {
      $this->storage->table('email_codes')->where('id', $previous)->where('user_id', $user->getAuthIdentifier())->update(['consumed_at' => now()]);
    }
    $request->session()->put($this->storage->sessionKey('email_codes.'.$purpose), $id);

    return 'sent';
  }

  public function hasActiveCode(Request $request, Authenticatable $user, string $purpose, string $email, string $binding): bool
  {
    return $this->storage->table('email_codes')
      ->where('id', $this->challengeId($request, $purpose))
      ->where('user_id', $user->getAuthIdentifier())->where('purpose', $purpose)
      ->where('email', $email)->where('binding', $binding)
      ->whereNull('consumed_at')->where('attempts', '<', self::MAX_ATTEMPTS)->where('expires_at', '>', now())->exists();
  }

  public function retryAfter(Authenticatable $user, string $purpose): int
  {
    $recent = $this->storage->table('email_codes')->where('user_id', $user->getAuthIdentifier())
      ->where('sent_at', '>', now()->subSeconds(self::RESEND_SECONDS))->orderByDesc('sent_at')->get(['purpose', 'sent_at']);
    $until = 0;
    foreach ($recent as $index => $row) {
      if ($row->purpose === $purpose || $index === self::ACCOUNT_SEND_LIMIT - 1) {
        $until = max($until, strtotime($row->sent_at) + self::RESEND_SECONDS);
      }
    }

    return max(0, $until - now()->timestamp);
  }

  public function consume(Authenticatable $user, string $purpose, string $email, string $binding, string $id, #[\SensitiveParameter] string $code): bool
  {
    return DB::transaction(function () use ($user, $purpose, $email, $binding, $id, $code): bool {
      $row = $this->storage->table('email_codes')->where('id', $id)->where('user_id', $user->getAuthIdentifier())->lockForUpdate()->first();
      if (! $row || $row->purpose !== $purpose || $row->email !== $email || ! hash_equals($row->binding, $binding)
        || $row->consumed_at !== null || $row->attempts >= self::MAX_ATTEMPTS || strtotime($row->expires_at) <= now()->timestamp) {
        return false;
      }
      $valid = hash_equals($row->code_hash, $this->hash($id, $code));
      $this->storage->table('email_codes')->where('id', $id)->update([
        'attempts' => $row->attempts + 1, 'consumed_at' => $valid ? now() : null,
      ]);

      return $valid;
    });
  }

  private function hash(string $id, #[\SensitiveParameter] string $code): string
  {
    return hash_hmac('sha256', $id.'|'.$code, (string) config('app.key'));
  }
}
