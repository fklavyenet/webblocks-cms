<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageOwnedBlockPublisher;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

class InternalPagePublishController extends Controller
{
  public function __construct(
    private readonly CurrentActorResolver $currentActorResolver,
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageOwnedBlockPublisher $pageOwnedBlockPublisher,
    private readonly PageRevisionManager $revisionManager,
  ) {}

  public function publish(Request $request, Page $page): JsonResponse
  {
    $this->rejectSharedSlotCascade($request);

    $validated = $request->validate([
      'include_page_owned_blocks' => ['sometimes', 'boolean'],
    ]);

    $includePageOwnedBlocks = (bool) ($validated['include_page_owned_blocks'] ?? false);
    $fromStatus = $page->status;

    $result = DB::transaction(function () use ($page, $includePageOwnedBlocks, $fromStatus): array {
      $lockedPage = Page::query()->lockForUpdate()->findOrFail($page->id);

      if (! in_array($lockedPage->status, [Page::STATUS_DRAFT, Page::STATUS_IN_REVIEW, Page::STATUS_ARCHIVED, Page::STATUS_PUBLISHED], true)) {
        throw ValidationException::withMessages([
          'page' => 'This page cannot be published from its current status.',
        ]);
      }

      if ($lockedPage->status !== Page::STATUS_PUBLISHED) {
        $actor = $this->currentActorResolver->resolve(preferredSource: 'internal-api');
        $lockedPage->forceFill([
          'status' => Page::STATUS_PUBLISHED,
          'published_at' => now(),
          'published_by_user_id' => $actor['user_id'],
          'updated_by_user_id' => $actor['user_id'],
        ])->save();
      }

      $blockResult = $includePageOwnedBlocks
        ? $this->pageOwnedBlockPublisher->publish($lockedPage->fresh(), source: 'internal-api', captureRevision: false)
        : $this->pageOwnedBlockPublisher->summary($lockedPage->fresh());

      $updatedPage = $lockedPage->fresh(['site.locales', 'translations.locale', 'slots.slotType']);
      $revision = $this->revisionManager->capture(
        $updatedPage,
        label: $includePageOwnedBlocks ? 'Page and page-owned blocks published' : 'Page published',
        reason: $includePageOwnedBlocks
          ? 'Internal Content API published the page and explicitly included page-owned blocks.'
          : 'Internal Content API published the page without publishing draft page-owned blocks.',
        event: 'workflow_changed',
        source: 'internal-api',
      );

      return [
        'page' => $updatedPage,
        'from_status' => $fromStatus,
        'block_result' => $blockResult,
        'revision_id' => $revision->id,
      ];
    });

    /** @var Page $publishedPage */
    $publishedPage = $result['page'];
    $blockResult = $result['block_result'];

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($publishedPage),
      'from_status' => $result['from_status'],
      'included_page_owned_blocks' => $includePageOwnedBlocks,
      'page_owned_blocks_published_count' => (int) ($blockResult['published_count'] ?? 0),
      'page_owned_blocks_summary' => $blockResult,
      'shared_slots_excluded' => $blockResult['shared_slots_excluded'] ?? [],
      'revision_id' => $result['revision_id'],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function publishPageOwnedBlocks(Request $request, Page $page): JsonResponse
  {
    $this->rejectSharedSlotCascade($request);

    $result = $this->pageOwnedBlockPublisher->publish($page, source: 'internal-api');

    $page->loadMissing(['site.locales', 'translations.locale', 'slots.slotType']);

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($page->fresh(['site.locales', 'translations.locale', 'slots.slotType'])),
      'changed_page_status' => false,
      'included_page_owned_blocks' => true,
      'page_owned_blocks_published_count' => (int) $result['published_count'],
      'page_owned_blocks_summary' => $result,
      'shared_slots_excluded' => $result['shared_slots_excluded'] ?? [],
      'revision_id' => $result['revision_id'],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function rejectSharedSlotCascade(Request $request): void
  {
    foreach (['include_shared_slot_blocks', 'publish_shared_slots', 'shared_slot_cascade'] as $field) {
      if ($request->boolean($field)) {
        throw ValidationException::withMessages([
          $field => 'Shared Slot cascade publishing is not supported by this endpoint. Review and publish Shared Slots separately.',
        ]);
      }
    }
  }
}
