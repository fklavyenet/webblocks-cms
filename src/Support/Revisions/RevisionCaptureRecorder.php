<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\RevisionCaptureEvent;

class RevisionCaptureRecorder
{
  public function record(CmsModel $owner, string $revisionModel, string $ownerKey, Closure $snapshot, array $metadata, bool $force = false): CmsModel
  {
    return DB::transaction(function () use ($owner, $revisionModel, $ownerKey, $snapshot, $metadata, $force): CmsModel {
      // Serialize capture/deduplication with editing and restore-candidate preparation.
      $owner = $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());
      $payload = $snapshot($owner);
      $table = (new $revisionModel)->getTable();
      $ready = Schema::hasColumn($table, 'snapshot_hash') && Schema::hasColumn($table, 'snapshot_bytes') && Schema::hasTable('wbcms_revision_capture_events');
      $hash = SnapshotContent::hash($payload);
      $revision = null;

      if ($ready && ! $force) {
        $latest = $revisionModel::query()->where($ownerKey, $owner->getKey())->latest('id')->first(['id', 'snapshot_hash']);
        if ($latest) {
          if ($latest->snapshot_hash === null) {
            $latest = $revisionModel::query()->findOrFail($latest->id);
            $this->measure($latest, SnapshotContent::hash($latest->snapshot ?? []));
          }
          if (hash_equals($hash, $latest->snapshot_hash)) {
            $revision = $revisionModel::query()->findOrFail($latest->id);
          }
        }
      }

      $created = $revision === null;
      if ($created) {
        $revision = $revisionModel::query()->create([
          ...$metadata,
          $ownerKey => $owner->getKey(),
          'site_id' => $owner->site_id,
          'snapshot' => $payload,
        ]);
        if ($ready) {
          $this->measure($revision, $hash);
        }
      }

      if ($ready) {
        RevisionCaptureEvent::query()->create([
          'page_revision_id' => $revision instanceof PageRevision ? $revision->id : null,
          'shared_slot_revision_id' => $revision instanceof PageRevision ? null : $revision->id,
          'created_by_user_id' => $metadata['created_by_user_id'] ?? null,
          'source' => $metadata['source'] ?? null,
          'event' => $metadata['event'] ?? null,
          'label' => $metadata['label'] ?? null,
          'reason' => $metadata['reason'] ?? $metadata['summary'] ?? null,
          'snapshot_created' => $created,
        ]);
      }

      return $revision;
    });
  }

  private function measure(CmsModel $revision, string $hash): void
  {
    // Measure the database representation once, outside any ordered query.
    $bytes = (int) (RevisionPayloadSizes::forIds($revision->getTable(), [$revision->id])[$revision->id] ?? 0);
    $revision->forceFill(['snapshot_hash' => $hash, 'snapshot_bytes' => $bytes])->saveQuietly();
  }
}
