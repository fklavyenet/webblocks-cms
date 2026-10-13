<?php

namespace WebBlocks\Cms\Support\Revisions;

use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\PageRevisionCandidate;

class RevisionCleanupQuery
{
  public function batches(string $revisionModel, string $ownerKey, int $days, int $keep, bool $protectRestoreSources): Generator
  {
    $table = (new $revisionModel)->getTable();
    if (! Schema::hasTable($table)) {
      return;
    }
    $hasBytes = Schema::hasColumn($table, 'snapshot_bytes');
    $hasCandidates = $protectRestoreSources && Schema::hasTable('wbcms_page_revision_candidates');
    $cutoff = now()->subDays($days);
    $highWater = (int) DB::table($table)->max('id');
    $cursor = null;
    $currentOwner = null;
    $position = 0;

    do {
      $query = DB::table($table)->where('id', '<=', $highWater)->select(['id', $ownerKey, 'created_at']);
      $hasBytes ? $query->addSelect('snapshot_bytes') : $query->selectRaw('NULL as snapshot_bytes');
      if ($cursor !== null) {
        $query->where(function ($after) use ($ownerKey, $cursor): void {
          $after->where($ownerKey, '<', $cursor->{$ownerKey})->orWhere(function ($sameOwner) use ($ownerKey, $cursor): void {
            $sameOwner->where($ownerKey, $cursor->{$ownerKey})->where(function ($older) use ($cursor): void {
              if ($cursor->created_at === null) {
                $older->whereNull('created_at')->where('id', '<', $cursor->id);
              } else {
                $older->where('created_at', '<', $cursor->created_at)->orWhereNull('created_at')
                  ->orWhere(function ($sameTime) use ($cursor): void {
                    $sameTime->where('created_at', $cursor->created_at)->where('id', '<', $cursor->id);
                  });
              }
            });
          });
        });
      }
      // All keys use the same direction so the covering index can be scanned backwards.
      $rows = $query->orderByDesc($ownerKey)->orderByDesc('created_at')->orderByDesc('id')->limit(500)->get();
      $eligible = [];
      foreach ($rows as $row) {
        if ($currentOwner !== (int) $row->{$ownerKey}) {
          $currentOwner = (int) $row->{$ownerKey};
          $position = 0;
        }
        $position++;
        if ($position <= $keep || ($row->created_at !== null && Carbon::parse($row->created_at)->gte($cutoff))) {
          continue;
        }
        $eligible[(int) $row->id] = ['id' => (int) $row->id, 'owner_id' => $currentOwner, 'bytes' => $row->snapshot_bytes === null ? null : (int) $row->snapshot_bytes];
      }
      if ($hasCandidates && $eligible !== []) {
        foreach ($this->protectedIds(array_keys($eligible)) as $id) {
          unset($eligible[(int) $id]);
        }
      }
      $unmeasured = array_keys(array_filter($eligible, fn (array $row): bool => $row['bytes'] === null));
      if ($unmeasured !== []) {
        // Legacy/directly inserted revisions are measured in a bounded, unsorted query.
        $sizes = DB::table($table)->whereIn('id', $unmeasured)->select('id')->selectRaw('LENGTH(snapshot) as snapshot_bytes')->get();
        foreach ($sizes as $size) {
          $eligible[(int) $size->id]['bytes'] = (int) $size->snapshot_bytes;
        }
      }
      if ($eligible !== []) {
        yield array_values(array_filter($eligible, fn (array $row): bool => $row['bytes'] !== null));
      }
      $cursor = $rows->last();
    } while ($rows->count() === 500);
  }

  public function protectedIds(array $ids): array
  {
    if (! Schema::hasTable('wbcms_page_revision_candidates')) {
      return [];
    }

    return DB::table('wbcms_page_revision_candidates')->where('status', PageRevisionCandidate::STATUS_READY)
      ->whereIn('page_revision_id', $ids)->pluck('page_revision_id')->all();
  }
}
