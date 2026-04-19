<?php

namespace App\Support\Release;

use App\Models\SystemRelease;
use App\Support\System\Updates\VersionComparator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ReleasePublisher
{
    public function __construct(
        private readonly VersionComparator $versionComparator,
    ) {}

    public function publish(array $build, array $options = []): array
    {
        if (! config('webblocks-release.publish.enabled', true)) {
            throw new ReleaseException('Release publishing is disabled in configuration.');
        }

        $serverUrl = rtrim((string) config('webblocks-release.publish.server_url', ''), '/');
        $token = (string) config('webblocks-release.publish.token', '');

        if ($serverUrl === '') {
            throw new ReleaseException('Configure WEBBLOCKS_RELEASE_PUBLISH_SERVER_URL before publishing a release.');
        }

        if ($token === '') {
            throw new ReleaseException('Configure WEBBLOCKS_RELEASE_PUBLISH_TOKEN before publishing a release.');
        }

        $request = Http::acceptJson()
            ->timeout((int) config('webblocks-release.publish.timeout_seconds', 30))
            ->connectTimeout((int) config('webblocks-release.publish.connect_timeout_seconds', 5))
            ->withToken($token)
            ->attach('package', File::get($build['absolute_path']), $build['filename'])
            ->asMultipart();

        $payload = [
            'product' => $build['product'],
            'version' => $build['version'],
            'channel' => $build['channel'],
            'name' => $options['release_name'] ?? 'WebBlocks CMS '.$build['version'],
            'description' => $options['description'] ?? null,
            'changelog' => $build['notes'] ?? null,
            'checksum_sha256' => $build['checksum_sha256'],
            'published_at' => $options['published_at'] ?? now()->toIso8601String(),
            'supported_from_version' => $options['minimum_supported_version'] ?? null,
            'supported_until_version' => $options['supported_until_version'] ?? null,
            'min_php_version' => $options['min_php_version'] ?? null,
            'min_laravel_version' => $options['min_laravel_version'] ?? null,
            'is_critical' => ($options['is_critical'] ?? false) ? '1' : '0',
            'is_security' => ($options['is_security'] ?? false) ? '1' : '0',
        ];

        try {
            $response = $request->post($serverUrl.'/api/updates/publish', array_filter($payload, fn ($value): bool => $value !== null));
        } catch (ConnectionException $exception) {
            throw new ReleaseException('Unable to reach the update server publish endpoint: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new ReleaseException('Update server publish failed: '.$message);
        }

        return [
            'server_url' => $serverUrl,
            'status' => $response->json('status'),
            'release' => $response->json('data.release'),
        ];
    }

    public function publishLocally(array $build, array $options = []): array
    {
        $downloadsDirectory = storage_path((string) config('webblocks-release.downloads_directory', 'app/public/releases'));
        File::ensureDirectoryExists($downloadsDirectory);

        $destinationPath = $downloadsDirectory.DIRECTORY_SEPARATOR.$build['filename'];
        File::copy($build['absolute_path'], $destinationPath);

        $downloadUrl = rtrim((string) config('app.url'), '/').rtrim((string) config('webblocks-release.downloads_url_prefix', '/storage/releases'), '/').'/'.$build['filename'];

        $release = SystemRelease::query()->updateOrCreate(
            [
                'product' => $build['product'],
                'channel' => $build['channel'],
                'version' => $build['version'],
            ],
            [
                'version_normalized' => $this->versionComparator->normalize($build['version']),
                'release_name' => $options['release_name'] ?? 'WebBlocks CMS '.$build['version'],
                'description' => $options['description'] ?? null,
                'changelog' => $build['notes'] ?? null,
                'download_url' => $downloadUrl,
                'checksum_sha256' => $build['checksum_sha256'],
                'is_critical' => (bool) ($options['is_critical'] ?? false),
                'is_security' => (bool) ($options['is_security'] ?? false),
                'published_at' => $options['published_at'] ?? now(),
                'supported_from_version' => $options['minimum_supported_version'] ?? null,
                'supported_until_version' => $options['supported_until_version'] ?? null,
                'min_php_version' => $options['min_php_version'] ?? null,
                'min_laravel_version' => $options['min_laravel_version'] ?? null,
                'metadata' => [
                    'package_size' => $build['size'],
                    'source_commit' => $build['source_commit'],
                    'source_tag' => $build['source_tag'],
                    'built_at' => $build['built_at'],
                ],
            ],
        );

        return [
            'server_url' => rtrim((string) config('app.url'), '/'),
            'status' => 'ok',
            'release' => [
                'version' => $release->version,
                'download_url' => $release->download_url,
            ],
        ];
    }
}
