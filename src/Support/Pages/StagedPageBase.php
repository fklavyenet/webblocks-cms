<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class StagedPageBase
{
  private const TABLE = 'wbcms_staged_page_bases';

  public function __construct(private readonly PageRevisionManager $revisions) {}

  public function fingerprint(Page $page): string
  {
    return SnapshotContent::hash($this->revisions->snapshotForInspection($page));
  }

  public function record(Page $draft, Page $source): void
  {
    if (Schema::hasTable(self::TABLE)) {
      DB::table(self::TABLE)->insert([
        'page_id' => $draft->id,
        'source_fingerprint' => $this->fingerprint($source),
      ]);
    }
  }

  public function matches(Page $draft, string $fingerprint): bool
  {
    $base = DB::table(self::TABLE)->where('page_id', $draft->id)->value('source_fingerprint');

    return is_string($base) && hash_equals($base, $fingerprint);
  }
}
