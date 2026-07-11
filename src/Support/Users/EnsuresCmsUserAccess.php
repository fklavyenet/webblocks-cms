<?php

namespace WebBlocks\Cms\Support\Users;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class EnsuresCmsUserAccess
{
  private const TRAIT_IMPORT = 'use WebBlocks\\Cms\\Auth\\Concerns\\HasWebBlocksCmsAccess;';

  private const TRAIT_SHORT_NAME = 'HasWebBlocksCmsAccess';

  private const LEGACY_TRAIT_SHORT_NAME = 'HasCmsAdminAccess';

  private ?string $lastBackupPath = null;

  public function __construct(
    private readonly Filesystem $files,
  ) {}

  public function ensure(?string $path = null): bool
  {
    $path = $path ?: $this->resolveUserModelPath();

    $this->lastBackupPath = null;

    if (! $this->files->exists($path)) {
      throw new \RuntimeException('App\\Models\\User was not found at: '.$path);
    }

    $contents = $this->files->get($path);

    if ($this->alreadyPatched($contents)) {
      return false;
    }

    if (! preg_match('/class\s+User\s+extends\s+Authenticatable\b/s', $contents)) {
      throw new \RuntimeException('Unable to patch App\\Models\\User: expected a standard User class declaration.');
    }

    $updated = $this->injectTraitImport($contents);

    if ($updated === null) {
      throw new \RuntimeException('Unable to patch App\\Models\\User: expected the App\\Models namespace declaration.');
    }

    $updated = $this->injectTraitUsage($updated);

    if ($updated === null) {
      throw new \RuntimeException('Unable to patch App\\Models\\User safely: expected a standard User class declaration.');
    }

    if ($updated === $contents) {
      return false;
    }

    $this->lastBackupPath = $this->createBackup($path, $contents);
    $this->files->put($path, $updated);

    return true;
  }

  public function lastBackupPath(): ?string
  {
    return $this->lastBackupPath;
  }

  private function resolveUserModelPath(): string
  {
    $configured = config('webblocks-cms.auth.user_model_path');

    if (is_string($configured) && trim($configured) !== '') {
      return $configured;
    }

    return app()->path('Models/User.php');
  }

  private function alreadyPatched(string $contents): bool
  {
    return str_contains($contents, self::TRAIT_IMPORT)
      || str_contains($contents, 'use '.self::TRAIT_SHORT_NAME.';')
      || str_contains($contents, self::LEGACY_TRAIT_SHORT_NAME);
  }

  private function injectTraitImport(string $contents): ?string
  {
    if (str_contains($contents, self::TRAIT_IMPORT)) {
      return $contents;
    }

    return preg_replace(
      '/^namespace\s+App\\\\Models;\R/m',
      "namespace App\\Models;\n\n".self::TRAIT_IMPORT."\n",
      $contents,
      1,
    );
  }

  private function injectTraitUsage(string $contents): ?string
  {
    if (str_contains($contents, 'use '.self::TRAIT_SHORT_NAME.';')) {
      return $contents;
    }

    $classWithExistingTraits = preg_replace(
      '/(class\s+User\s+extends\s+Authenticatable[^\{]*\{\s*\R(?:\s*\/\*\*.*?\*\/\s*\R)*\s*use\s+)([^;]+);/s',
      '$1$2, '.self::TRAIT_SHORT_NAME.';',
      $contents,
      1,
      $count,
    );

    if ($classWithExistingTraits !== null && $count === 1) {
      return $classWithExistingTraits;
    }

    return preg_replace(
      '/class\s+User\s+extends\s+Authenticatable[^\{]*\{\s*\R/s',
      "class User extends Authenticatable\n{\n    use ".self::TRAIT_SHORT_NAME.";\n\n",
      $contents,
      1,
    );
  }

  private function createBackup(string $path, string $contents): string
  {
    $directory = dirname($path);
    $filename = basename($path);
    $timestamp = now()->format('YmdHis');
    $backupPath = $directory.DIRECTORY_SEPARATOR.$filename.'.webblocks-cms.'.Str::lower(Str::random(6)).'.'.$timestamp.'.bak';

    $this->files->put($backupPath, $contents);

    return $backupPath;
  }
}
