<?php

namespace WebBlocks\Cms\Support\SystemPages;

use WebBlocks\Cms\Support\Plugins\PluginRegistry;

final class SystemPageRegistry
{
  /** @return array<string, SystemPageDefinition> */
  public function all(): array
  {
    $definitions = [
      'core:error-404' => new SystemPageDefinition('core:error-404', 'webblocks-cms::system_pages.not_found', blocks: [
        ['type' => 'hero', 'translations' => [
          'subtitle' => 'webblocks-cms::public.errors.not_found.eyebrow',
          'title' => 'webblocks-cms::public.errors.not_found.title',
          'content' => 'webblocks-cms::public.errors.not_found.body',
        ], 'children' => [
          ['type' => 'button_link', 'translations' => ['title' => 'webblocks-cms::public.errors.not_found.cta'], 'settings' => ['url' => '/', 'target' => '_self']],
        ]],
      ]),
      'core:error-503' => new SystemPageDefinition('core:error-503', 'webblocks-cms::system_pages.maintenance', blocks: [
        ['type' => 'hero', 'translations' => [
          'subtitle' => 'webblocks-cms::public.errors.maintenance.eyebrow',
          'title' => 'webblocks-cms::public.errors.maintenance.title',
          'content' => 'webblocks-cms::public.errors.maintenance.body',
        ]],
      ]),
    ];
    foreach (app(PluginRegistry::class)->enabled() as $plugin) {
      foreach ($plugin->systemPageDefinitions() as $definition) {
        $definitions[$definition->key] = $definition;
      }
    }

    return $definitions;
  }

  public function find(string $key): ?SystemPageDefinition
  {
    return $this->all()[$key] ?? null;
  }

  public function forRoute(?string $route): ?SystemPageDefinition
  {
    if ($route === null) {
      return null;
    }
    foreach ($this->all() as $definition) {
      if (in_array($route, $definition->routes, true)) {
        return $definition;
      }
    }

    return null;
  }
}
