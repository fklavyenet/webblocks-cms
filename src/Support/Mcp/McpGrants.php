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

  public function issue(
    User $user,
    int $siteId,
    array $scopes,
    string $challenge,
    McpOAuthClient $client,
    string $redirect,
    bool $draftsCreatedOnly = true,
    ?int $draftId = null,
  ): string {
    return DB::transaction(function () use ($user, $siteId, $scopes, $challenge, $client, $redirect, $draftsCreatedOnly, $draftId): string {
      $draft = $draftId === null ? null : app(McpDraftAccess::class)->candidates([$siteId])->lockForUpdate()->find($draftId);
      abort_if($draftId !== null && ! $draft, 403);
      $code = bin2hex(random_bytes(32));
      $this->grants()->where('expires_at', '<=', now())->delete();
      $grantId = $this->grants()->insertGetId([
        'user_id' => $user->getAuthIdentifier(),
        'site_id' => $siteId,
        'drafts_created_only' => $draftsCreatedOnly,
        ...array_fill_keys(array_values(McpConfiguration::GRANT_COLUMNS), false),
        ...array_fill_keys(array_values(array_intersect_key(McpConfiguration::GRANT_COLUMNS, array_flip($scopes))), true),
        'client_fingerprint' => $this->config->clientFingerprint($client->id),
        'oauth_client_id' => $client->id,
        'redirect_hash' => hash('sha256', $redirect),
        'code_hash' => hash('sha256', $code),
        'code_challenge' => $challenge,
        'expires_at' => now()->addMinutes(3),
      ]);

      if ($draftsCreatedOnly && $draft) {
        DB::table('wbcms_mcp_grant_drafts')->insert(['grant_id' => $grantId, 'page_id' => $draft->id]);
      }

      return $code;
    });
  }

  public function exchange(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $verifier, McpOAuthClient $client, string $redirect): ?array
  {
    return DB::transaction(function () use ($code, $verifier, $client, $redirect): ?array {
      $grant = $this->usableGrants()->where('oauth_client_id', $client->id)
        ->where('client_fingerprint', $this->config->clientFingerprint($client->id))->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
      $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
      $user = $grant ? $this->actor($grant) : null;

      if (! $user || ! hash_equals($grant->code_challenge, $challenge)
        || ! hash_equals($grant->redirect_hash, hash('sha256', $redirect))) {
        return null;
      }

      $scopes = $this->scopes($grant);
      if (array_diff($scopes, $this->config->availableScopes()) !== []) {
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
        'scope' => implode(' ', $scopes),
      ];
    });
  }

  /** @return array{id: int, drafts_created_only: bool, user: User, site: int, scopes: list<string>}|null */
  public function authenticate(#[\SensitiveParameter] ?string $token): ?array
  {
    if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
      return null;
    }
    $grant = $this->usableGrants()->where('token_hash', hash('sha256', $token))->first();
    $user = $grant && hash_equals($this->config->clientFingerprint($grant->oauth_client_id), $grant->client_fingerprint)
      ? $this->actor($grant) : null;

    return $user ? [
      'id' => (int) $grant->id,
      'drafts_created_only' => (bool) $grant->drafts_created_only,
      'user' => $user,
      'site' => (int) $grant->site_id,
      'scopes' => $this->scopes($grant),
    ] : null;
  }

  public function connections(User $user): LengthAwarePaginator
  {
    $columns = array_values(McpConfiguration::GRANT_COLUMNS);
    $identity = ['wbcms_mcp_grants.id', 'oauth_client_id', 'clients.name', 'wbcms_mcp_grants.site_id', 'sites.name', 'drafts_created_only', ...$columns];
    $pending = 'CASE WHEN wbcms_mcp_grants.token_hash IS NULL THEN 1 ELSE 0 END';
    $available = $this->config->availableScopes();

    return $this->usableGrants()->where('user_id', $user->getAuthIdentifier())
      ->join('wbcms_mcp_oauth_clients as clients', 'clients.id', '=', 'wbcms_mcp_grants.oauth_client_id')
      ->join('wbcms_sites as sites', 'sites.id', '=', 'wbcms_mcp_grants.site_id')
      ->select('oauth_client_id', 'clients.name as client_name', 'wbcms_mcp_grants.site_id', 'sites.name as site_name', 'drafts_created_only', ...$columns)
      ->selectRaw('MAX(wbcms_mcp_grants.id) as row_id, MAX(expires_at) as expires_at, '.$pending.' as pending')
      ->groupBy(...$identity)->groupByRaw($pending)
      ->orderByDesc('expires_at')->orderByDesc('row_id')->paginate(15)
      ->through(function (object $connection) use ($user, $available): object {
        $connection->draft_names = DB::table('wbcms_mcp_grant_drafts as access')->join('wbcms_pages as pages', 'pages.id', '=', 'access.page_id')
          ->where('access.grant_id', $connection->row_id)->where('pages.status', 'draft')
          ->join('wbcms_page_translations as translations', 'translations.page_id', '=', 'pages.id')
          ->orderBy('pages.id')->pluck('translations.name')->unique()->values();
        $connection->permissions = [];
        foreach ($this->scopes($connection) as $scope) {
          $connection->permissions[McpConfiguration::LABELS[$scope]] = in_array($scope, $available, true)
            && $this->policy->allowsScopes($user, (int) $connection->site_id, [$scope]);
        }

        return $connection;
      });
  }

  /** @return list<string> */
  private function scopes(object $grant): array
  {
    return [McpConfiguration::READ, ...array_keys(array_filter(McpConfiguration::GRANT_COLUMNS, fn (string $column) => $grant->{$column}))];
  }

  public function revoke(User $user, int $grantId): bool
  {
    return $this->usableGrants()->where('user_id', $user->getAuthIdentifier())
      ->where('wbcms_mcp_grants.id', $grantId)->delete() > 0;
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
