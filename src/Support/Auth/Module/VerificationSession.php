<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class VerificationSession
{
  private const PENDING_MINUTES = 5;

  public function __construct(private readonly Storage $storage) {}

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
    return hash_equals($this->fingerprint($user, $credential), (string) $request->session()->get($this->storage->sessionKey('verified'), ''));
  }

  public function mark(Request $request, Authenticatable $user, object $credential): void
  {
    $request->session()->regenerate();
    $request->session()->put($this->storage->sessionKey('verified'), $this->fingerprint($user, $credential));
    $request->session()->forget([$this->storage->sessionKey('pending'), $this->storage->sessionKey('setup'), $this->storage->sessionKey('email_codes')]);
  }

  public function beginPending(Request $request, Authenticatable $user, object $credential, bool $remember): void
  {
    $request->session()->regenerate();
    $request->session()->forget([$this->storage->sessionKey('verified'), $this->storage->sessionKey('setup'), $this->storage->sessionKey('email_codes')]);
    $request->session()->put($this->storage->sessionKey('pending'), [
      'user_id' => $user->getAuthIdentifier(),
      'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
      'remember' => $remember,
      'credential' => $this->fingerprint($user, $credential),
    ]);
  }

  public function pending(Request $request): ?array
  {
    $pending = $request->session()->get($this->storage->sessionKey('pending'));

    return is_array($pending) && ($pending['expires_at'] ?? 0) > now()->timestamp ? $pending : null;
  }
}
