<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use WebBlocks\Cms\Models\AutomaticPageRule;

final class AutomaticPageCatalogue
{
  /** Current, complete records; reject duplicate identities/slugs and cursor cycles. */
  public function records(AutomaticPageRule $rule, ?Authenticatable $actor = null, bool $preview = false): array
  {
    $inspector = app(AutomaticPageInspector::class);
    $cursor = null;
    $cursors = [];
    $records = [];
    $slugs = [];
    do {
      $result = $inspector->entries($rule, $actor, $cursor, $preview);
      if ($result['status'] !== 'available') {
        throw new RuntimeException('Publication catalogue unavailable.');
      }
      foreach ($result['page']->entries as $entry) {
        $key = $entry['key'];
        $record = $inspector->record($rule, $key, $actor, $preview);
        if (isset($records[$key]) || $record['status'] !== 'available' || isset($slugs[$record['slug']])) {
          throw new RuntimeException('Invalid or incomplete publication catalogue.');
        }
        $records[$key] = $record;
        $slugs[$record['slug']] = true;
      }
      $cursor = $result['page']->nextCursor;
      if ($cursor !== null && isset($cursors[$cursor])) {
        throw new RuntimeException('Publication cursor cycle.');
      }
      $cursors[$cursor ?? ''] = true;
    } while ($cursor !== null);
    ksort($records);

    return $records;
  }
}
