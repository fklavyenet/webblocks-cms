<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Database\Seeder;
use RuntimeException;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoResetPreflight;
use WebBlocks\Cms\Tests\TestCase;

class PublicDemoResetPreflightTest extends TestCase
{
  private string $markerPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->markerPath = sys_get_temp_dir().'/webblocks-public-demo-'.bin2hex(random_bytes(6)).'.marker';
    file_put_contents($this->markerPath, 'demo_database');

    config()->set([
      'app.url' => 'https://demo.example.test',
      'app.debug' => false,
      'database.connections.sqlite.database' => 'demo_database',
      'session.driver' => 'database',
      'cache.default' => 'database',
      'queue.default' => 'database',
      'mail.default' => 'array',
      'webblocks-cms.public_demo.enabled' => true,
      'webblocks-cms.public_demo.reset_enabled' => true,
      'webblocks-cms.public_demo.environment' => 'testing',
      'webblocks-cms.public_demo.host' => 'demo.example.test',
      'webblocks-cms.public_demo.expected_database' => 'demo_database',
      'webblocks-cms.public_demo.marker_path' => $this->markerPath,
      'webblocks-cms.public_demo.seeder' => PublicDemoTestSeeder::class,
    ]);
  }

  protected function tearDown(): void
  {
    @unlink($this->markerPath);

    parent::tearDown();
  }

  public function test_all_guards_must_pass(): void
  {
    app(PublicDemoResetPreflight::class)->assertSafe();

    $this->addToAssertionCount(1);
  }

  public function test_database_mismatch_aborts(): void
  {
    config()->set('webblocks-cms.public_demo.expected_database', 'production_database');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('active database');

    app(PublicDemoResetPreflight::class)->assertSafe();
  }

  public function test_missing_marker_aborts(): void
  {
    unlink($this->markerPath);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('storage marker');

    app(PublicDemoResetPreflight::class)->assertSafe();
  }
}

class PublicDemoTestSeeder extends Seeder
{
  public function run(): void {}
}
