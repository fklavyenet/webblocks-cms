<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;

class WebBlocksUiArtifactManifestBuilder
{
  public function __construct(
    private readonly WebBlocksUiManagerPaths $paths,
  ) {}

  /**
   * @param  array<int, string>  $artifactPaths
   * @return array<string, mixed>
   */
  public function prepare(WebBlocksUiRelease $release, array $artifactPaths, bool $writeManifest = false): array
  {
    $artifacts = [];

    foreach ($artifactPaths as $artifactPath) {
      $artifact = $this->artifactPayload($release, $artifactPath);

      if (collect($artifacts)->contains(fn (array $existing): bool => $existing['handle'] === $artifact['handle'])) {
        throw new InvalidArgumentException("Artifact handle [{$artifact['handle']}] is already present in this release manifest.");
      }

      $artifacts[] = $artifact;
    }

    $manifest = [
      'plugin' => 'webblocks-ui-manager',
      'version' => $release->version,
      'cdn_base_path' => $release->cdn_base_path ?: $this->paths->releasePublicDirectory($release->version),
      'cdn_base_url' => $release->cdn_base_url,
      'generated_at' => now()->toIso8601String(),
      'artifacts' => $artifacts,
    ];

    if ($writeManifest) {
      File::ensureDirectoryExists($this->paths->releasePublicPath($release->version));
      File::put(
        $this->paths->manifestPublicPath($release->version),
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
      );
    }

    return $manifest;
  }

  /**
   * @return array<string, mixed>
   */
  private function artifactPayload(WebBlocksUiRelease $release, string $artifactPath): array
  {
    $path = trim($artifactPath);

    if ($path === '') {
      throw new InvalidArgumentException('Artifact path cannot be empty.');
    }

    if (! is_file($path)) {
      throw new InvalidArgumentException("Artifact [{$path}] does not exist.");
    }

    $filename = basename($path);
    $handle = Str::of($filename)
      ->lower()
      ->replaceMatches('/[^a-z0-9.]+/', '-')
      ->trim('-')
      ->value();

    $targetPath = trim(($release->cdn_base_path ?: $this->paths->releasePublicDirectory($release->version)).'/'.$filename, '/');

    return [
      'handle' => $handle,
      'filename' => $filename,
      'source_path' => $path,
      'target_path' => $targetPath,
      'checksum_sha256' => hash_file('sha256', $path),
      'size_bytes' => filesize($path),
      'mime_type' => File::mimeType($path),
    ];
  }
}
