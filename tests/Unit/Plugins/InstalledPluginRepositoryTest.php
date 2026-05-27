<?php

namespace Tests\Unit\Plugins;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginException;

class InstalledPluginRepositoryTest extends TestCase
{
  private string $installRoot;

  protected function setUp(): void
  {
    parent::setUp();

    $this->installRoot = storage_path('framework/testing/installed-plugins/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $this->installRoot);
  }

  #[Test]
  public function uninstall_removes_only_the_selected_plugin_version_and_enabled_state(): void
  {
    File::ensureDirectoryExists($this->installRoot.'/sample-tools/1.0.0/src');
    File::ensureDirectoryExists($this->installRoot.'/sample-tools/1.1.0');
    File::put($this->installRoot.'/sample-tools/1.0.0/src/Provider.php', '<?php');

    $repository = app(InstalledPluginRepository::class);
    $repository->enable('sample-tools', '1.0.0');
    $repository->uninstall('sample-tools', '1.0.0');

    $this->assertDirectoryDoesNotExist($this->installRoot.'/sample-tools/1.0.0');
    $this->assertDirectoryExists($this->installRoot.'/sample-tools/1.1.0');
    $this->assertFileDoesNotExist($this->installRoot.'/sample-tools/enabled.json');
  }

  #[Test]
  public function uninstall_rejects_coordinates_that_could_escape_the_plugin_root(): void
  {
    File::ensureDirectoryExists($this->installRoot);

    $this->expectException(PluginException::class);

    app(InstalledPluginRepository::class)->uninstall('../sample-tools', '1.0.0');
  }

  #[Test]
  public function uninstall_is_clean_when_plugin_files_are_already_missing(): void
  {
    $repository = app(InstalledPluginRepository::class);

    $repository->enable('sample-tools', '1.0.0');
    $repository->uninstall('sample-tools', '1.0.0');

    $this->assertFileDoesNotExist($this->installRoot.'/sample-tools/enabled.json');
  }
}
