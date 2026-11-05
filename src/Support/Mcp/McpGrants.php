<?php

namespace WebBlocks\Cms\Support\Mcp;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

  public function issue(User $user, int $siteId, bool $canWrite, bool $canEditContent, bool $canCreateDraft, bool $canEditStructure, bool $canEditTranslations, string $challenge, McpOAuthClient $client, string $redirect): string
  {
    $code = bin2hex(random_bytes(32));
    $this->grants()->where('expires_at', '<=', now())->delete();
    $this->grants()->insert([
      'user_id' => $user->getAuthIdentifier(),
      'site_id' => $siteId,
      'can_write' => $canWrite,
      'can_edit_content' => $canEditContent,
      'can_create_draft' => $canCreateDraft,
      'can_edit_structure' => $canEditStructure,
      'can_edit_translations' => $canEditTranslations,
      'client_fingerprint' => $this->config->clientFingerprint($client->id),
      'oauth_client_id' => $client->id,
      'redirect_hash' => hash('sha256', $redirect),
      'code_hash' => hash('sha256', $code),
      'code_challenge' => $challenge,
      'expires_at' => now()->addMinutes(3),
    ]);

    return $code;
  }

  public function exchange(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $verifier, McpOAuthClient $client, string $redirect): ?array
  {
    return DB::transaction(function () use ($code, $verifier, $client, $redirect): ?array {
      $grant = $this->usableGrants()->where('oauth_client_id', $client->id)
        ->where('client_fingerprint', $this->config->clientFingerprint($client->id))->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
      $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

      if (! $grant || ! hash_equals($grant->code_challenge, $challenge) || ! $this->actor($grant)
        || ! hash_equals($grant->redirect_hash, hash('sha256', $redirect))) {
        return null;
      }

      $token = bin2hex(random_bytes(32));
      $this->grants()->where('id', $grant->id)->update([
        'code_hash' => null,
        'code_challenge' => null,
        'redirect_hash' => null,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addSeconds(self::ACCESS_LIFETIME),
      ]);

      return [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => self::ACCESS_LIFETIME,
        'scope' => implode(' ', array_filter([
          McpConfiguration::READ,
          $grant->can_write ? McpConfiguration::WRITE : null,
          $grant->can_edit_content ? McpConfiguration::CONTENT : null,
          $grant->can_create_draft ? McpConfiguration::CREATE : null,
          $grant->can_edit_structure ? McpConfiguration::STRUCTURE : null,
          $grant->can_edit_translations ? McpConfiguration::TRANSLATION : null,
        ])),
      ];
    });
  }

  /** @return array{user: User, site: int, write: bool, edit_content: bool, create_draft: bool, edit_structure: bool, edit_translations: bool}|null */
  public function authenticate(#[\SensitiveParameter] ?string $token): ?array
  {
    if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
      return null;
    }
    $grant = $this->usableGrants()->where('token_hash', hash('sha256', $token))->first();
    $user = $grant && hash_equals($this->config->clientFingerprint($grant->oauth_client_id), $grant->client_fingerprint)
      ? $this->actor($grant) : null;

    return $user ? [
      'user' => $user,
      'site' => (int) $grant->site_id,
      'write' => (bool) $grant->can_write,
      'edit_content' => (bool) $grant->can_edit_content,
      'create_draft' => (bool) $grant->can_create_draft,
      'edit_structure' => (bool) $grant->can_edit_structure,
      'edit_translations' => (bool) $grant->can_edit_translations,
    ] : null;
  }

  public function connections(User $user): LengthAwarePaginator
  {
    return $this->usableGrants()->where('user_id', $user->getAuthIdentifier())
      ->join('wbcms_mcp_oauth_clients as clients', 'clients.id', '=', 'wbcms_mcp_grants.oauth_client_id')
      ->select('oauth_client_id', 'clients.name as client_name')
      ->selectRaw('MAX(expires_at) as expires_at')
      ->groupBy('oauth_client_id', 'clients.name')
      ->orderByDesc('expires_at')->orderBy('oauth_client_id')->paginate(15);
  }

  public function revoke(User $user, string $clientId): bool
  {
    return $this->grants()->where('user_id', $user->getAuthIdentifier())
      ->where('oauth_client_id', $clientId)->delete() > 0;
  }

  private function actor(object $grant): ?User
  {
    $user = app('auth')->guard('web')->getProvider()->retrieveById($grant->user_id);

    return $user instanceof User && $user->canAccessAdmin() && $this->policy->read($user, (int) $grant->site_id) ? $user : null;
  }

  private function usableGrants(): Builder
  {
    return $this->grants()->whereNotNull('oauth_client_id')->where('expires_at', '>', now());
  }

  private function grants(): Builder
  {
    return DB::table('wbcms_mcp_grants');
  }
}
