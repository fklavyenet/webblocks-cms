<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Applications\ApplicationRegistry;
use WebBlocks\Cms\Support\Applications\ApplicationSettingsValidator;
use WebBlocks\Cms\Tests\TestCase;

class ApplicationRegistryTest extends TestCase
{
  private string $root;

  protected function setUp(): void
  {
    parent::setUp();

    $this->root = storage_path('framework/testing/embedded-applications-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->root.'/typing');
    config()->set('cms.embedded_applications.roots', [
      ['path' => $this->root, 'url' => '/test-apps'],
    ]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->root);

    parent::tearDown();
  }

  public function test_it_discovers_and_normalizes_a_ready_local_manifest(): void
  {
    File::put($this->root.'/typing/app.css', '.typing {}');
    File::put($this->root.'/typing/app.js', 'export default {};');
    File::put($this->root.'/typing/application.json', json_encode([
      'schema_version' => 1,
      'handle' => 'typing-test',
      'name' => 'Typing Test',
      'version' => '1.0.0',
      'render_mode' => 'inline',
      'mount' => ['element' => 'div', 'class' => 'typing-app'],
      'assets' => [
        'css' => [['path' => 'app.css']],
        'js' => [['path' => 'app.js', 'type' => 'module']],
      ],
      'supports' => ['locale' => true, 'theme' => true],
      'settings_schema' => [
        'duration' => ['type' => 'integer', 'min' => 30, 'max' => 120, 'default' => 60],
        'show_live_stats' => ['type' => 'boolean', 'default' => true],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $definition = app(ApplicationRegistry::class)->ready('typing-test');

    $this->assertNotNull($definition);
    $this->assertSame('/test-apps/typing/app.css', $definition->assets['css'][0]['path']);
    $this->assertSame('/test-apps/typing/app.js', $definition->assets['js'][0]['path']);
    $this->assertSame('module', $definition->assets['js'][0]['type']);
    $this->assertTrue($definition->supports['locale']);
    $this->assertArrayNotHasKey('path', $definition->toArray()['provider']);
  }

  public function test_it_reports_missing_and_traversing_files_without_exposing_absolute_paths(): void
  {
    File::put($this->root.'/typing/application.json', json_encode([
      'schema_version' => 1,
      'handle' => 'unsafe-app',
      'name' => 'Unsafe App',
      'render_mode' => 'inline',
      'assets' => [
        'css' => [['path' => '../secret.css']],
        'js' => [['path' => 'missing.js']],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $definition = app(ApplicationRegistry::class)->find('unsafe-app');
    $payload = $definition?->toArray();

    $this->assertNotNull($definition);
    $this->assertFalse($definition->isReady());
    $this->assertContains('application_asset_path_invalid', array_column($payload['readiness']['issues'], 'code'));
    $this->assertContains('application_asset_missing', array_column($payload['readiness']['issues'], 'code'));
    $this->assertStringNotContainsString($this->root, json_encode($payload));
  }

  public function test_settings_are_normalized_against_the_manifest_schema(): void
  {
    File::put($this->root.'/typing/app.js', 'export default {};');
    File::put($this->root.'/typing/application.json', json_encode([
      'schema_version' => 1,
      'handle' => 'typing-test',
      'name' => 'Typing Test',
      'render_mode' => 'inline',
      'assets' => ['js' => [['path' => 'app.js']]],
      'settings_schema' => [
        'duration' => ['type' => 'enum', 'values' => [30, 60, 120], 'default' => 60],
        'layout' => ['type' => 'string', 'max_length' => 16],
        'enabled' => ['type' => 'boolean', 'default' => true],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $definition = app(ApplicationRegistry::class)->ready('typing-test');
    $errors = [];
    $normalized = app(ApplicationSettingsValidator::class)->normalize($definition, [
      'duration' => 60,
      'layout' => 'tr-f',
      'enabled' => '0',
      'unknown' => true,
    ], 'settings.application_settings', $errors);

    $this->assertSame(['duration' => 60, 'layout' => 'tr-f', 'enabled' => false], $normalized);
    $this->assertSame('application_setting_unknown', $errors[0]['code']);
  }

  public function test_duplicate_handles_fail_closed_without_exposing_paths(): void
  {
    foreach (['typing', 'other'] as $directory) {
      File::ensureDirectoryExists($this->root.'/'.$directory);
      File::put($this->root.'/'.$directory.'/app.js', 'export default {};');
      File::put($this->root.'/'.$directory.'/application.json', json_encode([
        'schema_version' => 1,
        'handle' => 'duplicate-app',
        'name' => 'Duplicate App',
        'render_mode' => 'inline',
        'assets' => ['js' => [['path' => 'app.js']]],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    $definition = app(ApplicationRegistry::class)->find('duplicate-app');

    $this->assertNotNull($definition);
    $this->assertFalse($definition->isReady());
    $this->assertContains('application_handle_duplicate', array_column($definition->issues, 'code'));
    $this->assertStringNotContainsString($this->root, json_encode($definition->toArray()));
  }
}
