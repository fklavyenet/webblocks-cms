<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Support\InternalContentApi\MediaContractRegistry;
use WebBlocks\Cms\Tests\TestCase;

/**
 * GET /content-contract used to publish a hand-written list that called upload,
 * fetch, delete, replace and move unsupported while all five shipped as routes
 * and appeared in openapi.json. The AI guide tells clients to trust the contract
 * over guessing, so the drift made tools refuse work the API supports.
 *
 * These tests hold the contract to the route table: an operation is supported
 * exactly when its endpoint is registered, and the two lists can never overlap.
 */
class MediaContractRegistryTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  private function section(): array
  {
    return app(MediaContractRegistry::class)->section();
  }

  public function test_no_operation_is_reported_as_both_supported_and_unsupported(): void
  {
    $section = $this->section();

    $supported = array_column($section['supported_operations'], 'operation');

    $this->assertNotEmpty($supported, 'The media routes are registered, so the contract must report supported operations.');
    $this->assertSame(
      [],
      array_values(array_intersect($supported, $section['unsupported_operations'])),
      'An operation with a registered route must never appear in unsupported_operations.'
    );
  }

  public function test_every_registered_media_write_route_is_published_as_supported(): void
  {
    $expected = [
      'internal-content-api.media.store' => 'upload files',
      'internal-content-api.media.fetch' => 'fetch remote media',
      'internal-content-api.media.delete' => 'delete media',
      'internal-content-api.media.replace' => 'replace binary files',
      'internal-content-api.media.move' => 'move folders',
    ];

    $supported = array_column($this->section()['supported_operations'], 'operation');

    foreach ($expected as $name => $operation) {
      $this->assertNotNull(
        Route::getRoutes()->getByName($name),
        "Route {$name} is expected to exist; update the contract registry if it was intentionally removed."
      );
      $this->assertContains($operation, $supported, "Registered route {$name} must be published as a supported contract operation.");
    }
  }

  public function test_supported_operations_carry_the_capability_the_route_enforces(): void
  {
    $capabilities = [];

    foreach ($this->section()['supported_operations'] as $operation) {
      $capabilities[$operation['operation']] = $operation['requires_capability'];
    }

    $this->assertSame('media.upload', $capabilities['upload files']);
    $this->assertSame('media.upload', $capabilities['fetch remote media']);
    $this->assertSame('media.delete', $capabilities['delete media']);
    $this->assertSame('media.replace', $capabilities['replace binary files']);
    $this->assertSame('media.move', $capabilities['move folders']);
  }

  public function test_binary_routes_promote_the_write_scope_beyond_metadata(): void
  {
    $this->assertSame('full', $this->section()['write_scope']);
  }

  public function test_operations_the_storage_model_never_exposes_stay_unsupported(): void
  {
    $unsupported = $this->section()['unsupported_operations'];

    $this->assertContains('change storage paths', $unsupported);
    $this->assertContains('change mime type, kind, visibility, size, or dimensions', $unsupported);
  }
}
