<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\File;
use SplFileInfo;

/**
 * Runs database migrations after an apply operation. Instead of detecting the
 * product's Composer layout, the
 * strategy is config-driven (§4.3, §5.4).
 *
 *  - 'source'  : full `migrate --force` at the host root (standalone apps).
 *  - 'package' : `migrate --path=<package_migrations_path> --realpath --force`,
 *                i.e. only the configured product package's own migrations.
 *  - 'auto'    : 'package' when `migrations.package_migrations_path` is configured,
 *                otherwise 'source'.
 */
class UpdateMigrationRunner
{
  public function __construct(private readonly UpdateCommandRunner $commandRunner)
  {
  }

  public function run(string $commandDir, array &$output): void
  {
    $strategy = $this->resolveStrategy();

    if ($strategy === 'source') {
      $output[] = 'Migration strategy: source (full migrate at host root).';
      $this->commandRunner->run(
        $this->commandRunner->artisanCommand(['migrate', '--force']),
        $commandDir,
        $output,
      );

      return;
    }

    $migrationPath = $this->packageMigrationsPath($commandDir);

    if ($migrationPath === null || ! $this->hasMigrationFiles($migrationPath)) {
      $output[] = 'Migration strategy: package. No package update migrations found; host migrations were skipped.';

      return;
    }

    $output[] = 'Migration strategy: package. Running package update migrations at '.$migrationPath.'.';
    $this->commandRunner->run(
      $this->commandRunner->artisanCommand([
        'migrate',
        '--path='.$migrationPath,
        '--realpath',
        '--force',
      ]),
      $commandDir,
      $output,
    );
  }

  private function resolveStrategy(): string
  {
    $configured = (string) config('publisher-client.migrations.strategy', 'auto');

    if (in_array($configured, ['package', 'source'], true)) {
      return $configured;
    }

    $relative = trim((string) config('publisher-client.migrations.package_migrations_path', ''));

    return $relative !== '' ? 'package' : 'source';
  }

  private function packageMigrationsPath(string $commandDir): ?string
  {
    $relative = trim((string) config('publisher-client.migrations.package_migrations_path', ''), '/');

    if ($relative === '') {
      return null;
    }

    return rtrim($commandDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
  }

  private function hasMigrationFiles(string $migrationPath): bool
  {
    if (! File::isDirectory($migrationPath)) {
      return false;
    }

    return collect(File::files($migrationPath))
      ->contains(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');
  }
}
