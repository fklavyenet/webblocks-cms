<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Tests\TestCase;

class InternalApiDiscoveryControllerTest extends TestCase
{
  #[Test]
  public function openapi_schema_includes_plugin_catalog_path_parameters(): void
  {
    $response = $this->app->make(InternalApiDiscoveryController::class)->openapi();
    $schema = $response->getData(true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('plugin', $schema['paths']['/plugins/catalog/{plugin}']['get']['parameters'][0]['name']);
    $this->assertSame('path', $schema['paths']['/plugins/catalog/{plugin}']['get']['parameters'][0]['in']);
    $this->assertTrue($schema['paths']['/plugins/catalog/{plugin}']['get']['parameters'][0]['required']);
    $this->assertSame('string', $schema['paths']['/plugins/catalog/{plugin}']['get']['parameters'][0]['schema']['type']);
  }

  #[Test]
  public function openapi_schema_documents_page_block_topology_endpoints(): void
  {
    $response = $this->app->make(InternalApiDiscoveryController::class)->openapi();
    $schema = $response->getData(true);
    $paths = $schema['paths'];

    $this->assertArrayHasKey('/pages/{page}/slots/{slot}/blocks', $paths);
    $this->assertSame('content.apply', $paths['/pages/{page}/slots/{slot}/blocks']['post']['x-required-capability']);

    $this->assertArrayHasKey('/pages/{page}/slots/{slot}/blocks/reorder', $paths);
    $this->assertSame('content.apply', $paths['/pages/{page}/slots/{slot}/blocks/reorder']['patch']['x-required-capability']);

    $this->assertArrayHasKey('/pages/{page}/slots/{slot}/blocks/{block}', $paths);
    $this->assertSame('content.blocks.delete', $paths['/pages/{page}/slots/{slot}/blocks/{block}']['delete']['x-required-capability']);
  }

  #[Test]
  public function openapi_schema_documents_shared_slot_block_topology_endpoints(): void
  {
    $response = $this->app->make(InternalApiDiscoveryController::class)->openapi();
    $schema = $response->getData(true);
    $paths = $schema['paths'];

    $this->assertArrayHasKey('/shared-slots/{sharedSlot}/blocks/reorder', $paths);
    $this->assertSame('shared-slots.write', $paths['/shared-slots/{sharedSlot}/blocks/reorder']['patch']['x-required-capability']);

    $this->assertArrayHasKey('/shared-slots/{sharedSlot}/blocks/{block}', $paths);
    $this->assertSame('shared-slots.write plus content.blocks.delete', $paths['/shared-slots/{sharedSlot}/blocks/{block}']['delete']['x-required-capability']);

    $this->assertArrayHasKey('delete', $paths['/shared-slots/{sharedSlot}/blocks']);
    $this->assertSame('shared-slots.write plus content.blocks.delete', $paths['/shared-slots/{sharedSlot}/blocks']['delete']['x-required-capability']);
  }

  #[Test]
  public function openapi_schema_documents_the_apply_restore_point_option(): void
  {
    $response = $this->app->make(InternalApiDiscoveryController::class)->openapi();
    $schema = $response->getData(true);
    $apply = $schema['paths']['/content/apply']['post'];

    $this->assertArrayHasKey('x-optional-fields', $apply);
    $this->assertStringContainsString('create_restore_point', implode(' ', $apply['x-optional-fields']));
    $this->assertArrayHasKey('409', $apply['responses']);
  }

  #[Test]
  public function openapi_schema_documents_page_asset_endpoints(): void
  {
    $response = $this->app->make(InternalApiDiscoveryController::class)->openapi();
    $schema = $response->getData(true);
    $paths = $schema['paths'];

    $this->assertArrayHasKey('/pages/{page}/assets', $paths);
    $this->assertArrayHasKey('get', $paths['/pages/{page}/assets']);

    $this->assertSame('page-assets.write', $paths['/pages/{page}/assets/{type}']['post']['x-required-capability']);
    $this->assertStringContainsString('/site/', $paths['/pages/{page}/assets/{type}']['post']['x-path-contract']);

    $this->assertSame('page-assets.write', $paths['/pages/{page}/assets/{pageAsset}']['patch']['x-required-capability']);
    $this->assertSame('page-assets.write', $paths['/pages/{page}/assets/{pageAsset}']['delete']['x-required-capability']);
  }
}
