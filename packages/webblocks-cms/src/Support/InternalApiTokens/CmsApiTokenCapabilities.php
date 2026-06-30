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

  public const MEDIA_READ = 'media.read';

  public const MEDIA_WRITE = 'media.write';

  public const MEDIA_UPLOAD = 'media.upload';

  public const MEDIA_REPLACE = 'media.replace';

  public const MEDIA_MOVE = 'media.move';

  public const MEDIA_DELETE = 'media.delete';

  public const SITE_SETTINGS_WRITE = 'site-settings.write';

  public const SITE_ASSETS_READ = 'site-assets.read';

  public const SITE_ASSETS_WRITE = 'site-assets.write';

  public const DEFAULT = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::MEDIA_READ,
    self::SITE_SETTINGS_WRITE,
  ];

  public const ADVANCED = [
    self::SITE_ASSETS_READ,
    self::SITE_ASSETS_WRITE,
    self::MEDIA_WRITE,
    self::MEDIA_UPLOAD,
    self::MEDIA_REPLACE,
    self::MEDIA_MOVE,
    self::MEDIA_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
  ];

  public const DESTRUCTIVE = [
    self::MEDIA_REPLACE,
    self::MEDIA_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
  ];

  public const ALL = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::MEDIA_READ,
    self::SITE_SETTINGS_WRITE,
    self::MEDIA_WRITE,
    self::MEDIA_UPLOAD,
    self::MEDIA_REPLACE,
    self::MEDIA_MOVE,
    self::MEDIA_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
    self::SITE_ASSETS_READ,
    self::SITE_ASSETS_WRITE,
  ];

  public const LABELS = [
    self::CONTENT_READ => 'Read content metadata and contracts',
    self::CONTENT_VALIDATE => 'Validate content plans',
    self::CONTENT_APPLY => 'Apply draft content plans',
    self::NAVIGATION_WRITE => 'Write navigation menu items',
    self::SHARED_SLOTS_WRITE => 'Write Shared Slots',
    self::MEDIA_READ => 'Read Media Library records',
    self::SITE_SETTINGS_WRITE => 'Write safe site presentation settings',
    self::SITE_ASSETS_READ => 'Read canonical site CSS and JS override files',
    self::SITE_ASSETS_WRITE => 'Write canonical site CSS and JS override files',
    self::MEDIA_WRITE => 'Write safe Media Library metadata',
    self::MEDIA_UPLOAD => 'Upload Media Library files',
    self::MEDIA_REPLACE => 'Replace Media Library files',
    self::MEDIA_MOVE => 'Move Media Library files between folders',
    self::MEDIA_DELETE => 'Delete unused Media Library files',
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
        'read_media' => $this->has($token, self::MEDIA_READ) || $this->has($token, self::CONTENT_READ),
        'write_media_metadata' => $this->has($token, self::MEDIA_WRITE),
        'upload_media' => $this->has($token, self::MEDIA_UPLOAD),
        'replace_media_files' => $this->has($token, self::MEDIA_REPLACE),
        'move_media' => $this->has($token, self::MEDIA_MOVE),
        'delete_media' => $this->has($token, self::MEDIA_DELETE),
        'write_site_presentation_settings' => $this->has($token, self::SITE_SETTINGS_WRITE),
        'read_site_assets' => $this->has($token, self::SITE_ASSETS_READ),
        'write_site_assets' => $this->has($token, self::SITE_ASSETS_WRITE),
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
