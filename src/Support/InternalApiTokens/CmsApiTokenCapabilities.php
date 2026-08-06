<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\Plugins\PluginApiCapabilityRegistrar;

class CmsApiTokenCapabilities
{
  public function __construct(
    private readonly PluginApiCapabilityRegistrar $pluginCapabilities,
  ) {}

  public const CONTENT_READ = 'content.read';

  public const CONTENT_VALIDATE = 'content.validate';

  public const CONTENT_APPLY = 'content.apply';

  public const CONTENT_PUBLISH = 'content.publish';

  public const ADMIN_RENDER = 'admin.render';

  public const PAGES_DELETE = 'pages.delete';

  public const CONTENT_BLOCKS_DELETE = 'content.blocks.delete';

  public const BACKUPS_CREATE = 'backups.create';

  public const PAGE_ASSETS_WRITE = 'page-assets.write';

  public const NAVIGATION_WRITE = 'navigation.write';

  public const NAVIGATION_DELETE = 'navigation.delete';

  public const SHARED_SLOTS_WRITE = 'shared-slots.write';

  public const SHARED_SLOTS_DELETE = 'shared-slots.delete';

  public const MEDIA_READ = 'media.read';

  public const MEDIA_WRITE = 'media.write';

  public const MEDIA_UPLOAD = 'media.upload';

  public const MEDIA_REPLACE = 'media.replace';

  public const MEDIA_MOVE = 'media.move';

  public const MEDIA_DELETE = 'media.delete';

  public const SITE_SETTINGS_WRITE = 'site-settings.write';

  public const DOMAINS_WRITE = 'domains.write';

  public const DOMAINS_DELETE = 'domains.delete';

  public const SITE_ASSETS_READ = 'site-assets.read';

  public const SITE_ASSETS_WRITE = 'site-assets.write';

  public const ENGAGEMENT_READ = 'engagement.read';

  public const ENGAGEMENT_MODERATE = 'engagement.moderate';

  public const PLUGINS_READ = 'plugins.read';

  public const PLUGINS_INSTALL = 'plugins.install';

  public const PLUGINS_MANAGE = 'plugins.manage';

  public const PLUGINS_SETUP = 'plugins.setup';

  public const PLUGINS_UNINSTALL = 'plugins.uninstall';

  public const DEFAULT = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::ADMIN_RENDER,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::MEDIA_READ,
    self::SITE_SETTINGS_WRITE,
  ];

  private const LEGACY_DEFAULT_BEFORE_ADMIN_RENDER = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::NAVIGATION_WRITE,
    self::SHARED_SLOTS_WRITE,
    self::MEDIA_READ,
    self::SITE_SETTINGS_WRITE,
  ];

  public const ADVANCED = [
    self::DOMAINS_WRITE,
    self::DOMAINS_DELETE,
    self::SITE_ASSETS_READ,
    self::SITE_ASSETS_WRITE,
    self::ENGAGEMENT_READ,
    self::ENGAGEMENT_MODERATE,
    self::PLUGINS_READ,
    self::PLUGINS_INSTALL,
    self::PLUGINS_MANAGE,
    self::PLUGINS_SETUP,
    self::PLUGINS_UNINSTALL,
    self::MEDIA_WRITE,
    self::MEDIA_UPLOAD,
    self::MEDIA_REPLACE,
    self::MEDIA_MOVE,
    self::MEDIA_DELETE,
    self::NAVIGATION_DELETE,
    self::SHARED_SLOTS_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
    self::CONTENT_BLOCKS_DELETE,
    self::BACKUPS_CREATE,
    self::PAGE_ASSETS_WRITE,
    self::PLUGINS_INSTALL,
    self::PLUGINS_MANAGE,
    self::PLUGINS_SETUP,
    self::PLUGINS_UNINSTALL,
  ];

  public const DESTRUCTIVE = [
    self::MEDIA_REPLACE,
    self::MEDIA_DELETE,
    self::NAVIGATION_DELETE,
    self::SHARED_SLOTS_DELETE,
    self::DOMAINS_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
    self::CONTENT_BLOCKS_DELETE,
  ];

  public const ALL = [
    self::CONTENT_READ,
    self::CONTENT_VALIDATE,
    self::CONTENT_APPLY,
    self::ADMIN_RENDER,
    self::NAVIGATION_WRITE,
    self::NAVIGATION_DELETE,
    self::SHARED_SLOTS_WRITE,
    self::SHARED_SLOTS_DELETE,
    self::MEDIA_READ,
    self::SITE_SETTINGS_WRITE,
    self::DOMAINS_WRITE,
    self::DOMAINS_DELETE,
    self::MEDIA_WRITE,
    self::MEDIA_UPLOAD,
    self::MEDIA_REPLACE,
    self::MEDIA_MOVE,
    self::MEDIA_DELETE,
    self::CONTENT_PUBLISH,
    self::PAGES_DELETE,
    self::CONTENT_BLOCKS_DELETE,
    self::BACKUPS_CREATE,
    self::PAGE_ASSETS_WRITE,
    self::SITE_ASSETS_READ,
    self::SITE_ASSETS_WRITE,
    self::ENGAGEMENT_READ,
    self::ENGAGEMENT_MODERATE,
    self::PLUGINS_READ,
    self::PLUGINS_INSTALL,
    self::PLUGINS_MANAGE,
    self::PLUGINS_SETUP,
    self::PLUGINS_UNINSTALL,
  ];

  public const LABELS = [
    self::CONTENT_READ => 'Read content metadata and contracts',
    self::CONTENT_VALIDATE => 'Validate content plans',
    self::CONTENT_APPLY => 'Apply draft content plans',
    self::ADMIN_RENDER => 'Render allowlisted admin screen snapshots',
    self::NAVIGATION_WRITE => 'Write navigation menu items',
    self::NAVIGATION_DELETE => 'Delete navigation menu items',
    self::SHARED_SLOTS_WRITE => 'Write Shared Slots',
    self::SHARED_SLOTS_DELETE => 'Delete unreferenced Shared Slots',
    self::MEDIA_READ => 'Read Media Library records',
    self::SITE_SETTINGS_WRITE => 'Write safe site presentation settings',
    self::DOMAINS_WRITE => 'Add and change site domains',
    self::DOMAINS_DELETE => 'Remove site domains',
    self::SITE_ASSETS_READ => 'Read canonical site CSS and JS override files',
    self::SITE_ASSETS_WRITE => 'Write canonical site CSS and JS override files',
    self::ENGAGEMENT_READ => 'Read public comments and ratings',
    self::ENGAGEMENT_MODERATE => 'Moderate public comments',
    self::PLUGINS_READ => 'Read installed plugin status',
    self::PLUGINS_INSTALL => 'Install manually uploaded plugin ZIP artifacts',
    self::PLUGINS_MANAGE => 'Enable or disable installed plugins',
    self::PLUGINS_SETUP => 'Run plugin setup migrations',
    self::PLUGINS_UNINSTALL => 'Uninstall disabled manually uploaded plugins',
    self::MEDIA_WRITE => 'Write safe Media Library metadata',
    self::MEDIA_UPLOAD => 'Upload Media Library files',
    self::MEDIA_REPLACE => 'Replace Media Library files',
    self::MEDIA_MOVE => 'Move Media Library files between folders',
    self::MEDIA_DELETE => 'Delete unused Media Library files',
    self::CONTENT_PUBLISH => 'Publish content',
    self::PAGES_DELETE => 'Delete pages',
    self::CONTENT_BLOCKS_DELETE => 'Delete page-owned blocks',
    self::BACKUPS_CREATE => 'Create a system backup restore point before content apply',
    self::PAGE_ASSETS_WRITE => 'Attach page-specific /site CSS and JS assets to pages',
  ];

  /**
   * All grantable capabilities: CMS core plus capabilities contributed by
   * currently-enabled plugins. Used to validate token grants and drive the UI.
   *
   * @return list<string>
   */
  public function grantable(): array
  {
    return array_values(array_unique([...self::ALL, ...$this->pluginCapabilities->names()]));
  }

  /**
   * Advanced (opt-in) grantable capabilities, core plus enabled-plugin ones.
   *
   * @return list<string>
   */
  public function advancedGrantable(): array
  {
    return array_values(array_unique([...self::ADVANCED, ...$this->pluginCapabilities->names()]));
  }

  /**
   * Capability => label for every grantable capability, core plus plugin.
   *
   * @return array<string, string>
   */
  public function labelsAll(): array
  {
    return [...self::LABELS, ...$this->pluginCapabilities->labels()];
  }

  public function capabilitiesFor(?CmsApiToken $token): array
  {
    if (! $token) {
      return [];
    }

    $capabilities = $token->capabilities;

    if (! is_array($capabilities) || $capabilities === []) {
      return self::DEFAULT;
    }

    $normalized = collect($capabilities)
      ->filter(fn ($capability) => is_string($capability) && $capability !== '')
      ->unique()
      ->values()
      ->all();

    if ($this->usesLegacyDefaultWithoutAdminRender($normalized)) {
      array_splice($normalized, 3, 0, [self::ADMIN_RENDER]);
    }

    return $normalized;
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
        'archive_page' => $this->has($token, self::CONTENT_PUBLISH),
        'render_admin_snapshots' => $this->has($token, self::ADMIN_RENDER),
        'delete_page' => $this->has($token, self::PAGES_DELETE),
        'write_navigation_items' => $this->has($token, self::NAVIGATION_WRITE),
        'delete_navigation_items' => $this->has($token, self::NAVIGATION_DELETE),
        'write_shared_slots' => $this->has($token, self::SHARED_SLOTS_WRITE),
        'delete_shared_slots' => $this->has($token, self::SHARED_SLOTS_DELETE),
        'read_media' => $this->has($token, self::MEDIA_READ) || $this->has($token, self::CONTENT_READ),
        'write_media_metadata' => $this->has($token, self::MEDIA_WRITE),
        'upload_media' => $this->has($token, self::MEDIA_UPLOAD),
        'replace_media_files' => $this->has($token, self::MEDIA_REPLACE),
        'move_media' => $this->has($token, self::MEDIA_MOVE),
        'delete_media' => $this->has($token, self::MEDIA_DELETE),
        'write_site_presentation_settings' => $this->has($token, self::SITE_SETTINGS_WRITE),
        'write_site_domains' => $this->has($token, self::DOMAINS_WRITE),
        'delete_site_domains' => $this->has($token, self::DOMAINS_DELETE),
        'read_site_assets' => $this->has($token, self::SITE_ASSETS_READ),
        'write_site_assets' => $this->has($token, self::SITE_ASSETS_WRITE),
        'read_engagement' => $this->has($token, self::ENGAGEMENT_READ),
        'moderate_engagement_comments' => $this->has($token, self::ENGAGEMENT_MODERATE),
        'read_plugins' => $this->has($token, self::PLUGINS_READ),
        'install_plugins' => $this->has($token, self::PLUGINS_INSTALL),
        'manage_plugins' => $this->has($token, self::PLUGINS_MANAGE),
        'setup_plugins' => $this->has($token, self::PLUGINS_SETUP),
        'uninstall_plugins' => $this->has($token, self::PLUGINS_UNINSTALL),
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

  private function usesLegacyDefaultWithoutAdminRender(array $capabilities): bool
  {
    if (in_array(self::ADMIN_RENDER, $capabilities, true)) {
      return false;
    }

    foreach (self::LEGACY_DEFAULT_BEFORE_ADMIN_RENDER as $capability) {
      if (! in_array($capability, $capabilities, true)) {
        return false;
      }
    }

    return true;
  }
}
