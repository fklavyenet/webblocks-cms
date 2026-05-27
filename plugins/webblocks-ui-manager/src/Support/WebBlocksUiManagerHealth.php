<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginHealthResult;

class WebBlocksUiManagerHealth
{
  public function __construct(
    private readonly WebBlocksUiManagerPaths $paths,
  ) {}

  public function health(PluginDefinition $plugin): PluginHealthResult
  {
    if (! Schema::hasTable('webblocks_ui_manager_releases') || ! Schema::hasTable('webblocks_ui_manager_artifacts') || ! Schema::hasTable('webblocks_ui_manager_publish_runs')) {
      return PluginHealthResult::warning('Plugin release tables are not available. Run the latest migrations before using WebBlocks UI Manager.');
    }

    $basePath = $this->paths->defaultCdnBasePath();

    if ($basePath === '') {
      return PluginHealthResult::warning('The WebBlocks UI Manager CDN base path is not configured.');
    }

    $trackedCount = WebBlocksUiRelease::query()
      ->whereIn('status', [WebBlocksUiRelease::STATUS_PREPARED, WebBlocksUiRelease::STATUS_PUBLISHED])
      ->count();

    if ($trackedCount === 0) {
      return PluginHealthResult::unknown('No prepared WebBlocks UI release metadata has been recorded yet.');
    }

    if (! is_dir(public_path($basePath)) && ! is_writable(public_path())) {
      return PluginHealthResult::warning('Configured local CDN base path is not present and public path is not writable.');
    }

    if (is_dir(public_path($basePath)) && ! is_writable(public_path($basePath))) {
      return PluginHealthResult::warning('Configured local CDN base path is not writable.');
    }

    $missingPublishedManifest = WebBlocksUiRelease::query()
      ->where('status', WebBlocksUiRelease::STATUS_PUBLISHED)
      ->whereNotNull('manifest_path')
      ->get()
      ->contains(fn (WebBlocksUiRelease $release): bool => ! is_file(public_path($release->manifest_path)));

    if ($missingPublishedManifest) {
      return PluginHealthResult::warning('One or more published WebBlocks UI release records reference a missing local manifest file.');
    }

    $latestPublished = WebBlocksUiRelease::query()
      ->where('status', WebBlocksUiRelease::STATUS_PUBLISHED)
      ->latest('published_at')
      ->first();

    if ($latestPublished instanceof WebBlocksUiRelease) {
      return PluginHealthResult::healthy('Latest published WebBlocks UI release: '.$latestPublished->version.'.');
    }

    return PluginHealthResult::healthy($trackedCount.' prepared WebBlocks UI release record(s) are ready for dry-run or publish.');
  }
}
