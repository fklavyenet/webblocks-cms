<?php

namespace WebBlocks\Cms\Tests\Unit;

use RuntimeException;
use WebBlocks\Cms\Support\System\Updates\PackageLicensePromoter;
use WebBlocks\Cms\Tests\TestCase;

class PackageLicensePromoterTest extends TestCase
{
  private string $packageRoot;

  protected function setUp(): void
  {
    parent::setUp();

    $this->packageRoot = sys_get_temp_dir().'/webblocks-license-'.uniqid('', true);
    mkdir($this->packageRoot.'/docs', 0775, true);
  }

  protected function tearDown(): void
  {
    if (is_dir($this->packageRoot)) {
      app('files')->deleteDirectory($this->packageRoot);
    }

    parent::tearDown();
  }

  public function test_it_promotes_the_validated_notice_to_the_package_root(): void
  {
    file_put_contents($this->packageRoot.'/docs/LICENSE', 'MIT notice');

    app(PackageLicensePromoter::class)->promote($this->packageRoot);

    $this->assertSame('MIT notice', file_get_contents($this->packageRoot.'/LICENSE'));
  }

  public function test_it_is_idempotent_when_the_root_notice_matches(): void
  {
    file_put_contents($this->packageRoot.'/docs/LICENSE', 'MIT notice');
    file_put_contents($this->packageRoot.'/LICENSE', 'MIT notice');

    app(PackageLicensePromoter::class)->promote($this->packageRoot);

    $this->assertSame('MIT notice', file_get_contents($this->packageRoot.'/LICENSE'));
  }

  public function test_it_refuses_to_overwrite_a_different_root_license(): void
  {
    file_put_contents($this->packageRoot.'/docs/LICENSE', 'MIT notice');
    file_put_contents($this->packageRoot.'/LICENSE', 'different');

    $this->expectException(RuntimeException::class);
    app(PackageLicensePromoter::class)->promote($this->packageRoot);
  }
}
