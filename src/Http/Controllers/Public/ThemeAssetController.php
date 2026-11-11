<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebBlocks\Cms\Support\Theme\PublicThemes;

final class ThemeAssetController
{
  public function __invoke(Request $request, PublicThemes $themes, int $version, string $checksum, string $path): Response
  {
    $asset = $themes->asset($version, $checksum, $path);
    abort_if($asset === null, 404);

    $response = response($asset->contents)->withHeaders([
      'Content-Type' => $asset->mime,
      'X-Content-Type-Options' => 'nosniff',
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'public, max-age=31536000, immutable',
    ])->setEtag($asset->checksum);
    $response->isNotModified($request);

    return $response;
  }
}
