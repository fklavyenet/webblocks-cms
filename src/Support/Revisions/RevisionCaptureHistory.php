<?php

namespace WebBlocks\Cms\Support\Revisions;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\RevisionCaptureEvent;

class RevisionCaptureHistory
{
  public function for(CmsModel $revision): array
  {
    if (! Schema::hasTable('wbcms_revision_capture_events')) {
      return ['events' => [], 'has_more' => false];
    }
    $events = $revision->captureEvents()->limit(101)->get();

    return [
      'events' => $events->take(100)->map(fn (RevisionCaptureEvent $event): array => [
        'id' => $event->id,
        'saved_at' => $event->created_at?->toIso8601String(),
        'actor_user_id' => $event->created_by_user_id,
        'source' => $event->source,
        'event' => $event->event,
        'label' => $event->label,
        'reason' => $event->reason,
        'snapshot_created' => $event->snapshot_created,
      ])->values()->all(),
      'has_more' => $events->count() > 100,
    ];
  }
}
