<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;

/**
 * Collapse one admin write's repeated search reindexes into a single pass.
 *
 * Every block, translation and slot save reindexes the whole page it belongs
 * to, and one editor save is several of those rows: the block itself plus up to
 * four translation families, plus any child blocks a builder field syncs. On a
 * page-owned block that is the same page rebuilt several times over. On a
 * Shared Slot it is every published page using the slot, rebuilt once per row —
 * a header edit on a 22-page site walked 22 full block trees per saved row.
 *
 * The reindex still happens, and still comes from the save hooks that know
 * which pages changed. It happens once, in terminate(), after the response has
 * been sent.
 */
class CoalesceSearchIndexing
{
  public function __construct(
    private readonly PublicSearchIndexer $indexer,
  ) {}

  public function handle(Request $request, Closure $next): Response
  {
    if (! $request->isMethodCacheable()) {
      $this->indexer->beginCoalescing();
    }

    return $next($request);
  }

  public function terminate(Request $request, Response $response): void
  {
    // A no-op on the reads that never opened a scope.
    $this->indexer->endCoalescing();
  }
}
