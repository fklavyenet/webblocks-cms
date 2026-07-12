<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ComposerManifestTest extends TestCase
{
  private array $composer;

  protected function setUp(): void
  {
    parent::setUp();

    $this->composer = json_decode(
      (string) file_get_contents(dirname(__DIR__, 2).'/composer.json'),
      true,
      512,
      JSON_THROW_ON_ERROR,
    );
  }

  #[Test]
  public function manifest_has_standalone_package_identity_and_platform_requirements(): void
  {
    $this->assertSame('fklavyenet/webblocks-cms', $this->composer['name']);
    $this->assertSame('library', $this->composer['type']);
    $this->assertSame('MIT', $this->composer['license']);
    $this->assertSame('^8.4', $this->composer['require']['php']);
    $this->assertSame('^13.0', $this->composer['require']['laravel/framework']);
    $this->assertSame('*', $this->composer['require']['ext-mbstring']);
    $this->assertSame('*', $this->composer['require']['ext-zip']);
    $this->assertSame('*', $this->composer['require']['ext-sodium']);
    $this->assertArrayNotHasKey('version', $this->composer);
  }

  #[Test]
  public function manifest_preserves_package_autoload_and_provider_discovery(): void
  {
    $this->assertSame('src/', $this->composer['autoload']['psr-4']['WebBlocks\\Cms\\']);
    $this->assertSame('database/seeders/', $this->composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\']);
    $this->assertSame('tests/', $this->composer['autoload-dev']['psr-4']['WebBlocks\\Cms\\Tests\\']);
    $this->assertSame(
      ['WebBlocks\\Cms\\WebBlocksCmsServiceProvider'],
      $this->composer['extra']['laravel']['providers'],
    );
  }

  #[Test]
  public function manifest_has_no_outer_application_autoload_or_script_dependencies(): void
  {
    $encoded = json_encode($this->composer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    foreach (['App\\\\', 'Project\\\\', 'plugins/', 'packages/webblocks-cms', 'artisan'] as $forbidden) {
      $this->assertStringNotContainsString($forbidden, $encoded);
    }
  }
}
