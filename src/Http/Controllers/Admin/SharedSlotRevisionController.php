<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SharedSlotRevisionController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly SharedSlotRevisionManager $revisionManager,
  ) {}

  public function index(SharedSlot $sharedSlot): View
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);
    abort_unless($this->revisionManager->canView(request()->user(), $sharedSlot), 403);

    if (! $this->revisionManager->revisionsTableExists()) {
      return redirect()
        ->route('admin.shared-slots.edit', $sharedSlot)
        ->withErrors(['revisions' => 'Shared Slot revisions are not ready yet. Run the latest migrations before opening revision history.'])
        ->throwResponse();
    }

    return view('webblocks-cms::admin.shared-slots.revisions.index', [
      'sharedSlot' => $sharedSlot->loadMissing('site'),
      'revisions' => $sharedSlot->revisions()->with(['actor', 'createdByUser', 'restoredFrom'])->get(),
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $sharedSlot),
    ]);
  }

  public function show(SharedSlot $sharedSlot, SharedSlotRevision $revision): View
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);
    abort_unless($revision->shared_slot_id === $sharedSlot->id, 404);
    abort_unless($this->revisionManager->canView(request()->user(), $sharedSlot), 403);

    if (! $this->revisionManager->revisionsTableExists()) {
      return redirect()
        ->route('admin.shared-slots.edit', $sharedSlot)
        ->withErrors(['revisions' => 'Shared Slot revisions are not ready yet. Run the latest migrations before opening revision details.'])
        ->throwResponse();
    }

    $snapshot = $revision->snapshot ?? [];

    return view('webblocks-cms::admin.shared-slots.revisions.show', [
      'sharedSlot' => $sharedSlot->loadMissing('site'),
      'revision' => $revision->loadMissing(['actor', 'createdByUser', 'restoredFrom']),
      'canRestoreRevisions' => $this->revisionManager->canRestore(request()->user(), $sharedSlot),
      'snapshotMetadata' => data_get($snapshot, 'shared_slot', []),
      'snapshotBlocks' => $this->flattenSnapshotBlocks(data_get($snapshot, 'blocks', [])),
    ]);
  }

  public function restore(SharedSlot $sharedSlot, SharedSlotRevision $revision): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);
    abort_unless($revision->shared_slot_id === $sharedSlot->id, 404);
    abort_unless($this->revisionManager->canRestore(request()->user(), $sharedSlot), 403);

    if (! $this->revisionManager->revisionsTableExists()) {
      return redirect()
        ->route('admin.shared-slots.edit', $sharedSlot)
        ->withErrors(['revisions' => 'Shared Slot revisions are not ready yet. Run the latest migrations before restoring revisions.']);
    }

    $this->revisionManager->restore($sharedSlot, $revision, request()->user());

    return redirect()
      ->route('admin.shared-slots.edit', $sharedSlot)
      ->with('status', 'Shared Slot revision restored successfully.')
      ->with('status_action', [
        'label' => 'Edit blocks',
        'url' => route('admin.shared-slots.blocks.edit', $sharedSlot),
      ]);
  }

  private function flattenSnapshotBlocks(array $blocks): Collection
  {
    $blockCollection = collect($blocks)->keyBy('snapshot_id');
    $childrenByParent = collect($blocks)
      ->groupBy(fn (array $block) => $block['parent_snapshot_id'] ?? 'root');

    $flatten = function ($parentSnapshotId, int $depth = 0) use (&$flatten, $childrenByParent, $blockCollection): array {
      return collect($childrenByParent->get($parentSnapshotId ?? 'root', []))
        ->sortBy(fn (array $block) => sprintf('%010d-%010d', (int) ($block['sort_order'] ?? 0), (int) ($block['snapshot_id'] ?? 0)))
        ->flatMap(function (array $block) use (&$flatten, $depth, $blockCollection) {
          $parent = $blockCollection->get($block['parent_snapshot_id'] ?? null);

          return array_merge([
            [
              'snapshot_id' => $block['snapshot_id'] ?? null,
              'depth' => $depth,
              'type' => $block['type'] ?? 'block',
              'title' => $block['title'] ?: $block['content'] ?: null,
              'sort_order' => $block['sort_order'] ?? 0,
              'parent_type' => $parent['type'] ?? null,
            ],
          ], $flatten($block['snapshot_id'] ?? null, $depth + 1));
        })
        ->values()
        ->all();
    };

    return collect($flatten(null));
  }
}
