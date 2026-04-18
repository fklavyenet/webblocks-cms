<?php

namespace App\Http\Controllers\Api\Updates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Updates\LatestReleaseRequest;
use App\Http\Resources\Updates\LatestReleaseResource;
use App\Support\System\Updates\ReleaseCatalog;
use App\Support\System\Updates\UpdateApiResponse;
use App\Support\System\Updates\VersionComparator;

class LatestReleaseController extends Controller
{
    public function __invoke(LatestReleaseRequest $request, ReleaseCatalog $releaseCatalog, VersionComparator $versionComparator)
    {
        $release = $releaseCatalog->latestPublished(
            $request->string('product')->toString(),
            $request->string('channel')->toString(),
            $request->string('php_version')->toString() ?: null,
            $request->string('laravel_version')->toString() ?: null,
        );

        if ($release === null) {
            return UpdateApiResponse::error('release_not_found', 'No published release was found for the requested product and channel.', 404);
        }

        $installedVersion = $request->string('installed_version')->toString();
        $compatibility = $installedVersion === ''
            ? ['status' => 'unknown', 'reasons' => []]
            : (function () use ($versionComparator, $release, $request, $installedVersion): array {
                $resolved = $versionComparator->isCompatible(
                    $release,
                    $installedVersion,
                    $request->string('php_version')->toString() ?: null,
                    $request->string('laravel_version')->toString() ?: null,
                );

                return [
                    'status' => $resolved->status,
                    'reasons' => $resolved->reasons,
                ];
            })();

        return UpdateApiResponse::success(LatestReleaseResource::make([
            'product' => $release->product,
            'channel' => $release->channel,
            'installed_version' => $installedVersion !== '' ? $installedVersion : null,
            'latest_version' => $release->version,
            'update_available' => $installedVersion !== '' ? $versionComparator->isNewer($release->version, $installedVersion) : true,
            'compatibility' => $compatibility,
            'release' => $release,
        ])->resolve());
    }
}
