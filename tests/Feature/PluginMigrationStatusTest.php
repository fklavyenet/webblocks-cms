<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Tests\TestCase;

class PluginMigrationStatusTest extends TestCase
{
  #[Test]
  public function migration_action_becomes_current_and_reopens_when_a_new_migration_is_added(): void
  {
    Schema::create('migrations', function (Blueprint $table): void {
      $table->increments('id');
      $table->string('migration');
      $table->integer('batch');
    });

    $pluginRoot = storage_path('framework/testing/plugin-migration-status-'.uniqid());
    $migrationPath = $pluginRoot.'/database/migrations';
    File::ensureDirectoryExists($migrationPath);

    try {
      File::put($migrationPath.'/2026_09_19_120000_create_example_table.php', '<?php return null;');

      $plugin = PluginDefinition::make('example-plugin')
        ->label('Example Plugin')
        ->version('1.0.0')
        ->source('manual upload')
        ->installPath($pluginRoot)
        ->migrations(['database/migrations']);
      $runner = app(PluginMigrationRunner::class);

      $this->assertTrue($runner->hasPendingMigrations($plugin));

      DB::table('migrations')->insert([
        'migration' => '2026_09_19_120000_create_example_table',
        'batch' => 1,
      ]);

      $this->assertFalse($runner->hasPendingMigrations($plugin));

      File::put($migrationPath.'/2026_09_19_130000_add_example_column.php', '<?php return null;');

      $this->assertTrue($runner->hasPendingMigrations($plugin));
    } finally {
      File::deleteDirectory($pluginRoot);
    }
  }
}
