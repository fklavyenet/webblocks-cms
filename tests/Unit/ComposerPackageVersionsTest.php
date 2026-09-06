<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Plugins\ComposerPackageVersions;
use WebBlocks\Cms\Tests\TestCase;

final class ComposerPackageVersionsTest extends TestCase
{
  private string $path;

  protected function setUp(): void
  {
    parent::setUp();

    $this->path = sys_get_temp_dir().'/webblocks-installed-'.uniqid('', true).'.json';
  }

  protected function tearDown(): void
  {
    File::delete($this->path);

    parent::tearDown();
  }

  public function test_it_prefers_the_current_on_disk_package_version(): void
  {
    File::put($this->path, json_encode(['packages' => [[
      'name' => 'fklavyenet/quiztem',
      'version' => '1.22.11.0',
      'pretty_version' => '1.22.11',
    ]]], JSON_THROW_ON_ERROR));

    $package = (new ComposerPackageVersions($this->path))->find('fklavyenet/quiztem');

    $this->assertTrue($package['installed']);
    $this->assertSame('1.22.11.0', $package['version']);
  }

  public function test_valid_disk_metadata_authoritatively_reports_a_missing_package(): void
  {
    File::put($this->path, json_encode(['packages' => []], JSON_THROW_ON_ERROR));

    $package = (new ComposerPackageVersions($this->path))->find('example/missing');

    $this->assertFalse($package['installed']);
    $this->assertNull($package['version']);
  }

  public function test_it_normalizes_a_composer_v_prefix_for_constraint_checks(): void
  {
    File::put($this->path, json_encode(['packages' => [[
      'name' => 'laravel/framework',
      'version' => 'v13.19.0',
    ]]], JSON_THROW_ON_ERROR));

    $package = (new ComposerPackageVersions($this->path))->find('laravel/framework');

    $this->assertSame('13.19.0', $package['version']);
  }

  public function test_invalid_disk_metadata_falls_back_to_composer_runtime_data(): void
  {
    File::put($this->path, '{invalid');

    $package = (new ComposerPackageVersions($this->path))->find('laravel/framework');

    $this->assertTrue($package['installed']);
    $this->assertNotNull($package['version']);
  }
}
