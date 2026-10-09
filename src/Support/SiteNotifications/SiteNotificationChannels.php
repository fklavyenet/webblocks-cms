<?php

namespace WebBlocks\Cms\Support\SiteNotifications;

use InvalidArgumentException;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

final class SiteNotificationChannels
{
  private array $channels = ['contact' => ['class' => ContactNotificationChannel::class, 'plugin' => null]];

  public function register(string $key, string $channel, ?string $plugin = null): void
  {
    if (! preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $key) || ! is_a($channel, SiteNotificationChannel::class, true)) {
      throw new InvalidArgumentException('Invalid site notification channel.');
    }
    $this->channels[$key] = ['class' => $channel, 'plugin' => $plugin];
  }

  public function all(): array
  {
    $active = array_filter($this->channels, fn (array $entry) => $entry['plugin'] === null || app(PluginRegistry::class)->isActive($entry['plugin']));

    return array_map(fn (array $entry) => app($entry['class']), $active);
  }
}
