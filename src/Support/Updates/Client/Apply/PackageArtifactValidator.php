<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Boundary validation for a staged PACKAGE artifact (package apply strategy only).
 *
 * A package release must contain package code and nothing else. This guard,
 * Shared package-boundary validation (§7.1 hardening) rejects a staged tree
 * that:
 *   - carries files outside the configured package allowlist,
 *   - ships frontend build-chain files (node_modules, vite/tailwind/postcss
 *     config, lockfiles, compiled public/build|hot),
 *   - hides host-runtime references in scanned source (App\Http\Controllers,
 *     App\Models\, host layout/route references, npm/vite tooling),
 *   - uses hidden or unsafe path segments, or
 *   - contains symlinks.
 *
 * Every rule is config-driven so each package adopter tunes its own contract;
 * an empty rule list disables that check. This runs ONLY under the package
 * strategy — a full-root artifact legitimately contains app/Http, app/Models,
 * etc., so full-root apply never invokes it. The archive-level guards (zip-slip,
 * size caps, symlink, duplicate paths) stay in UpdatePackageExtractor; this
 * validator inspects the already-staged tree.
 */
final class PackageArtifactValidator
{
  /**
   * @param  list<string>  $allowedRoots
   * @param  list<string>  $forbiddenPaths
   * @param  list<string>  $forbiddenContentPatterns
   * @param  list<string>  $scanExtensions
   * @param  list<string>  $requiredPaths
   */
  public function __construct(
    private readonly array $allowedRoots = [],
    private readonly array $forbiddenPaths = [],
    private readonly array $forbiddenContentPatterns = [],
    private readonly array $scanExtensions = ['php', 'blade.php', 'json', 'md', 'css', 'js'],
    private readonly bool $rejectHiddenSegments = true,
    private readonly array $requiredPaths = [],
  ) {}

  public static function fromConfig(): self
  {
    $config = (array) config('publisher-client.apply.package_validation', []);

    return new self(
      allowedRoots: self::stringList($config['allowed_roots'] ?? []),
      forbiddenPaths: self::stringList($config['forbidden_paths'] ?? []),
      forbiddenContentPatterns: self::stringList($config['forbidden_content_patterns'] ?? []),
      scanExtensions: self::stringList($config['scan_extensions'] ?? ['php', 'blade.php', 'json', 'md', 'css', 'js']),
      rejectHiddenSegments: (bool) ($config['reject_hidden_segments'] ?? true),
      requiredPaths: self::stringList($config['required_paths'] ?? []),
    );
  }

  public function validate(string $stagedRoot): void
  {
    $root = rtrim(str_replace('\\', '/', $stagedRoot), '/');

    foreach ($this->requiredPaths as $required) {
      $required = trim(str_replace('\\', '/', $required), '/');

      if ($required !== '' && ! File::exists($root.'/'.$required)) {
        throw new UpdateException(
          'The downloaded update package does not match the expected package structure.',
          'Required package path missing from staged artifact: '.$required.'.',
        );
      }
    }

    foreach (File::allFiles($stagedRoot, true) as $file) {
      $relativePath = $this->relativePath($stagedRoot, $file->getPathname());

      $this->assertAllowedPath($relativePath);

      if ($file->isLink()) {
        throw new UpdateException(
          'The downloaded update package contains a symbolic link and was rejected.',
          'Symlink in staged package artifact: '.$relativePath.'.',
        );
      }

      if ($this->shouldScanFile($relativePath)) {
        $this->assertNoForbiddenContent($relativePath, (string) File::get($file->getPathname()));
      }
    }
  }

  public function assertAllowedPath(string $relativePath): void
  {
    $normalized = trim(str_replace('\\', '/', $relativePath), '/');

    if ($normalized === '') {
      throw new UpdateException(
        'The downloaded update package contains an invalid path.',
        'Empty relative path in staged artifact.',
      );
    }

    if ($this->rejectHiddenSegments) {
      foreach (explode('/', $normalized) as $segment) {
        if ($segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
          throw new UpdateException(
            'The downloaded update package contains hidden or unsafe paths.',
            'Hidden/unsafe path segment in staged artifact: '.$normalized.'.',
          );
        }
      }
    }

    foreach ($this->forbiddenPaths as $forbidden) {
      $forbidden = trim(str_replace('\\', '/', $forbidden), '/');

      if ($forbidden === '') {
        continue;
      }

      if ($normalized === $forbidden
        || str_starts_with($normalized, $forbidden.'/')
        || str_starts_with($normalized, $forbidden.'.')) {
        throw new UpdateException(
          'The downloaded update package contains frontend build-chain files and was rejected.',
          'Forbidden path in staged artifact: '.$normalized.'.',
        );
      }
    }

    if ($this->allowedRoots === []) {
      return;
    }

    foreach ($this->allowedRoots as $root) {
      $root = trim(str_replace('\\', '/', $root), '/');

      if ($root !== '' && ($normalized === $root || str_starts_with($normalized, $root.'/'))) {
        return;
      }
    }

    throw new UpdateException(
      'The downloaded update package contains files outside the package allowlist.',
      'Path outside package allowlist in staged artifact: '.$normalized.'.',
    );
  }

  private function shouldScanFile(string $relativePath): bool
  {
    if ($this->forbiddenContentPatterns === []) {
      return false;
    }

    $lower = strtolower($relativePath);

    foreach ($this->scanExtensions as $extension) {
      $extension = strtolower(ltrim($extension, '.'));

      if ($extension !== '' && str_ends_with($lower, '.'.$extension)) {
        return true;
      }
    }

    return false;
  }

  private function assertNoForbiddenContent(string $relativePath, string $contents): void
  {
    foreach ($this->forbiddenContentPatterns as $pattern) {
      if ($pattern !== '' && str_contains($contents, $pattern)) {
        throw new UpdateException(
          'The downloaded update package failed the package runtime boundary scan.',
          'Forbidden host-runtime reference in staged artifact file: '.$relativePath.'.',
        );
      }
    }
  }

  private function relativePath(string $root, string $path): string
  {
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, $normalizedRoot)) {
      return substr($normalizedPath, strlen($normalizedRoot));
    }

    return $normalizedPath;
  }

  /**
   * @return list<string>
   */
  private static function stringList(mixed $value): array
  {
    if (! is_array($value)) {
      return [];
    }

    return array_values(array_filter(
      array_map(static fn ($item): string => is_string($item) ? $item : '', $value),
      static fn (string $item): bool => $item !== '',
    ));
  }
}
