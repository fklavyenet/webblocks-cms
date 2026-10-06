<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PluginSidebarGroups
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginAppearance $appearance,
    private readonly CmsTranslator $translator,
  ) {}

  public function appendTo(array $groups, ?Authenticatable $user, string $locale): array
  {
    $owners = [];
    foreach ($this->plugins->menuItems($user) as $entry) {
      $item = $entry['item'];
      $plugin = $entry['plugin'];
      $route = $item->routeName();
      if ($route === null || ! Route::has($route)) {
        continue;
      }
      $groupName = $item->groupName() ?: 'System';
      $groupKey = $groupName === 'System' ? 'system' : 'plugin-'.Str::slug($groupName);
      $index = collect($groups)->search(fn ($group) => ($group['key'] ?? null) === $groupKey || $group['label'] === $groupName);
      if ($index === false) {
        $groups[] = [
          'key' => $groupKey,
          'label' => $this->translator->plugin($plugin->handle(), 'admin.menu_group.'.Str::slug($groupName), $locale, [], $groupName),
          'icon' => $this->appearance->iconClass($plugin),
          'items' => [],
        ];
        $index = array_key_last($groups);
        $owners[$index] = [$plugin->handle()];
      } elseif (isset($owners[$index]) && ! in_array($plugin->handle(), $owners[$index], true)) {
        // A shared heading cannot represent one plugin's personal appearance.
        $owners[$index][] = $plugin->handle();
        $groups[$index]['icon'] = 'wb-icon-plug';
      }
      $suffix = Str::afterLast($route, '.');
      $groups[$index]['items'][] = [
        'label' => $this->translator->plugin($plugin->handle(), 'admin.menu.'.$item->key(), $locale, [], $item->labelText()),
        'route' => $route,
        'url' => route($route, [], false),
        'active' => in_array($suffix, ['index', 'create', 'store', 'edit', 'update', 'show', 'destroy'], true) ? [Str::beforeLast($route, '.').'.*'] : [$route],
        'icon' => $item->iconClass(),
      ];
    }

    return $groups;
  }
}
