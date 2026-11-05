<?php

namespace WebBlocks\Cms\Support\Mcp;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Policies\McpPagePolicy;

final class McpGrants
{
  private const ACCESS_LIFETIME = 3600;

  public function __construct(
    private readonly McpConfiguration $config,
    private readonly McpPagePolicy $policy,
  ) {}

  public function issue(User $user, int $siteId, bool $canWrite, string $challenge): string
  {
    $code = bin2hex(random_bytes(32));
    $this->grants()->where('expires_at', '<=', now())->delete();
    $this->grants()->insert([
      'user_id' => $user->getAuthIdentifier(),
      'site_id' => $siteId,
      'can_write' => $canWrite,
      'client_fingerprint' => $this->config->fingerprint(),
      'code_hash' => hash('sha256', $code),
      'code_challenge' => $challenge,
      'expires_at' => now()->addMinutes(3),
    ]);

    return $code;
  }

  public function exchange(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $verifier): ?array
  {
    return DB::transaction(function () use ($code, $verifier): ?array {
      $grant = $this->usableGrants()->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
      $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

      if (! $grant || ! hash_equals($grant->code_challenge, $challenge) || ! $this->actor($grant)) {
        return null;
      }

      $token = bin2hex(random_bytes(32));
      $this->grants()->where('id', $grant->id)->update([
        'code_hash' => null,
        'code_challenge' => null,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addSeconds(self::ACCESS_LIFETIME),
      ]);

      return [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => self::ACCESS_LIFETIME,
        'scope' => McpConfiguration::READ.($grant->can_write ? ' '.McpConfiguration::WRITE : ''),
      ];
    });
  }

  /** @return array{user: User, site: int, write: bool}|null */
  public function authenticate(#[\SensitiveParameter] ?string $token): ?array
  {
    if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
      return null;
    }
    $grant = $this->usableGrants()->where('token_hash', hash('sha256', $token))->first();
    $user = $grant ? $this->actor($grant) : null;

    return $user ? ['user' => $user, 'site' => (int) $grant->site_id, 'write' => (bool) $grant->can_write] : null;
  }

  private function actor(object $grant): ?User
  {
    $user = app('auth')->guard('web')->getProvider()->retrieveById($grant->user_id);

    return $user instanceof User && $user->canAccessAdmin() && $this->policy->read($user, (int) $grant->site_id) ? $user : null;
  }

  private function usableGrants(): Builder
  {
    return $this->grants()->where('client_fingerprint', $this->config->fingerprint())->where('expires_at', '>', now());
  }

  private function grants(): Builder
  {
    return DB::table('wbcms_mcp_grants');
  }
}
