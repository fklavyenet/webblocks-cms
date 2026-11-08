<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Support\Database\ContentVersionUpdates;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\RestoredSettingWrites;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;

/** Collect derived-state updates within the restoration transaction. */
final class RevisionContentUpdates
{
  public function during(Closure $write, int $attempts = 1): mixed
  {
    // Open both scopes per attempt so failed standalone writes discard deferred
    // work. Existing request-level indexing scopes retain their final flush.
    return DB::transaction(fn ($connection) => app(ContentVersionUpdates::class)->during(
      $connection,
      fn () => PublicSearchIndexer::coalescing(fn () => app(RestoredIndexTargets::class)->during(
        fn () => app(RestoreLookups::class)->during(fn () => app(PersistentDocuments::class)->duringRestoreMetadata(
          fn () => app(RestoredSettingWrites::class)->during($write),
        )),
      )),
    ), $attempts);
  }
}
