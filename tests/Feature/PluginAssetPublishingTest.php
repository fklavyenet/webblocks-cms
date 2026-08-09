<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Plugins\PluginAssetPublisher;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Core gap 0.6: plugin static assets were declarable and not servable.
 *
 * `PluginPublicAsset` could always emit a `<link>` or `<script>`, and the manifest
 * always documented an `assets` key — but nothing put the files anywhere a browser
 * could reach, so the tag pointed at a 404. The appointments plugin hit this while
 * building its booking form and shipped a fully server-rendered flow instead.
 *
 * The publishing half is a copy into the document root, which makes the filter on
 * *what* gets copied the security boundary: anything that lands there is served by
 * the web server on the site's own origin.
 */
class PluginAssetPublishingTest extends TestCase
{
  private string $pluginPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->pluginPath = storage_path('framework/testing/plugin-assets/example-plugin');
    File::deleteDirectory(dirname($this->pluginPath));
    File::ensureDirectoryExists($this->pluginPath.'/'.PluginAssetPublisher::SOURCE_DIRECTORY);

    File::deleteDirectory(public_path('cms/plugins/example-plugin'));
  }

  protected function tearDown(): void
  {
    File::deleteDirectory(dirname($this->pluginPath));
    File::deleteDirectory(public_path('cms/plugins/example-plugin'));

    parent::tearDown();
  }

  public function test_declared_assets_are_copied_into_the_document_root(): void
  {
    $this->writeAsset('forms.css', 'body{}');
    $this->writeAsset('nested/forms.js', 'console.log(1)');

    $result = (new PluginAssetPublisher)->publish($this->plugin());

    $this->assertSame(2, $result['published']);
    $this->assertFileExists(public_path('cms/plugins/example-plugin/forms.css'));
    $this->assertFileExists(public_path('cms/plugins/example-plugin/nested/forms.js'));
  }

  public function test_executable_and_scriptable_files_are_never_published(): void
  {
    /*
     * The whole reason the filter exists. `.php` is the obvious one; `.svg` and
     * `.html` are the ones people argue for, and both are documents a browser will
     * execute script from on the site's own origin — which is the admin's origin.
     */
    foreach (['shell.php', 'page.html', 'logo.svg', 'data.xml', 'run.sh', 'app.phtml'] as $name) {
      $this->writeAsset($name, 'x');
    }

    $result = (new PluginAssetPublisher)->publish($this->plugin());

    $this->assertSame(0, $result['published']);
    $this->assertSame(6, $result['skipped']);

    foreach (['shell.php', 'page.html', 'logo.svg', 'data.xml', 'run.sh', 'app.phtml'] as $name) {
      $this->assertFileDoesNotExist(public_path('cms/plugins/example-plugin/'.$name));
    }
  }

  public function test_a_symlink_out_of_the_source_directory_is_refused(): void
  {
    /*
     * A symlink is a path out of the directory the checks are scoped to. Refusing
     * it outright is cheaper than resolving it and arguing about where it landed —
     * and one plugin symlinking `/` would otherwise publish the filesystem.
     */
    $secret = storage_path('framework/testing/plugin-assets/secret.css');
    File::put($secret, 'body{}');
    symlink($secret, $this->pluginPath.'/'.PluginAssetPublisher::SOURCE_DIRECTORY.'/linked.css');

    $result = (new PluginAssetPublisher)->publish($this->plugin());

    $this->assertSame(0, $result['published']);
    $this->assertFileDoesNotExist(public_path('cms/plugins/example-plugin/linked.css'));
  }

  public function test_dotfiles_are_never_published(): void
  {
    // `.env` is the one that matters, and no plugin ever meant to serve a dotfile.
    $this->writeAsset('.env', 'APP_KEY=secret');

    $result = (new PluginAssetPublisher)->publish($this->plugin());

    $this->assertSame(0, $result['published']);
    $this->assertFileDoesNotExist(public_path('cms/plugins/example-plugin/.env'));
  }

  public function test_publishing_removes_files_a_release_no_longer_ships(): void
  {
    /*
     * Publishing over the top would leave the previous version's scripts reachable
     * for ever, which is what makes "we removed that" untrue.
     */
    $this->writeAsset('old.js', 'old');
    (new PluginAssetPublisher)->publish($this->plugin());
    $this->assertFileExists(public_path('cms/plugins/example-plugin/old.js'));

    File::delete($this->pluginPath.'/'.PluginAssetPublisher::SOURCE_DIRECTORY.'/old.js');
    $this->writeAsset('new.js', 'new');
    (new PluginAssetPublisher)->publish($this->plugin());

    $this->assertFileDoesNotExist(public_path('cms/plugins/example-plugin/old.js'));
    $this->assertFileExists(public_path('cms/plugins/example-plugin/new.js'));
  }

  public function test_a_plugin_with_no_asset_directory_publishes_nothing(): void
  {
    // Most plugins. It must be silent for them rather than creating empty folders.
    File::deleteDirectory($this->pluginPath.'/'.PluginAssetPublisher::SOURCE_DIRECTORY);

    $this->assertNull((new PluginAssetPublisher)->publish($this->plugin()));
  }

  public function test_uninstalling_removes_the_published_copy(): void
  {
    $this->writeAsset('forms.css', 'body{}');
    $publisher = new PluginAssetPublisher;
    $publisher->publish($this->plugin());

    $publisher->unpublish('example-plugin');

    $this->assertDirectoryDoesNotExist(public_path('cms/plugins/example-plugin'));
  }

  public function test_a_malformed_handle_cannot_delete_an_arbitrary_directory(): void
  {
    /*
     * `unpublish` builds a path from a handle. A handle is validated everywhere it
     * is created, but this is the call that turns one into `deleteDirectory`, so it
     * refuses anything that is not a handle rather than trusting its callers.
     */
    $publisher = new PluginAssetPublisher;
    $canary = public_path('cms/plugins/canary');
    File::ensureDirectoryExists($canary);

    $publisher->unpublish('../../..');
    $publisher->unpublish('');

    $this->assertDirectoryExists($canary);
    File::deleteDirectory($canary);
  }

  private function writeAsset(string $relative, string $contents): void
  {
    $path = $this->pluginPath.'/'.PluginAssetPublisher::SOURCE_DIRECTORY.'/'.$relative;
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
  }

  private function plugin(): PluginDefinition
  {
    return PluginDefinition::make('example-plugin')
      ->label('Example')
      ->version('1.0.0')
      ->installPath($this->pluginPath);
  }
}
