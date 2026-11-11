<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use App\Models\User;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Policies\PanelLanguagePolicy;

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
    ?array $allowedIpRanges = null,
    ?int $requestsPerMinute = null,
  ): IssuedCmsApiToken {
    if (array_intersect($capabilities, [CmsApiTokenCapabilities::SYSTEM_UPDATES_READ, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN]) !== []) {
      abort_unless($tokenType === 'system' && $allowedSiteIds === null && $creator?->can('access-system') && (bool) ($creator->is_active ?? true), 403);
    }

    if (in_array(CmsApiTokenCapabilities::PANEL_LANGUAGES_MANAGE, $capabilities, true)) {
      abort_unless($tokenType === 'system' && $allowedSiteIds === null
        && app(PanelLanguagePolicy::class)->manage($creator), 403);
    }

    $plainToken = self::TOKEN_PREFIX.Str::random(64);

    $record = CmsApiToken::query()->create([
      'name' => $name,
      'token_hash' => $this->hash($plainToken),
      'token_preview' => $this->preview($plainToken),
      'capabilities' => array_values($capabilities),
      'token_type' => $tokenType,
      'allowed_site_ids' => $allowedSiteIds === null ? null : array_values(array_unique(array_map('intval', $allowedSiteIds))),
      'expires_at' => $expiresAt,
      'allowed_ip_ranges' => $allowedIpRanges,
      'requests_per_minute' => $requestsPerMinute,
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
