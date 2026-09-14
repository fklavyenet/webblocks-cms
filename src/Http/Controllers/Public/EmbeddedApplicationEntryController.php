<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Applications\ApplicationAssetStore;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class EmbeddedApplicationEntryController extends Controller
{
  public function __invoke(string $application, SiteResolver $sites, ApplicationAssetStore $assets, Request $request): Response
  {
    $record = EmbeddedApplication::query()
      ->where('handle', $application)
      ->where('is_enabled', true)
      ->firstOrFail();
    $asset = $assets->read($sites->current(), $record, 'html', 'index.html');

    abort_unless($asset['exists'], 404);

    $origin = $request->getSchemeAndHttpHost();

    return response($asset['contents'], 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'no-cache',
      'ETag' => '"'.$asset['checksum'].'"',
      'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline' {$origin}; style-src 'unsafe-inline' {$origin}; img-src {$origin} data: blob:; media-src {$origin} blob:; connect-src {$origin}; font-src {$origin} data:; object-src 'none'; base-uri {$origin}; form-action 'none'; frame-ancestors {$origin}",
      'Referrer-Policy' => 'no-referrer',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
}
