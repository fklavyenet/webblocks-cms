<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Applications\ApplicationAssetStore;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class EmbeddedApplicationEntryController extends Controller
{
  public function __invoke(string $application, SiteResolver $sites, ApplicationAssetStore $assets): Response
  {
    $record = EmbeddedApplication::query()
      ->where('handle', $application)
      ->where('is_enabled', true)
      ->firstOrFail();
    $asset = $assets->read($sites->current(), $record, 'html', 'index.html');

    abort_unless($asset['exists'], 404);

    return response($asset['contents'], 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'no-cache',
      'ETag' => '"'.$asset['checksum'].'"',
      'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; font-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
      'Referrer-Policy' => 'no-referrer',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
}
