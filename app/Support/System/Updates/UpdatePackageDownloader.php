<?php

namespace App\Support\System\Updates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdatePackageDownloader
{
    public function download(string $url, string $destinationPath): void
    {
        try {
            $response = Http::timeout((int) config('webblocks-updates.installer.download_timeout_seconds', 120))
                ->connectTimeout((int) config('webblocks-updates.client.connect_timeout_seconds', 3))
                ->withHeaders([
                    'User-Agent' => 'WebBlocks-CMS-Updater/'.config('app.version', 'unknown'),
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
}
