<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLocaleUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function update_migration_adds_missing_admin_locale_column_to_users_table(): void
  {
    Schema::table('users', function ($table): void {
      $table->dropColumn('admin_locale');
    });

    $this->assertFalse(Schema::hasColumn('users', 'admin_locale'));

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_05_120000_ensure_users_admin_locale.php');
    $migration->up();

    $this->assertTrue(Schema::hasColumn('users', 'admin_locale'));

    DB::table('users')->insert([
      'name' => 'Locale Admin',
      'email' => 'locale-admin@example.test',
      'password' => bcrypt('password'),
      'role' => 'super_admin',
      'is_admin' => true,
      'is_active' => true,
      'admin_locale' => 'de',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $this->assertSame('de', DB::table('users')->where('email', 'locale-admin@example.test')->value('admin_locale'));
  }
}
