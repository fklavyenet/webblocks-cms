<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Builds the `media_library` section of GET /content-contract from the router.
 *
 * The contract used to publish a hand-written `unsupported_operations` list.
 * It drifted: upload, fetch, delete, replace and move were all shipped as real
 * endpoints and advertised in openapi.json while the contract still called them
 * unsupported. The AI guide tells tools to trust the contract and never guess,
 * so the stale list produced a false capability gap — clients refused work the
 * API actually supports.
 *
 * Deriving the section from the same route table openapi.json describes means
 * the two cannot disagree again: an operation is supported exactly when its
 * route is registered.
 */
class MediaContractRegistry
{
  /**
   * Internal API route name (without the group prefix) => the operation label
   * the contract has always used. The labels are kept byte-identical to the
   * historical `unsupported_operations` strings so a client that matched on
   * them keeps working as an operation moves from unsupported to supported.
   */
  private const ROUTE_OPERATIONS = [
    'media.store' => 'upload files',
    'media.fetch' => 'fetch remote media',
    'media.delete' => 'delete media',
    'media.replace' => 'replace binary files',
    'media.move' => 'move folders',
    'media.folders.store' => 'create media folders',
    'media.update' => 'edit media metadata',
  ];

  /**
   * Operations with no endpoint by design. These are derived facts about the
   * storage model rather than missing features: the CMS owns disk layout and
   * the intrinsic file properties, so no route will ever expose them.
   */
  private const NON_ROUTABLE_OPERATIONS = [
    'change storage paths',
    'change mime type, kind, visibility, size, or dimensions',
  ];

  /**
   * Routes whose presence means the caller can write bytes, not just metadata.
   */
  private const BINARY_WRITE_ROUTES = ['media.store', 'media.fetch', 'media.replace', 'media.delete'];

  private const ROUTE_NAME_PREFIX = 'internal-content-api.';

  public function __construct(private readonly Router $router) {}

  /**
   * @return array<string, mixed>
   */
  public function section(): array
  {
    $supported = [];
    $unsupported = self::NON_ROUTABLE_OPERATIONS;

    foreach (self::ROUTE_OPERATIONS as $name => $label) {
      $route = $this->route($name);

      if ($route === null) {
        $unsupported[] = $label;

        continue;
      }

      $supported[] = [
        'operation' => $label,
        'method' => $this->primaryMethod($route),
        'path' => '/'.ltrim($route->uri(), '/'),
        'requires_capability' => $this->capability($route),
      ];
    }

    sort($unsupported);

    return [
      'index_url' => '/webadmin/api/media',
      'update_url_template' => '/webadmin/api/media/{media}',
      'read_requires_capability' => 'media.read',
      'read_transitional_capability' => 'content.read',
      'write_requires_capability' => 'media.write',
      'write_scope' => $this->writeScope(),
      'supported_update_fields' => [
        'title',
        'alt_text',
        'caption',
        'description',
      ],
      'supported_operations' => $supported,
      'unsupported_operations' => array_values($unsupported),
      'operations_source' => 'Derived from the registered internal API route table, the same source openapi.json describes. supported_operations and unsupported_operations are disjoint by construction.',
    ];
  }

  /**
   * The full contract advertises metadata-only writing when the binary routes
   * are absent, which is what a deployment that withholds the media capabilities
   * looks like from the caller's side.
   */
  private function writeScope(): string
  {
    foreach (self::BINARY_WRITE_ROUTES as $name) {
      if ($this->route($name) !== null) {
        return 'full';
      }
    }

    return 'metadata-only';
  }

  private function route(string $name): ?Route
  {
    return $this->router->getRoutes()->getByName(self::ROUTE_NAME_PREFIX.$name);
  }

  private function primaryMethod(Route $route): string
  {
    $methods = array_values(array_diff($route->methods(), ['HEAD']));

    return $methods[0] ?? 'GET';
  }

  private function capability(Route $route): ?string
  {
    foreach ($route->gatherMiddleware() as $middleware) {
      if (is_string($middleware) && Str::startsWith($middleware, 'internal-api.capability:')) {
        return Str::after($middleware, 'internal-api.capability:');
      }
    }

    return null;
  }
}
