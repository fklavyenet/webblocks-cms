<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use WebBlocks\Cms\Models\CmsApiToken;

class CmsApiTokenCapabilities
{
  public const CONTENT_READ = 'content.read';
  public const CONTENT_VALIDATE = 'content.validate';
  public const CONTENT_APPLY = 'content.apply';
  public const CONTENT_PUBLISH = 'content.publish';
  public const PAGES_DELETE = 'pages.delete';
  public const NAVIGATION_WRITE = 'navigation.write';
  public const SHARED_SLOTS_WRITE = 'shared-slots.write';

  public const DEFAULT = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
  ];

  public const DESTRUCTIVE = [
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
  ];

  public function capabilitiesFor(?CmsApiToken $token): array
  {
    if (! $token) {
      return [];
    }

    $capabilities = $token->capabilities;

    if (! is_array($capabilities) || $capabilities === []) {
      return self::DEFAULT;
    }

    return collect($capabilities)
      ->filter(fn ($capability) => is_string($capability) && $capability !== '')
      ->unique()
      ->values()
      ->all();
  }

  public function has(?CmsApiToken $token, string $capability): bool
  {
    return in_array($capability, $this->capabilitiesFor($token), true);
  }

  public function publicDescription(?CmsApiToken $token): array
  {
    $capabilities = $this->capabilitiesFor($token);

    return [
      'capabilities' => $capabilities,
      'destructive_capabilities' => array_values(array_intersect($capabilities, self::DESTRUCTIVE)),
      'destructive_requires_explicit_capability' => true,
    ];
  }
}
