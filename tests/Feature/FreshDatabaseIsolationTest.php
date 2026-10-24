<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Tests\TestCase;

class FreshDatabaseIsolationTest extends TestCase
{
  private static array $connections = [];

  private static array $migrationRuns = [];

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('app.locale', $this->providedData()[0]);
    $app['events']->listen(MigrationsEnded::class, static function (MigrationsEnded $event): void {
      if ($event->method === 'up') {
        $locale = config('app.locale');
        self::$migrationRuns[$locale] = (self::$migrationRuns[$locale] ?? 0) + 1;
      }
    });
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public static function mutations(): array
  {
    return [
      'commit English data' => ['en', 'commit'],
      'change Turkish schema' => ['tr', 'schema'],
      'change English schema' => ['en', 'schema'],
      'commit Turkish data' => ['tr', 'commit'],
    ];
  }

  #[DataProvider('mutations')]
  public function test_each_case_has_a_pristine_database_and_real_transaction_semantics(string $locale, string $mutation): void
  {
    $connection = DB::connection();
    $database = $connection->getPdo();
    $this->assertInstanceOf(PDO::class, $database);
    foreach (self::$connections as $previous) {
      $this->assertNotSame($previous, $database);
    }
    self::$connections[] = $database;
    $this->assertSame(0, $connection->transactionLevel());
    $this->assertFalse($database->inTransaction());
    $this->assertSame(0, DB::table('wbcms_sites')->count());
    $this->assertFalse(Schema::hasColumn('wbcms_sites', 'leaked'));
    $this->assertSame(1, (int) $database->query('PRAGMA foreign_keys')->fetchColumn());
    $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    if (getenv('WEBBLOCKS_CMS_TEST_FRESH_SCHEMA_CACHE') !== '0') {
      $this->assertSame(1, self::$migrationRuns[$locale]);
    }
    DB::beginTransaction();
    DB::table('wbcms_sites')->insert(['name' => 'Rolled back', 'handle' => 'rolled-back']);
    DB::rollBack();
    $this->assertSame(0, DB::table('wbcms_sites')->count());
    DB::beginTransaction();
    DB::table('wbcms_sites')->insert(['name' => 'Committed', 'handle' => 'committed']);
    DB::commit();
    $this->assertSame(1, DB::table('wbcms_sites')->value('id'));
    if ($mutation === 'schema') {
      Schema::table('wbcms_sites', fn ($table) => $table->string('leaked')->nullable());
    }
  }

  public static function tearDownAfterClass(): void
  {
    self::$connections = [];
    self::$migrationRuns = [];
    parent::tearDownAfterClass();
  }
}
