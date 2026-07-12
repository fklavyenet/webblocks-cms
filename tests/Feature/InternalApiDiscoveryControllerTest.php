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
}
