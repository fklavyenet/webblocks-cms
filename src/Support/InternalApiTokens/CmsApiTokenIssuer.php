<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use App\Models\User;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\CmsApiToken;

class CmsApiTokenIssuer
{
  public const TOKEN_PREFIX = 'wbcms_';

  public function issue(
    string $name,
    ?User $creator = null,
    array $capabilities = CmsApiTokenCapabilities::DEFAULT,
    string $tokenType = 'system',
    ?array $allowedSiteIds = null,
    mixed $expiresAt = null,
  ): IssuedCmsApiToken {
    $plainToken = self::TOKEN_PREFIX.Str::random(64);

    $record = CmsApiToken::query()->create([
      'name' => $name,
      'token_hash' => $this->hash($plainToken),
      'token_preview' => $this->preview($plainToken),
      'capabilities' => array_values($capabilities),
      'token_type' => $tokenType,
      'allowed_site_ids' => $allowedSiteIds === null ? null : array_values(array_unique(array_map('intval', $allowedSiteIds))),
      'expires_at' => $expiresAt,
      'created_by_user_id' => $creator?->id,
    ]);

    return new IssuedCmsApiToken($record, $plainToken);
  }

  public function hash(string $plainToken): string
  {
    return hash('sha256', $plainToken);
  }

  public function preview(string $plainToken): string
  {
    return Str::substr($plainToken, 0, 10).'...';
  }
}
