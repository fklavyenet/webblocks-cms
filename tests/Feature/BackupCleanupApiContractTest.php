<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

class BackupCleanupApiContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function backup_cleanup_routes_enforce_separate_read_write_and_delete_capabilities(): void
  {
    $expected = [
      'internal-content-api.system.backup-cleanup.show' => 'internal-api.capability:'.CmsApiTokenCapabilities::BACKUPS_READ,
      'internal-content-api.system.backup-cleanup.update' => 'internal-api.capability:'.CmsApiTokenCapabilities::BACKUPS_SETTINGS_WRITE,
      'internal-content-api.system.backup-cleanup.run' => 'internal-api.capability:'.CmsApiTokenCapabilities::BACKUPS_DELETE,
    ];

    foreach ($expected as $name => $middleware) {
      $this->assertContains($middleware, Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? []);
    }

    $this->assertContains(CmsApiTokenCapabilities::BACKUPS_DELETE, CmsApiTokenCapabilities::DESTRUCTIVE);
  }

  #[Test]
  public function openapi_documents_the_policy_and_destructive_run(): void
  {
    $controller = $this->app->make(InternalApiDiscoveryController::class);
    $paths = $controller->openapi()->getData(true)['paths'];

    $this->assertSame(CmsApiTokenCapabilities::BACKUPS_READ, $paths['/system/backup-cleanup']['get']['x-required-capability']);
    $this->assertSame(CmsApiTokenCapabilities::BACKUPS_SETTINGS_WRITE, $paths['/system/backup-cleanup']['put']['x-required-capability']);
    $this->assertSame(CmsApiTokenCapabilities::BACKUPS_DELETE, $paths['/system/backup-cleanup/run']['post']['x-required-capability']);
    $this->assertTrue($paths['/system/backup-cleanup/run']['post']['x-destructive']);
    $this->assertSame(CmsApiTokenCapabilities::MAINTENANCE_READ, $paths['/system/cleanup']['get']['x-required-capability']);
    $this->assertSame(CmsApiTokenCapabilities::MAINTENANCE_SETTINGS_WRITE, $paths['/system/cleanup']['put']['x-required-capability']);
    $this->assertSame(CmsApiTokenCapabilities::MAINTENANCE_DELETE, $paths['/system/cleanup/{category}/run']['post']['x-required-capability']);
    $this->assertTrue($paths['/system/cleanup/{category}/run']['post']['x-destructive']);
  }

  #[Test]
  public function maintenance_cleanup_routes_use_separate_capabilities(): void
  {
    $this->assertContains('internal-api.capability:maintenance.read', Route::getRoutes()->getByName('internal-content-api.system.cleanup.show')?->gatherMiddleware() ?? []);
    $this->assertContains('internal-api.capability:maintenance.settings.write', Route::getRoutes()->getByName('internal-content-api.system.cleanup.update')?->gatherMiddleware() ?? []);
    $this->assertContains('internal-api.capability:maintenance.delete', Route::getRoutes()->getByName('internal-content-api.system.cleanup.run')?->gatherMiddleware() ?? []);
    $this->assertContains(CmsApiTokenCapabilities::MAINTENANCE_DELETE, CmsApiTokenCapabilities::DESTRUCTIVE);
  }
}
