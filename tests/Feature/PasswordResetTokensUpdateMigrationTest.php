<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTokensUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  public function test_update_migration_creates_missing_password_reset_tokens_table(): void
  {
    Schema::dropIfExists('password_reset_tokens');

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_05_120000_ensure_password_reset_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasTable('password_reset_tokens'));

    DB::table('password_reset_tokens')->insert([
      'email' => 'editor@example.test',
      'token' => 'hashed-token',
      'created_at' => now(),
    ]);

    $this->assertDatabaseHas('password_reset_tokens', [
      'email' => 'editor@example.test',
      'token' => 'hashed-token',
    ]);
  }

  public function test_update_migration_keeps_existing_password_reset_tokens_table(): void
  {
    Schema::dropIfExists('password_reset_tokens');
    Schema::create('password_reset_tokens', function (Blueprint $table): void {
      $table->string('email')->primary();
      $table->string('token');
      $table->timestamp('created_at')->nullable();
      $table->string('source')->nullable();
    });

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_05_120000_ensure_password_reset_tokens_table.php');
    $migration->up();

    $this->assertTrue(Schema::hasColumn('password_reset_tokens', 'source'));
  }
}
