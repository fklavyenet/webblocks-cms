<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Support\Facades\Cache;
use Throwable;
use WebBlocks\Cms\Support\ExtensionsCatalog;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\WebBlocks;

final class PluginCatalogUpdates
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginCatalogClient $catalog,
  ) {}

  public function snapshot(): array
  {
    $installed = $this->installedVersions();
    $cached = Cache::get($this->cacheKey(), []);
    if (($cached['cms_version'] ?? null) !== WebBlocks::version()) {
      $cached = [];
    }
    $updates = array_filter($cached['updates'] ?? [], fn (array $update, string $handle): bool => isset($installed[$handle])
      && version_compare($update['version'], $installed[$handle], '>'), ARRAY_FILTER_USE_BOTH);
    $checkedAt = $cached['checked_at'] ?? null;
    $due = $installed !== [] && ($checkedAt === null || now()->getTimestamp() - $checkedAt >= 3600);

    return [
      'updates' => $updates,
      'checked_at' => $checkedAt,
      'due' => $due,
      'retry_pending' => $due && Cache::has($this->cacheKey().':retry'),
    ];
  }

  public function refreshIfDue(): array
  {
    $snapshot = $this->snapshot();
    if (! $snapshot['due'] || $snapshot['retry_pending']) {
      return $snapshot;
    }

    $key = $this->cacheKey();
    $lock = Cache::lock($key.':refresh', 120);
    if (! $lock->get()) {
      return $snapshot;
    }

    try {
      $snapshot = $this->snapshot();
      if (! $snapshot['due'] || $snapshot['retry_pending']) {
        return $snapshot;
      }

      try {
        $releases = $this->catalog->installedReleases(array_keys($this->installedVersions()));
      } catch (Throwable) {
        $releases = null;
      }

      if ($releases === null) {
        Cache::put($key.':retry', true, now()->addMinutes(5));
      } else {
        $updates = [];
        foreach ($releases as $handle => $release) {
          if ($release->hasInstallableArtifact()) {
            $updates[$handle] = [
              'version' => $release->version,
              'summary' => $release->summary,
              'notes' => $release->notes,
              'highlights' => $release->highlights,
              'note_items' => $release->noteItems(),
              'details_url' => $release->detailsUrl,
            ];
          }
        }
        // Keep the last successful check available during catalog outages.
        Cache::forever($key, [
          'cms_version' => WebBlocks::version(),
          'updates' => $updates,
          'checked_at' => now()->getTimestamp(),
        ]);
      }
    } finally {
      $lock->release();
    }

    return $this->snapshot();
  }

  /** @return array<string, string> */
  private function installedVersions(): array
  {
    $versions = [];
    foreach ($this->plugins->all() as $plugin) {
      $path = $plugin->installPathValue();
      $version = $plugin->versionText();
      if ($path !== null && $version !== null && is_dir($path)) {
        $versions[$plugin->handle()] = $version;
      }
    }

    return $versions;
  }

  private function cacheKey(): string
  {
    return 'webblocks-cms:plugin-catalog-updates:'.hash('sha256', ExtensionsCatalog::pluginBaseUrl());
  }
}
