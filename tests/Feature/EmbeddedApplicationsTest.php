<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApplicationController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Applications\ApplicationAssetCollector;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

class EmbeddedApplicationsTest extends TestCase
{
  private string $root;

  protected function setUp(): void
  {
    parent::setUp();

    $this->root = storage_path('framework/testing/embedded-feature-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->root.'/typing');
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
      'settings_schema' => [
        'duration' => ['type' => 'integer', 'min' => 30, 'max' => 120, 'default' => 60],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    config()->set('cms.embedded_applications.roots', [['path' => $this->root, 'url' => '/test-apps']]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->root);

    parent::tearDown();
  }

  #[Test]
  public function the_core_catalog_and_capability_expose_application_support(): void
  {
    $definition = collect(app(CoreBlockTypeCatalogSyncer::class)->definitions())->firstWhere('slug', 'application');

    $this->assertNotNull($definition);
    $this->assertTrue($definition['is_system']);
    $this->assertFalse($definition['is_container']);
    $this->assertContains(CmsApiTokenCapabilities::APPLICATIONS_READ, CmsApiTokenCapabilities::ADVANCED);
    $this->assertContains(CmsApiTokenCapabilities::APPLICATIONS_READ, CmsApiTokenCapabilities::ALL);
  }

  #[Test]
  public function the_registry_api_and_openapi_publish_the_same_application_contract(): void
  {
    $controller = app(InternalApplicationController::class);
    $list = $controller->index(request())->getData(true);
    $schema = $controller->schema('typing-test')->getData(true);
    $paths = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];

    $this->assertSame('typing-test', $list['applications'][0]['handle']);
    $this->assertSame('ready', $list['applications'][0]['readiness']['status']);
    $this->assertSame('integer', $schema['settings_schema']['duration']['type']);
    $this->assertSame('applications.read', $paths['/applications']['get']['x-required-capability']);
    $this->assertArrayHasKey('/applications/{application}/schema', $paths);
  }

  #[Test]
  public function an_application_block_renders_a_safe_mount_and_collects_each_asset_once(): void
  {
    $type = new BlockType(['slug' => 'application', 'name' => 'Application']);
    $block = new Block([
      'id' => 42,
      'type' => 'application',
      'settings' => [
        'application_handle' => 'typing-test',
        'application_settings' => ['duration' => 60],
        'width' => 'wide',
      ],
    ]);
    $block->setRelation('blockType', $type);
    $block->setAttribute('render_locale_code', 'tr');

    $html = view('webblocks-cms::pages.partials.blocks.application', compact('block'))->render();
    $assets = app(ApplicationAssetCollector::class)->collect(collect([
      ['blocks' => collect([$block, $block])],
    ]));

    $this->assertStringContainsString('data-wb-application="typing-test"', $html);
    $this->assertStringContainsString('data-wb-application-mount', $html);
    $this->assertStringContainsString('&quot;duration&quot;:60', $html);
    $this->assertSame(['/test-apps/typing/app.css'], $assets['css']->pluck('path')->all());
    $this->assertSame(['/test-apps/typing/app.js'], $assets['body_end_js']->pluck('path')->all());
    $this->assertTrue($assets['has_applications']);
  }
}
