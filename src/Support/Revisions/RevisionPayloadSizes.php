<?php

namespace WebBlocks\Cms\Support\Revisions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Relational\PersistentFields;

final class RevisionPayloadSizes
{
  /** Metadata-only size reads after cutover; bounded unsorted legacy reads before it. */
  public static function forIds(string $table, iterable $ids): array
  {
    $ids = collect($ids)->all();
    if (Schema::hasColumn($table, 'snapshot')) {
      return DB::table($table)->whereIn('id', $ids)->select('id')->selectRaw('LENGTH(snapshot) as bytes')->pluck('bytes', 'id')->all();
    }
    $owner = $table === 'wbcms_page_revisions' ? PersistentFields::MODELS['PageRevision'][0] : PersistentFields::MODELS['SharedSlotRevision'][0];

    return DB::table('wbcms_persistent_documents')->whereIn($owner, $ids)->where('attribute', 'snapshot')->pluck('payload_bytes', $owner)->all();
  }
}
