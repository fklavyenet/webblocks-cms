<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Package-scoped apply (§7.1): replaces ONLY the product package directory
 * (e.g. vendor/fklavyenet/<product>) with the staged release. User data, .env
 * and storage live outside the package dir, so they are untouched by
 * construction.
 *
 * The atomic staging → backup → rename → rollback mechanic is ported from the
 * The package strategy replaces only the configured runtime target: the target
 * dir and the expected package identity come from config, not hard-coded
 * product literals. Product-specific post-apply work (composer metadata
 * normalization, asset sync, catalog repair) stays out of the strategy — it is
 * driven by the orchestrator + configured post_apply commands.
 */
class PackageApplyStrategy extends AbstractApplyStrategy
{
  public function name(): string
  {
    return 'package';
  }

  public function targetRoot(): string
  {
    $configured = trim((string) config('publisher-client.apply.target_path', ''));

    if ($configured !== '') {
      return rtrim($configured, '/\\');
    }

    $resolved = $this->resolveFromServiceProvider() ?? $this->resolveFromPackageName();

    if ($resolved === null) {
      throw new UpdateException(
        'The update target package directory is not configured.',
        'Set publisher-client.apply.target_path, or publisher-client.package.service_provider / package.name so the package strategy can resolve the runtime directory.',
      );
    }

    return rtrim($resolved, '/\\');
  }

  public function apply(string $stagedRoot): array
  {
    $target = $this->targetRoot();

    if (! File::isDirectory($stagedRoot)) {
      throw new UpdateException('The downloaded update package could not be applied.', 'Staged package root is missing: '.$stagedRoot);
    }

    $this->assertSafeStagedContents($stagedRoot);
    $this->assertStagedIdentity($stagedRoot);
    PackageArtifactValidator::fromConfig()->validate($stagedRoot);

    $enforceActive = (bool) config('publisher-client.apply.enforce_active_runtime_target', true);

    if ($enforceActive && ! File::isDirectory($target)) {
      throw new UpdateException(
        'The active package directory to update could not be found.',
        'enforce_active_runtime_target is on but the target package directory does not exist: '.$target,
      );
    }

    $output = [];
    $this->replaceDirectory($target, $stagedRoot, $output);

    return [
      'strategy' => $this->name(),
      'target' => $target,
      'output' => $output,
    ];
  }

  /**
   * Atomic directory replacement with rollback.
   */
  private function replaceDirectory(string $target, string $stagedRoot, array &$output): void
  {
    $staging = $target.'.pc-new';
    $backup = $target.'.pc-old';

    File::deleteDirectory($staging);
    File::deleteDirectory($backup);
    File::ensureDirectoryExists(dirname($target));

    if (! File::copyDirectory($stagedRoot, $staging)) {
      throw new UpdateException('The update could not apply the downloaded package.', 'Failed to stage package contents into '.$staging.'.');
    }

    if (File::exists($target)) {
      if (! File::isDirectory($target)) {
        File::deleteDirectory($staging);

        throw new UpdateException('The update could not apply the downloaded package.', 'Target path is not a directory: '.$target);
      }

      if (! @rename($target, $backup)) {
        File::deleteDirectory($staging);

        throw new UpdateException(
          'The update could not write application files. Check file permissions and try again.',
          'Failed to move the existing package directory out of the way: '.$target,
        );
      }
    }

    if (! @rename($staging, $target)) {
      File::deleteDirectory($staging);

      if (File::isDirectory($backup)) {
        @rename($backup, $target);
      }

      throw new UpdateException('The update could not apply the downloaded package.', 'Failed to replace the package directory at '.$target.'.');
    }

    File::deleteDirectory($backup);

    $output[] = 'Replaced '.$target.' with the staged package artifact.';
  }

  private function assertStagedIdentity(string $stagedRoot): void
  {
    $expectedName = trim((string) config('publisher-client.package.name', ''));

    if ($expectedName === '') {
      return; // Identity not configured: skip the check.
    }

    $stagedName = $this->composerName($stagedRoot);

    if ($stagedName !== $expectedName) {
      throw new UpdateException(
        'The downloaded update package does not match this product.',
        'Package identity mismatch: expected composer name "'.$expectedName.'", staged package is "'.($stagedName ?? 'unknown').'".',
      );
    }
  }

  private function resolveFromServiceProvider(): ?string
  {
    $fqcn = trim((string) config('publisher-client.package.service_provider', ''));

    if ($fqcn === '' || ! class_exists($fqcn)) {
      return null;
    }

    $file = (new ReflectionClass($fqcn))->getFileName();

    if (! is_string($file) || $file === '') {
      return null;
    }

    $dir = dirname($file);

    while ($dir !== dirname($dir)) {
      if (File::isFile($dir.DIRECTORY_SEPARATOR.'composer.json')) {
        return $dir;
      }

      $dir = dirname($dir);
    }

    return null;
  }

  private function resolveFromPackageName(): ?string
  {
    $name = trim((string) config('publisher-client.package.name', ''));

    if ($name === '') {
      return null;
    }

    $vendorPath = base_path('vendor'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name));

    if (File::isDirectory($vendorPath)) {
      return $vendorPath;
    }

    return base_path('packages'.DIRECTORY_SEPARATOR.basename($name));
  }
}
