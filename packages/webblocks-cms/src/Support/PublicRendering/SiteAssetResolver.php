<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use WebBlocks\Cms\Models\Site;

class SiteAssetResolver
{
  public function cssPathFor(?Site $site): ?string
  {
    return $this->pathFor($site, 'css/site.css');
  }

  public function jsPathFor(?Site $site): ?string
  {
    return $this->pathFor($site, 'js/site.js');
  }

  private function pathFor(?Site $site, string $suffix): ?string
  {
    $handle = $site?->handle;

    if (! is_string($handle) || $handle === '') {
      return null;
    }

    $relativePath = 'site/'.$handle.'/'.$suffix;
    $absolutePath = public_path($relativePath);

    if (! is_file($absolutePath)) {
      return null;
    }

    $hash = hash_file('sha256', $absolutePath);
    $version = is_string($hash) ? substr($hash, 0, 12) : (string) filemtime($absolutePath);

    return '/'.$relativePath.'?v='.$version;
  }
}
