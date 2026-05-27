<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\File;
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
    if (! Schema::hasTable('webblocks_ui_manager_releases') || ! Schema::hasTable('webblocks_ui_manager_artifacts')) {
      return PluginHealthResult::warning('Plugin release tables are not available. Run the latest migrations before using WebBlocks UI Manager.');
    }

    $basePath = $this->paths->defaultCdnBasePath();

    if ($basePath === '') {
      return PluginHealthResult::warning('The WebBlocks UI Manager CDN base path is not configured.');
    }

    $preparedCount = WebBlocksUiRelease::query()
      ->where('status', WebBlocksUiRelease::STATUS_PREPARED)
      ->count();

    if ($preparedCount === 0) {
      return PluginHealthResult::unknown('No prepared WebBlocks UI release metadata has been recorded yet.');
    }

    if (! File::isDirectory(public_path($basePath))) {
      return PluginHealthResult::warning('Prepared WebBlocks UI release metadata exists, but the configured local CDN base path is not present yet.');
    }

    $missingManifest = WebBlocksUiRelease::query()
      ->where('status', WebBlocksUiRelease::STATUS_PREPARED)
      ->whereNotNull('manifest_path')
      ->get()
      ->contains(fn (WebBlocksUiRelease $release): bool => ! File::exists(public_path($release->manifest_path)));

    if ($missingManifest) {
      return PluginHealthResult::warning('One or more prepared WebBlocks UI release records reference a missing local manifest file.');
    }

    return PluginHealthResult::healthy($preparedCount.' prepared WebBlocks UI release record(s) are available.');
  }
}
