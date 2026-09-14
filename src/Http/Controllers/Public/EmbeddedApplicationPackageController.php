<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Routing\Controller;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Applications\ApplicationPackageStore;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class EmbeddedApplicationPackageController extends Controller
{
  public function __invoke(string $application, string $version, string $path, SiteResolver $sites, ApplicationPackageStore $packages): BinaryFileResponse
  {
    $record = EmbeddedApplication::query()->where('handle', $application)->where('is_enabled', true)->firstOrFail();

    try {
      $asset = $packages->read($sites->current(), $record, $version, $path);
    } catch (RuntimeException) {
      abort(404);
    }

    $response = response()->file($asset['absolute_path'], [
      'Cache-Control' => 'public, max-age=31536000, immutable',
      'ETag' => '"'.$asset['checksum'].'"',
      'Access-Control-Allow-Origin' => '*',
      'Cross-Origin-Resource-Policy' => 'cross-origin',
      'X-Content-Type-Options' => 'nosniff',
      'Referrer-Policy' => 'no-referrer',
    ]);

    if ($path === 'index.html') {
      $origin = request()->getSchemeAndHttpHost();
      $response->headers->set('Cache-Control', 'no-cache');
      $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'unsafe-inline' {$origin}; style-src 'unsafe-inline' {$origin}; img-src {$origin} data: blob:; media-src {$origin} blob:; connect-src {$origin}; font-src {$origin} data:; object-src 'none'; base-uri {$origin}; form-action 'none'; frame-ancestors {$origin}");
    }

    return $response;
  }
}
