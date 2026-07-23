<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;

class UpdateMigrationRunner
{
  private const PACKAGE_UPDATE_MIGRATIONS_PATH = 'vendor/fklavyenet/webblocks-cms/database/migrations/updates';

  public function __construct(
    private readonly UpdateCommandRunner $commandRunner,
  ) {}

  public function run(string $targetPath, array &$output): void
  {
    $migrationPath = $this->packageUpdateMigrationsPath($targetPath);

    if (! $this->hasMigrationFiles($migrationPath)) {
      $output[] = 'No package update migrations found; host application migrations were skipped.';

      return;
    }

    $output[] = 'Running package update migrations at '.$migrationPath.'. Host application migrations were skipped.';
    $this->commandRunner->run(
      $this->commandRunner->artisanCommand([
        'migrate',
        '--path='.$migrationPath,
        '--realpath',
        '--force',
      ]),
      $targetPath,
      $output,
    );
  }

  public function packageUpdateMigrationsPath(string $targetPath): string
  {
    $relativePath = (string) config(
      'webblocks-updates.installer.package_update_migrations_path',
      self::PACKAGE_UPDATE_MIGRATIONS_PATH,
    );

    return rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($relativePath, '/'));
  }

  public function hasMigrationFiles(string $migrationPath): bool
  {
    if (! File::isDirectory($migrationPath)) {
      return false;
    }

    return collect(File::files($migrationPath))
      ->contains(fn (\SplFileInfo $file): bool => $file->getExtension() === 'php');
  }
}
