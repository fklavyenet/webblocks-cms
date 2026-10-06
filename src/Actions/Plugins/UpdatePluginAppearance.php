<?php

namespace WebBlocks\Cms\Actions\Plugins;

use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Plugins\PluginAppearance;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;

class UpdatePluginAppearance
{
  public function execute(PluginDefinition $plugin, ?string $slug): void
  {
    $key = PluginAppearance::settingKey($plugin->handle());
    if ($slug === null || $slug === '') {
      SystemSetting::query()->where('key', $key)->delete();
    } else {
      SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $slug]);
    }
    app(PluginAppearance::class)->forget();
  }
}
