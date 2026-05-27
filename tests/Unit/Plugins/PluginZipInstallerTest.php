<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use ZipArchive;

class PluginZipInstallerTest extends TestCase
{
  private string $installRoot;

  protected function setUp(): void
  {
    parent::setUp();

    $this->installRoot = storage_path('framework/testing/plugin-installer/'.str()->uuid());
    config()->set('webblocks-plugins.install.root', $this->installRoot);
  }

  #[Test]
  public function valid_plugin_zip_installs_manifest_under_safe_plugin_root_disabled(): void
  {
    $result = app(PluginZipInstaller::class)->install($this->zip([
      'webblocks-plugin.json' => json_encode($this->manifest(), JSON_PRETTY_PRINT),
      'src/ExampleServiceProvider.php' => '<?php',
    ]));

    $this->assertSame('sample-tools', $result['handle']);
    $this->assertSame('1.0.0', $result['version']);
    $this->assertFileExists($this->installRoot.'/sample-tools/1.0.0/webblocks-plugin.json');
    $this->assertFileDoesNotExist($this->installRoot.'/sample-tools/enabled.json');
  }

  #[Test]
  public function duplicate_plugin_handles_are_rejected(): void
  {
    $installer = app(PluginZipInstaller::class);
    $installer->install($this->zip(['webblocks-plugin.json' => json_encode($this->manifest())]));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('already installed');

    $installer->install($this->zip(['webblocks-plugin.json' => json_encode($this->manifest())]));
  }

  #[Test]
  public function unsafe_archive_paths_are_rejected(): void
  {
    foreach ([
      '../escape.php',
      '/absolute.php',
      'public/cms/overwrite.js',
      'vendor/package/file.php',
      'app/Http/Controllers/Controller.php',
    ] as $path) {
      try {
        app(PluginZipInstaller::class)->install($this->zip([
          'webblocks-plugin.json' => json_encode($this->manifest(['handle' => 'sample-'.str()->random(8)])),
          $path => 'unsafe',
        ]));

        $this->fail('Expected unsafe path to be rejected: '.$path);
      } catch (RuntimeException $exception) {
        $this->assertStringContainsString('Plugin package', $exception->getMessage());
      }
    }
  }

  #[Test]
  public function malformed_invalid_and_incompatible_manifests_are_rejected(): void
  {
    foreach ([
      ['handle' => 'Invalid_Handle'],
      ['version' => 'one'],
      ['provider' => ''],
      ['required_cms_version' => '>=99.0.0'],
    ] as $override) {
      try {
        app(PluginZipInstaller::class)->install($this->zip([
          'webblocks-plugin.json' => json_encode($this->manifest(array_merge($override, ['handle' => $override['handle'] ?? 'sample-'.str()->random(8)]))),
        ]));

        $this->fail('Expected invalid manifest to be rejected.');
      } catch (RuntimeException $exception) {
        $this->assertNotSame('', $exception->getMessage());
      }
    }
  }

  #[Test]
  public function symlink_entries_are_rejected(): void
  {
    $zipPath = $this->zip([
      'webblocks-plugin.json' => json_encode($this->manifest()),
      'src/link.php' => 'target',
    ]);

    $zip = new ZipArchive;
    $zip->open($zipPath);
    $zip->setExternalAttributesName('src/link.php', ZipArchive::OPSYS_UNIX, (0120000 | 0777) << 16);
    $zip->close();

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('symlinks are not allowed');

    app(PluginZipInstaller::class)->install($zipPath);
  }

  /**
   * @param  array<string, mixed>  $override
   * @return array<string, mixed>
   */
  private function manifest(array $override = []): array
  {
    return array_merge([
      'handle' => 'sample-tools',
      'label' => 'Sample Tools',
      'version' => '1.0.0',
      'provider' => 'Vendor\\SampleTools\\SampleToolsPlugin',
      'required_cms_version' => '^1.32',
      'permissions' => [],
      'commands' => [],
      'routes' => [],
      'settings' => [],
      'migrations' => [],
      'assets' => [],
      'health' => null,
    ], $override);
  }

  /**
   * @param  array<string, string>  $entries
   */
  private function zip(array $entries): string
  {
    $path = storage_path('framework/testing/plugin-'.str()->uuid().'.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $contents) {
      $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
  }
}
