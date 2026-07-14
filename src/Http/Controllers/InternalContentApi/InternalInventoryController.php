<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Serves the packaged AI authoring inventory so trusted tools can read the
 * per-block design contract before proposing or applying a page design.
 */
class InternalInventoryController extends Controller
{
  private const DOCUMENT = 'docs/inventory.md';

  public function show(): JsonResponse
  {
    $path = $this->documentPath();

    if (! is_file($path) || ! is_readable($path)) {
      return response()->json([
        'ok' => false,
        'code' => 'inventory_unavailable',
        'message' => 'The packaged CMS inventory document is not available in this installation.',
        'warnings' => [],
        'errors' => [
          ['path' => 'inventory', 'message' => 'The inventory document could not be read from the installed package.'],
        ],
      ], 404);
    }

    $contents = @file_get_contents($path);

    if ($contents === false) {
      return response()->json([
        'ok' => false,
        'code' => 'inventory_unavailable',
        'message' => 'The packaged CMS inventory document could not be read.',
        'warnings' => [],
        'errors' => [
          ['path' => 'inventory', 'message' => 'The inventory document could not be read from the installed package.'],
        ],
      ], 404);
    }

    return response()->json([
      'ok' => true,
      'inventory' => [
        'format' => 'markdown',
        'document' => self::DOCUMENT,
        'checksum_sha256' => hash('sha256', $contents),
        'content' => $contents,
      ],
      '_links' => [
        'self' => '/webadmin/api/inventory',
        'api_discovery' => '/webadmin/api',
        'ai_guide' => '/webadmin/api/ai-guide',
        'content_contract' => '/webadmin/api/content-contract',
        'block_types' => '/webadmin/api/block-types',
        'documentation' => '/docs/inventory',
      ],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  /**
   * Resolved from the installed package root, never from a host-specific
   * absolute path baked into the code or responses.
   */
  private function documentPath(): string
  {
    return dirname(__DIR__, 4).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::DOCUMENT);
  }
}
