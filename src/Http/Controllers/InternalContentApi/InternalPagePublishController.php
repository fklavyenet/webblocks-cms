<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
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
    private readonly BlockTypeApiAuthoringPolicy $apiAuthoringPolicy,
  ) {}

  public function publish(Request $request, Page $page): JsonResponse
  {
    if ($this->pageScopeHasHumanOnlyBlock($page)) {
      return $this->apiAuthoringPolicy->rejectionResponse('page.blocks');
    }

    $this->rejectSharedSlotCascade($request);
    $this->rejectStagedUpdatePagePublish($page);

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

  public function archive(Request $request, Page $page): JsonResponse
  {
    $this->rejectStagedUpdatePageArchive($page);

    $fromStatus = $page->status;

    $result = DB::transaction(function () use ($page, $fromStatus): array {
      $lockedPage = Page::query()->lockForUpdate()->findOrFail($page->id);

      // Same transition rule as the admin workflow: draft pages are not
      // archivable, and re-archiving an archived page is a no-op success.
      if (! in_array($lockedPage->status, [Page::STATUS_IN_REVIEW, Page::STATUS_PUBLISHED, Page::STATUS_ARCHIVED], true)) {
        throw ValidationException::withMessages([
          'page' => 'This page cannot be archived from its current status.',
        ]);
      }

      if ($lockedPage->status !== Page::STATUS_ARCHIVED) {
        $actor = $this->currentActorResolver->resolve(preferredSource: 'internal-api');
        $lockedPage->forceFill([
          'status' => Page::STATUS_ARCHIVED,
          'archived_by_user_id' => $actor['user_id'],
          'updated_by_user_id' => $actor['user_id'],
        ])->save();
      }

      $updatedPage = $lockedPage->fresh(['site.locales', 'translations.locale', 'slots.slotType']);
      $revision = $this->revisionManager->capture(
        $updatedPage,
        label: 'Page archived',
        reason: 'Internal Content API archived the page.',
        event: 'workflow_changed',
        source: 'internal-api',
      );

      return [
        'page' => $updatedPage,
        'from_status' => $fromStatus,
        'revision_id' => $revision->id,
      ];
    });

    /** @var Page $archivedPage */
    $archivedPage = $result['page'];

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($archivedPage),
      'from_status' => $result['from_status'],
      'revision_id' => $result['revision_id'],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function publishPageOwnedBlocks(Request $request, Page $page): JsonResponse
  {
    if ($this->pageScopeHasHumanOnlyBlock($page)) {
      return $this->apiAuthoringPolicy->rejectionResponse('page.blocks');
    }

    $this->rejectSharedSlotCascade($request);
    $this->rejectStagedUpdatePagePublish($page);

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

  private function rejectStagedUpdatePagePublish(Page $page): void
  {
    $metadata = $page->settings['staged_update'] ?? null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== 'published_page_update') {
      return;
    }

    $managedSlots = is_array($metadata['managed_slots'] ?? null)
      ? array_values(array_filter($metadata['managed_slots'], 'is_string'))
      : [];

    throw new HttpResponseException(response()->json([
      'ok' => false,
      'code' => 'staged_update_requires_promote',
      'message' => 'This page is a staged update. Use promote_staged_page_update instead of page publish.',
      'recommended_action' => [
        'method' => 'POST',
        'url' => '/webadmin/api/content/apply',
        'required_capabilities' => [
          'content.apply',
          'content.publish',
        ],
        'body' => [
          'plan' => [
            'mode' => 'promote_staged_page_update',
            'staged_page_id' => $page->id,
            'expected_source_page_id' => $metadata['source_page_id'] ?? null,
            'expected_source_path' => $metadata['source_path'] ?? null,
            'promote_slots' => $managedSlots,
          ],
        ],
      ],
      'warnings' => [
        'Publishing a staged update page would not update the public source page.',
      ],
      'errors' => [
        [
          'path' => 'page.staged_update',
          'message' => 'Staged updates must be promoted onto their source page.',
        ],
      ],
    ], 409));
  }

  private function rejectStagedUpdatePageArchive(Page $page): void
  {
    $metadata = $page->settings['staged_update'] ?? null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== 'published_page_update') {
      return;
    }

    throw ValidationException::withMessages([
      'page' => 'This page is a staged update. Promote it onto its source page via content apply, or delete it with DELETE /webadmin/api/pages/{page}; archiving a staged draft is not supported.',
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

  /**
   * True when the page-owned scope that would be published contains a
   * human-only block such as Trusted HTML.
   */
  private function pageScopeHasHumanOnlyBlock(Page $page): bool
  {
    return $this->apiAuthoringPolicy->scopeHasHumanOnlyBlock(
      $page->blocks()->get(['id', 'type', 'block_type_id'])
    );
  }
}
