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

  public const SITE_SETTINGS_WRITE = 'site-settings.write';

  public const DEFAULT = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::SITE_SETTINGS_WRITE,
  ];

  public const DESTRUCTIVE = [
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
  ];

  public const ALL = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::SITE_SETTINGS_WRITE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
  ];

  public const LABELS = [
    self::CONTENT_READ => 'Read content metadata and contracts',
    self::CONTENT_VALIDATE => 'Validate content plans',
    self::CONTENT_APPLY => 'Apply draft content plans',
    self::NAVIGATION_WRITE => 'Write navigation menu items',
    self::SHARED_SLOTS_WRITE => 'Write Shared Slots',
    self::SITE_SETTINGS_WRITE => 'Write safe site presentation settings',
    self::CONTENT_PUBLISH => 'Publish content',
    self::PAGES_DELETE => 'Delete pages',
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
      'can' => [
        'read_content' => $this->has($token, self::CONTENT_READ),
        'validate_content_plans' => $this->has($token, self::CONTENT_VALIDATE),
        'apply_draft_content_plans' => $this->has($token, self::CONTENT_APPLY),
        'create_staged_update' => $this->has($token, self::CONTENT_APPLY),
        'replace_staged_update' => $this->has($token, self::CONTENT_APPLY),
        'promote_staged_update' => $this->has($token, self::CONTENT_APPLY) && $this->has($token, self::CONTENT_PUBLISH),
        'publish_page' => $this->has($token, self::CONTENT_PUBLISH),
        'delete_page' => $this->has($token, self::PAGES_DELETE),
        'write_site_presentation_settings' => $this->has($token, self::SITE_SETTINGS_WRITE),
      ],
    ];
  }

  public function summary(?CmsApiToken $token): string
  {
    $capabilities = $this->capabilitiesFor($token);
    $visible = array_slice($capabilities, 0, 3);
    $remaining = count($capabilities) - count($visible);

    return implode(', ', $visible).($remaining > 0 ? ' +'.$remaining : '');
  }
}
