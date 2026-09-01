<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;
use WebBlocks\Cms\Support\Updates\Client\Contracts\InstalledVersionStore;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\CmsClientBackupManager;
use WebBlocks\Cms\Support\Updates\CmsClientInstalledVersionStore;
use WebBlocks\Cms\Support\Updates\CmsClientPostApplyRunner;
use WebBlocks\Cms\Support\Updates\CmsClientRunRecorder;
use WebBlocks\Cms\Tests\TestCase;

class EmbeddedPublisherClientTest extends TestCase
{
  public function test_embedded_runtime_is_registered_with_cms_adapters(): void
  {
    $this->assertInstanceOf(CmsClientBackupManager::class, app(BackupManager::class));
    $this->assertInstanceOf(CmsClientInstalledVersionStore::class, app(InstalledVersionStore::class));
    $this->assertInstanceOf(CmsClientPostApplyRunner::class, app(PostApplyRunner::class));
    $this->assertInstanceOf(CmsClientRunRecorder::class, app(RunRecorder::class));
    $this->assertInstanceOf(SystemUpdater::class, app(SystemUpdater::class));

    $this->assertSame('webblocks-cms', config('publisher-client.product'));
    $this->assertSame('package', config('publisher-client.apply.strategy'));
    $this->assertFalse((bool) config('publisher-client.apply.composer_install'));
    $this->assertFalse((bool) config('publisher-client.migrations.enabled'));
  }

  public function test_public_composer_manifest_has_no_private_client_dependency(): void
  {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    $this->assertArrayNotHasKey('fklavyenet/webblocks-publisher-client', $composer['require'] ?? []);
  }
}
