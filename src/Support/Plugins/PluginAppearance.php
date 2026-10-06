<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\SystemSetting;

class PluginAppearance
{
  private ?array $overrides = null;

  private ?array $activeIcons = null;

  public function forget(): void
  {
    $this->overrides = null;
    $this->activeIcons = null;
  }

  public static function settingKey(string $handle): string
  {
    return 'plugins.sidebar_icon.'.$handle;
  }

  public function isAvailable(): bool
  {
    return Schema::hasTable((new SystemSetting)->getTable()) && Schema::hasTable((new IconCatalogItem)->getTable());
  }

  public function selectedSlug(PluginDefinition $plugin): ?string
  {
    $this->overrides ??= Schema::hasTable((new SystemSetting)->getTable())
      ? SystemSetting::query()->where('key', 'like', 'plugins.sidebar_icon.%')->pluck('value', 'key')->all()
      : [];
    $value = $this->overrides[self::settingKey($plugin->handle())] ?? null;

    return is_string($value) ? PluginDefinition::iconSlug($value) : null;
  }

  public function iconClass(PluginDefinition $plugin): string
  {
    $this->activeIcons ??= Schema::hasTable((new IconCatalogItem)->getTable())
      ? IconCatalogItem::query()->active()->pluck('slug')->all()
      : [];
    foreach ([$this->selectedSlug($plugin), $plugin->defaultIconSlug()] as $slug) {
      if ($slug !== null && in_array($slug, $this->activeIcons, true)) {
        return 'wb-icon-'.$slug;
      }
    }

    return 'wb-icon-plug';
  }

  public function settingsRouteName(PluginDefinition $plugin): string
  {
    return $plugin->settingsDefinition()?->routeName ?? $plugin->routeNamePrefix().'.settings.edit';
  }
}
