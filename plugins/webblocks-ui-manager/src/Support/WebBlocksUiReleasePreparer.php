<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiArtifact;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;

class WebBlocksUiReleasePreparer
{
  public function __construct(
    private readonly WebBlocksUiArtifactManifestBuilder $manifestBuilder,
    private readonly WebBlocksUiManagerPaths $paths,
  ) {}

  /**
   * @param  array<int, string>  $artifactPaths
   * @return array<string, mixed>
   */
  public function prepare(string $version, array $artifactPaths, bool $writeManifest = false): array
  {
    return DB::transaction(function () use ($version, $artifactPaths, $writeManifest): array {
      $release = WebBlocksUiRelease::query()->firstOrCreate(
        ['version' => $version],
        [
          'label' => 'WebBlocks UI '.$version,
          'status' => WebBlocksUiRelease::STATUS_DRAFT,
          'cdn_base_path' => $this->paths->releasePublicDirectory($version),
          'cdn_base_url' => $this->paths->defaultCdnBaseUrl(),
        ]
      );

      $manifest = $this->manifestBuilder->prepare($release, $artifactPaths, $writeManifest);

      $release->forceFill([
        'status' => WebBlocksUiRelease::STATUS_PREPARED,
        'manifest_path' => $writeManifest ? $this->paths->manifestRelativePath($version) : null,
        'manifest' => $manifest,
        'prepared_at' => now(),
      ])->save();

      WebBlocksUiArtifact::query()
        ->where('release_id', $release->id)
        ->delete();

      foreach ($manifest['artifacts'] as $artifact) {
        WebBlocksUiArtifact::query()->create([
          'release_id' => $release->id,
          'handle' => $artifact['handle'],
          'source_path' => $artifact['source_path'],
          'target_path' => $artifact['target_path'],
          'public_url' => $release->cdn_base_url ? rtrim($release->cdn_base_url, '/').'/'.basename($artifact['target_path']) : null,
          'checksum_sha256' => $artifact['checksum_sha256'],
          'size_bytes' => $artifact['size_bytes'],
          'mime_type' => $artifact['mime_type'],
          'metadata' => ['filename' => $artifact['filename']],
          'status' => WebBlocksUiArtifact::STATUS_TRACKED,
        ]);
      }

      return [
        'release' => $release->fresh('artifacts'),
        'manifest' => $manifest,
        'manifest_written' => $writeManifest,
      ];
    });
  }
}
