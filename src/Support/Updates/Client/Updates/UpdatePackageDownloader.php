<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the release artifact to the workspace. The User-Agent is
 * product-driven instead of hard-coded.
 */
class UpdatePackageDownloader
{
  public function download(string $url, string $destinationPath): void
  {
    try {
      $response = Http::timeout((int) config('publisher-client.apply.download_timeout_seconds', 120))
        ->connectTimeout((int) config('publisher-client.connect_timeout_seconds', 3))
        ->withHeaders([
          'User-Agent' => $this->userAgent(),
        ])
        ->get($url);
    } catch (ConnectionException $exception) {
      throw new UpdateException(
        'The update package could not be downloaded.',
        'Package download failed: '.$exception->getMessage(),
        previous: $exception,
      );
    }

    if (! $response->successful()) {
      throw new UpdateException(
        'The update package could not be downloaded.',
        'Package download failed with HTTP '.$response->status().'.',
      );
    }

    $body = $response->body();

    if ($body === '') {
      throw new UpdateException('The update package download was empty.', 'Package download returned an empty body.');
    }

    File::ensureDirectoryExists(dirname($destinationPath));
    File::put($destinationPath, $body);
  }

  private function userAgent(): string
  {
    $product = (string) config('publisher-client.product', 'unknown');
    $version = (string) config('app.version', 'unknown');

    return 'WebBlocks-Publisher-Client/'.$product.'/'.$version;
  }
}
