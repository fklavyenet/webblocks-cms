<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Support\Users\CmsUserRoleSchema;
use WebBlocks\Cms\Tests\TestCase;

final class CmsUserRoleCompatibilityMigrationTest extends TestCase
{
  protected bool $cacheFreshDatabaseSchema = false;

  protected function defineDatabaseMigrations(): void
  {
    Schema::create('users', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('email')->unique();
      $table->string('password');
      $table->string('role', 32)->default('teacher');
      $table->boolean('is_admin')->default(false);
      $table->boolean('is_active')->default(true);
      $table->timestamps();
    });
  }

  public static function profiles(): array
  {
    return [['fresh'], ['updates']];
  }

  #[DataProvider('profiles')]
  public function test_migration_allows_role_free_accounts_without_changing_existing_roles_or_defaults(string $profile): void
  {
    DB::table('users')->insert([
      ['name' => 'Fictional operator', 'email' => 'operator@example.test', 'password' => 'fixture', 'role' => 'super_admin', 'is_admin' => true],
      ['name' => 'Fictional teacher', 'email' => 'teacher@example.test', 'password' => 'fixture', 'role' => 'teacher', 'is_admin' => false],
    ]);
    $existing = DB::table('users')->orderBy('id')->get()->toArray();

    try {
      CmsRoleCompatibilityHostUser::create(['name' => 'Fictional reader', 'email' => 'reader@example.test', 'password' => 'fixture']);
      $this->fail('The legacy NOT NULL column must reproduce registration failure.');
    } catch (QueryException $exception) {
      $this->assertStringContainsString('NOT NULL', $exception->getMessage());
    }
    $this->assertSame(2, DB::table('users')->count());

    $migration = require dirname(__DIR__, 2).'/database/migrations/'.$profile.'/2026_10_26_163000_allow_role_free_host_accounts.php';
    $migration->up();
    $migration->up();
    $this->assertEquals($existing, DB::table('users')->orderBy('id')->get()->toArray());
    $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');
    $this->assertTrue($column['nullable']);
    $this->assertStringContainsString('teacher', (string) $column['default']);
    $this->assertTrue(collect(Schema::getIndexes('users'))->contains(fn ($index) => $index['unique'] && $index['columns'] === ['email']));

    $reader = CmsRoleCompatibilityHostUser::create(['name' => 'Fictional reader', 'email' => 'reader@example.test', 'password' => 'fixture']);
    $this->assertNull($reader->fresh()->role);
    $this->assertFalse($reader->isSuperAdmin());
    $this->assertFalse($reader->canAccessAdmin());
    $this->assertFalse($reader->fresh()->is_admin);
    $this->assertSame(3, DB::table('users')->count());
    DB::table('users')->insert(['name' => 'Host default', 'email' => 'default@example.test', 'password' => 'fixture']);
    $this->assertSame('teacher', DB::table('users')->where('email', 'default@example.test')->value('role'));
    $migration->down();
    $this->assertNull($reader->fresh()->role);
  }

  public function test_fresh_coexistence_install_creates_no_cms_grants_for_a_host_only_account(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    $reader = CmsRoleCompatibilityHostUser::create(['name' => 'Fictional account', 'email' => 'host-only@example.test', 'password' => 'fixture']);
    $this->assertNull($reader->fresh()->role);
    $this->assertFalse($reader->canAccessAdmin());
    $this->assertDatabaseCount('wbcms_site_user', 0);
    $this->assertDatabaseCount('wbcms_acl_system_administrators', 0);
    $this->assertDatabaseCount('users', 1);
  }

  public function test_missing_role_or_cms_identity_columns_are_left_untouched(): void
  {
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    (new CmsUserRoleSchema)->ensureNullableRole();
    $this->assertFalse(collect(Schema::getColumns('users'))->firstWhere('name', 'role')['nullable']);
    Schema::drop('users');
    (new CmsUserRoleSchema)->ensureNullableRole();
    $this->assertFalse(Schema::hasTable('users'));
  }
}

final class CmsRoleCompatibilityHostUser extends Authenticatable
{
  use HasCmsAdminAccess;

  protected $table = 'users';

  protected $fillable = ['name', 'email', 'password'];
}
