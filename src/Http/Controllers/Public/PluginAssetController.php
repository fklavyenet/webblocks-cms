<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use WebBlocks\Cms\Support\Plugins\PluginAssetPublisher;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginAssetController
{
  public function __invoke(
    Request $request,
    string $plugin,
    string $path,
    PluginRegistry $plugins,
    PluginAssetPublisher $publisher,
  ): BinaryFileResponse {
    abort_unless(PluginDefinition::isValidHandle($plugin), 404);

    $definition = $plugins->get($plugin);

    abort_if($definition === null, 404);

    $file = $publisher->sourceFileFor($definition, $path);

    abort_if($file === null, 404);

    $versioned = $definition->versionText() !== null
      && hash_equals($definition->versionText(), (string) $request->query('v', ''));

    return response()->file($file, [
      'Cache-Control' => $versioned
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=300',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
}
