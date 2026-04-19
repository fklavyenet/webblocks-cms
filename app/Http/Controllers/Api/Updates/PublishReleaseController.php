<?php

namespace App\Http\Controllers\Api\Updates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Updates\PublishReleaseRequest;
use App\Models\SystemRelease;
use App\Support\System\Updates\UpdateApiResponse;
use App\Support\System\Updates\VersionComparator;
use Illuminate\Support\Facades\Storage;

class PublishReleaseController extends Controller
{
    public function __invoke(PublishReleaseRequest $request, VersionComparator $versionComparator)
    {
        $package = $request->file('package');
        $filename = $package->getClientOriginalName();
        $relativePath = trim((string) config('webblocks-release.downloads_directory', 'app/public/releases'), '/').'/'.$filename;
        Storage::disk('local')->put($relativePath, file_get_contents($package->getRealPath()));

        $downloadUrl = rtrim((string) config('webblocks-updates.server.base_url', config('app.url')), '/').rtrim((string) config('webblocks-release.downloads_url_prefix', '/storage/releases'), '/').'/'.$filename;

        $release = SystemRelease::query()->updateOrCreate(
            [
                'product' => $request->string('product')->toString(),
                'channel' => $request->string('channel')->toString(),
                'version' => $request->string('version')->toString(),
            ],
            [
                'version_normalized' => $versionComparator->normalize($request->string('version')->toString()),
                'release_name' => $request->string('name')->toString() ?: 'WebBlocks CMS '.$request->string('version')->toString(),
                'description' => $request->input('description'),
                'changelog' => $request->input('changelog'),
                'download_url' => $downloadUrl,
                'checksum_sha256' => $request->string('checksum_sha256')->toString(),
                'is_critical' => $request->boolean('is_critical'),
                'is_security' => $request->boolean('is_security'),
                'published_at' => $request->input('published_at') ?: now(),
                'supported_from_version' => $request->input('supported_from_version'),
                'supported_until_version' => $request->input('supported_until_version'),
                'min_php_version' => $request->input('min_php_version'),
                'min_laravel_version' => $request->input('min_laravel_version'),
                'metadata' => [
                    'package_filename' => $filename,
                    'package_size' => $package->getSize(),
                    'stored_path' => $relativePath,
                ],
            ],
        );

        return UpdateApiResponse::success([
            'product' => $release->product,
            'release' => [
                'version' => $release->version,
                'download_url' => $release->download_url,
                'checksum_sha256' => $release->checksum_sha256,
                'published_at' => $release->published_at?->toIso8601String(),
            ],
        ]);
    }
}
