<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PluginMigrationRunner
{
  /**
   * @return array{ran: bool, paths: array<int, string>, message: string}
   */
  public function run(PluginDefinition $plugin, bool $repairRecordedMigrations = false): array
  {
    $installPath = $plugin->installPathValue();

    if ($installPath === null || ! is_dir($installPath)) {
      throw new RuntimeException('Plugin install path is not available.');
    }

    if ($plugin->migrationPaths() === []) {
      return [
        'ran' => false,
        'paths' => [],
        'message' => 'This plugin does not declare migrations.',
      ];
    }

    $root = realpath($installPath);

    if ($root === false) {
      throw new RuntimeException('Plugin install path is not available.');
    }

    $paths = [];

    foreach ($plugin->migrationPaths() as $path) {
      $resolved = realpath($root.DIRECTORY_SEPARATOR.$path);

      if ($resolved === false || ! is_dir($resolved)) {
        throw new RuntimeException('Declared plugin migration path is not available.');
      }

      $resolved = rtrim($resolved, DIRECTORY_SEPARATOR);

      if ($resolved !== $root && ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Declared plugin migration path is outside the plugin install path.');
      }

      $paths[] = $resolved;
    }

    try {
      if ($repairRecordedMigrations) {
        $this->forgetRecordedMigrations($paths);
      }

      foreach ($paths as $path) {
        Artisan::call('migrate', [
          '--path' => $path,
          '--realpath' => true,
          '--force' => true,
        ]);
      }
    } catch (Throwable $exception) {
      throw new RuntimeException('Plugin migrations failed: '.$this->safeMessage($exception->getMessage(), $root), previous: $exception);
    }

    return [
      'ran' => true,
      'paths' => $paths,
      'message' => 'Plugin migrations completed.',
    ];
  }

  private function safeMessage(string $message, string $root): string
  {
    $message = str_replace($root, '[plugin]', $message);

    return trim($message) !== '' ? $message : 'Migration command did not complete.';
  }

  /**
   * @param  array<int, string>  $paths
   */
  private function forgetRecordedMigrations(array $paths): void
  {
    if (! DB::getSchemaBuilder()->hasTable('migrations')) {
      return;
    }

    $migrations = [];

    foreach ($paths as $path) {
      foreach (glob($path.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
        $migrations[] = pathinfo($file, PATHINFO_FILENAME);
      }
    }

    if ($migrations !== []) {
      DB::table('migrations')->whereIn('migration', array_values(array_unique($migrations)))->delete();
    }
  }
}
