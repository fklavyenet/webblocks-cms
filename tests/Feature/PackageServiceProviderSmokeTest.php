<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentPlanController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageAssetController;
use WebBlocks\Cms\Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageServiceProviderSmokeTest extends TestCase
{
  #[Test]
  public function provider_boots_and_merges_representative_configuration(): void
  {
    $this->assertTrue($this->app->providerIsLoaded(WebBlocksCmsServiceProvider::class));
    $this->assertSame('webblocks-cms', config('webblocks-updates.product'));
    $this->assertSame('stable', config('webblocks-updates.channel'));
    $this->assertIsArray(config('webblocks-cms'));
  }

  #[Test]
  public function provider_registers_views_and_translations_from_package_paths(): void
  {
    $viewFinder = $this->app['view']->getFinder();
    $viewPaths = $viewFinder->getHints()['webblocks-cms'] ?? [];
    $translationHints = $this->app['translator']->getLoader()->namespaces();

    $this->assertContains(realpath(dirname(__DIR__, 2).'/resources/views'), array_map('realpath', $viewPaths));
    $this->assertSame(realpath(dirname(__DIR__, 2).'/resources/lang'), realpath($translationHints['webblocks-cms']));
  }

  #[Test]
  public function representative_route_files_exist_without_outer_bootstrap(): void
  {
    foreach (['admin.php', 'auth.php', 'install.php', 'public.php'] as $routeFile) {
      $this->assertFileExists(dirname(__DIR__, 2).'/routes/'.$routeFile);
    }

    $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/bootstrap/app.php');
  }

  #[Test]
  public function package_migrations_and_public_assets_are_publishable(): void
  {
    $this->assertDirectoryExists(dirname(__DIR__, 2).'/database/migrations/fresh');
    $this->assertDirectoryExists(dirname(__DIR__, 2).'/database/migrations/updates');
    $this->assertFileExists(dirname(__DIR__, 2).'/public/cms/package-boundary.json');

    $publishGroups = ServiceProvider::publishableGroups();
    $this->assertContains(WebBlocksCmsServiceProvider::ASSETS_PUBLISH_TAG, $publishGroups);
  }

  #[Test]
  public function internal_content_resource_controller_resolves_from_the_container(): void
  {
    $this->assertInstanceOf(
      InternalContentResourceController::class,
      $this->app->make(InternalContentResourceController::class),
    );

    $this->assertInstanceOf(
      InternalContentPlanController::class,
      $this->app->make(InternalContentPlanController::class),
    );

    $this->assertInstanceOf(
      InternalPageAssetController::class,
      $this->app->make(InternalPageAssetController::class),
    );
  }
}
