<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiArtifact;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiPublishRun;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;

class WebBlocksUiReleasePublisher
{
  public function __construct(
    private readonly WebBlocksUiManagerPaths $paths,
  ) {}

  public function dryRun(string $version): WebBlocksUiPublishRun
  {
    return $this->run($version, dryRun: true);
  }

  public function publish(string $version): WebBlocksUiPublishRun
  {
    return $this->run($version, dryRun: false);
  }

  private function run(string $version, bool $dryRun): WebBlocksUiPublishRun
  {
    $release = WebBlocksUiRelease::query()
      ->where('version', $version)
      ->with('artifacts')
      ->first();

    if (! $release instanceof WebBlocksUiRelease) {
      throw new InvalidArgumentException("WebBlocks UI release [{$version}] is not tracked.");
    }

    $startedAt = now();
    $mode = $dryRun ? WebBlocksUiPublishRun::MODE_DRY_RUN : WebBlocksUiPublishRun::MODE_PUBLISH;
    $targetRoot = $this->paths->cdnRootPublicPath();
    $targetReleasePath = $this->paths->releasePublicPath($release->version);

    try {
      $operations = $this->validate($release, $targetRoot, $targetReleasePath);

      if (! $dryRun) {
        $this->write($operations, $targetReleasePath, $release);
      }

      return $this->recordRun(
        release: $release,
        mode: $mode,
        status: WebBlocksUiPublishRun::STATUS_SUCCEEDED,
        targetRoot: $targetRoot,
        targetReleasePath: $targetReleasePath,
        operations: $operations,
        message: $dryRun ? 'Dry-run validation passed.' : 'Release published.',
        startedAt: $startedAt
      );
    } catch (Throwable $exception) {
      $status = $exception instanceof InvalidArgumentException
        ? WebBlocksUiPublishRun::STATUS_BLOCKED
        : WebBlocksUiPublishRun::STATUS_FAILED;

      return $this->recordRun(
        release: $release,
        mode: $mode,
        status: $status,
        targetRoot: $targetRoot,
        targetReleasePath: $targetReleasePath,
        operations: [],
        message: $exception->getMessage(),
        startedAt: $startedAt
      );
    }
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function validate(WebBlocksUiRelease $release, string $targetRoot, string $targetReleasePath): array
  {
    if ($release->status === WebBlocksUiRelease::STATUS_DRAFT) {
      throw new InvalidArgumentException('Release must be prepared before publishing.');
    }

    if (! preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $release->version)) {
      throw new InvalidArgumentException("Release version [{$release->version}] is not semver-like.");
    }

    $expectedCdnPath = $this->paths->releasePublicDirectory($release->version);

    if ($this->normalizeRelativePath((string) $release->cdn_base_path) !== $expectedCdnPath) {
      throw new InvalidArgumentException("Release CDN path must be [{$expectedCdnPath}] for version [{$release->version}].");
    }

    if ($release->artifacts->isEmpty()) {
      throw new InvalidArgumentException('Release has no prepared artifacts.');
    }

    $manifest = is_array($release->manifest) ? $release->manifest : [];

    if (($manifest['plugin'] ?? null) !== 'webblocks-ui-manager' || ($manifest['version'] ?? null) !== $release->version) {
      throw new InvalidArgumentException('Release manifest does not match the plugin and release version.');
    }

    $manifestArtifacts = collect($manifest['artifacts'] ?? [])
      ->keyBy(fn (array $artifact): string => (string) ($artifact['handle'] ?? ''));

    $this->assertExpectedDistFiles($release);

    $operations = [];

    foreach ($release->artifacts as $artifact) {
      $manifestArtifact = $manifestArtifacts->get($artifact->handle);

      if (! is_array($manifestArtifact)) {
        throw new InvalidArgumentException("Artifact [{$artifact->handle}] is missing from the release manifest.");
      }

      $sourcePath = $this->safeSourcePath($artifact);
      $targetPath = $this->safeTargetPath($release, $artifact, $targetRoot, $targetReleasePath);
      $sourceChecksum = hash_file('sha256', $sourcePath);

      if ($sourceChecksum !== $artifact->checksum_sha256 || $sourceChecksum !== ($manifestArtifact['checksum_sha256'] ?? null)) {
        throw new InvalidArgumentException("Artifact [{$artifact->handle}] checksum does not match prepared metadata.");
      }

      $action = 'write';

      if (File::exists($targetPath)) {
        $existingChecksum = hash_file('sha256', $targetPath);

        if ($existingChecksum !== $sourceChecksum) {
          throw new InvalidArgumentException("Published artifact [{$artifact->handle}] already exists with a different checksum.");
        }

        $action = 'skip';
      }

      $operations[] = [
        'artifact' => $artifact->handle,
        'action' => $action,
        'source_path' => $sourcePath,
        'target_path' => $targetPath,
        'checksum_sha256' => $sourceChecksum,
        'size_bytes' => filesize($sourcePath),
      ];
    }

    $manifestPath = $this->paths->manifestPublicPath($release->version);

    if (File::exists($manifestPath) && trim((string) File::get($manifestPath)) !== trim($this->manifestJson($release))) {
      throw new InvalidArgumentException('Published manifest already exists with different content.');
    }

    $operations[] = [
      'artifact' => 'manifest.json',
      'action' => File::exists($manifestPath) ? 'skip' : 'write',
      'source_path' => null,
      'target_path' => $manifestPath,
      'checksum_sha256' => hash('sha256', $this->manifestJson($release)),
      'size_bytes' => strlen($this->manifestJson($release)),
    ];

    return $operations;
  }

  /**
   * @param  array<int, array<string, mixed>>  $operations
   */
  private function write(array $operations, string $targetReleasePath, WebBlocksUiRelease $release): void
  {
    DB::transaction(function () use ($operations, $targetReleasePath, $release): void {
      File::ensureDirectoryExists($targetReleasePath);

      foreach ($operations as $operation) {
        if ($operation['artifact'] === 'manifest.json') {
          File::put((string) $operation['target_path'], $this->manifestJson($release));

          continue;
        }

        if ($operation['action'] === 'skip') {
          continue;
        }

        if (! File::copy((string) $operation['source_path'], (string) $operation['target_path'])) {
          throw new RuntimeException("Artifact [{$operation['artifact']}] could not be copied.");
        }
      }

      $release->forceFill([
        'status' => WebBlocksUiRelease::STATUS_PUBLISHED,
        'manifest_path' => $this->paths->manifestRelativePath($release->version),
        'published_at' => now(),
      ])->save();
    });
  }

  /**
   * @param  array<int, array<string, mixed>>  $operations
   */
  private function recordRun(
    WebBlocksUiRelease $release,
    string $mode,
    string $status,
    string $targetRoot,
    string $targetReleasePath,
    array $operations,
    string $message,
    mixed $startedAt,
  ): WebBlocksUiPublishRun {
    if ($status === WebBlocksUiPublishRun::STATUS_BLOCKED) {
      $release->forceFill(['status' => WebBlocksUiRelease::STATUS_BLOCKED])->save();
    }

    if ($status === WebBlocksUiPublishRun::STATUS_FAILED) {
      $release->forceFill(['status' => WebBlocksUiRelease::STATUS_PUBLISH_FAILED])->save();
    }

    if ($status === WebBlocksUiPublishRun::STATUS_SUCCEEDED && $mode === WebBlocksUiPublishRun::MODE_DRY_RUN && in_array($release->status, [WebBlocksUiRelease::STATUS_BLOCKED, WebBlocksUiRelease::STATUS_PUBLISH_FAILED], true)) {
      $release->forceFill(['status' => WebBlocksUiRelease::STATUS_PREPARED])->save();
    }

    return WebBlocksUiPublishRun::query()->create([
      'release_id' => $release->id,
      'mode' => $mode,
      'status' => $status,
      'target_root' => $targetRoot,
      'target_release_path' => $targetReleasePath,
      'operations' => $operations,
      'message' => $message,
      'started_at' => $startedAt,
      'finished_at' => now(),
    ]);
  }

  private function safeSourcePath(WebBlocksUiArtifact $artifact): string
  {
    $sourcePath = (string) $artifact->source_path;
    $realPath = realpath($sourcePath);

    if ($realPath === false || ! is_file($realPath)) {
      throw new InvalidArgumentException("Artifact [{$artifact->handle}] source file is missing.");
    }

    if (is_link($sourcePath)) {
      throw new InvalidArgumentException("Artifact [{$artifact->handle}] source path cannot be a symlink.");
    }

    if (! $this->pathIsInside($realPath, base_path())) {
      throw new InvalidArgumentException("Artifact [{$artifact->handle}] source path must stay inside the project root.");
    }

    return $realPath;
  }

  private function safeTargetPath(WebBlocksUiRelease $release, WebBlocksUiArtifact $artifact, string $targetRoot, string $targetReleasePath): string
  {
    $filename = basename((string) ($artifact->metadata['filename'] ?? $artifact->handle));
    $expectedTarget = $this->normalizeRelativePath($this->paths->releasePublicDirectory($release->version).'/'.$filename);

    if ($this->normalizeRelativePath($artifact->target_path) !== $expectedTarget) {
      throw new InvalidArgumentException("Artifact [{$artifact->handle}] target path must be [{$expectedTarget}].");
    }

    $targetPath = $targetReleasePath.DIRECTORY_SEPARATOR.$filename;

    if (! $this->pathIsInside($targetPath, $targetRoot)) {
      throw new InvalidArgumentException("Artifact [{$artifact->handle}] target path escapes the configured CDN root.");
    }

    return $targetPath;
  }

  private function assertExpectedDistFiles(WebBlocksUiRelease $release): void
  {
    $actual = $release->artifacts
      ->map(fn (WebBlocksUiArtifact $artifact): string => basename((string) ($artifact->metadata['filename'] ?? $artifact->handle)))
      ->all();

    $missing = array_values(array_diff($this->paths->expectedDistFiles(), $actual));

    if ($missing !== []) {
      throw new InvalidArgumentException('Release is missing expected WebBlocks UI dist file(s): '.implode(', ', $missing).'.');
    }
  }

  private function normalizeRelativePath(string $path): string
  {
    $original = str_replace('\\', '/', $path);
    $path = trim($original, '/');

    if ($path === '' || str_starts_with($original, '/') || str_contains($path, '../') || str_contains($path, '/..')) {
      throw new InvalidArgumentException("Path [{$path}] is not a safe relative path.");
    }

    return $path;
  }

  private function pathIsInside(string $path, string $root): bool
  {
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';

    return str_starts_with($normalizedPath, $normalizedRoot);
  }

  private function manifestJson(WebBlocksUiRelease $release): string
  {
    return json_encode($release->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
  }
}
