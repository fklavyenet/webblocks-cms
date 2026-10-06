<?php

namespace WebBlocks\Cms\Support\Plugins;

use RuntimeException;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;

class PluginIconDeclaration
{
  public function validate(array $manifest): void
  {
    $catalog = json_decode((string) file_get_contents(WebBlocksIconManifestSyncer::bundledManifestPath()), true);
    $known = array_column(is_array($catalog) ? $catalog : [], 'slug');
    $icon = $manifest['icon'] ?? null;
    if (array_key_exists('icon', $manifest)) {
      if (! is_string($icon) || ! in_array(PluginDefinition::iconSlug($icon), $known, true)) {
        throw new RuntimeException(__('webblocks-cms::admin.plugin_appearance.invalid_declaration'));
      }

      return;
    }
    $menu = $manifest['menu'] ?? [];
    if (is_array($menu) && $menu !== []) {
      foreach ($menu as $item) {
        $icon = is_array($item) ? ($item['icon'] ?? null) : null;
        // Existing packages declare menu classes, sometimes from older UI
        // catalogs. Retain that contract; new explicit defaults must be bundled.
        if (is_string($icon) && PluginDefinition::iconSlug($icon) !== null) {
          return;
        }
      }
      throw new RuntimeException(__('webblocks-cms::admin.plugin_appearance.invalid_declaration'));
    }
  }
}
