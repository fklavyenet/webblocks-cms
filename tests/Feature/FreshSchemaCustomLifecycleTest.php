<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Tests\TestCase;

class FreshSchemaCustomLifecycleTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $name = $this->name();
    $this->app['events']->listen(MigrationsEnded::class, static function () use ($name): void {
      DB::statement('CREATE TABLE migration_hook_fixture (test_name TEXT)');
      DB::table('migration_hook_fixture')->insert(['test_name' => $name]);
    });
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public function test_first_custom_migration_hook_runs_on_its_own_database(): void
  {
    $this->assertSame($this->name(), DB::table('migration_hook_fixture')->value('test_name'));
    Schema::drop('migration_hook_fixture');
  }

  public function test_changed_custom_migration_hook_does_not_reuse_a_prior_test_fixture(): void
  {
    $this->assertSame($this->name(), DB::table('migration_hook_fixture')->value('test_name'));
    Schema::drop('migration_hook_fixture');
  }
}
