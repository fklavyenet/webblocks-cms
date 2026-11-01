<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\PageRevisionScopeRequest;
use WebBlocks\Cms\Http\Requests\InternalContentApi\ApplyPageRevisionCandidateRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Support\Pages\PageLocaleRestoreException;
use WebBlocks\Cms\Support\Pages\PageLocaleRestorePlanner;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\TranslationRemovalConfirmationRequired;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class InternalPageRevisionController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageRevisionManager $revisions,
    private readonly PageRevisionInspector $inspector,
    private readonly PageRevisionCandidateManager $candidates,
  ) {}

  public function index(Request $request, Page $page): JsonResponse
  {
    $this->authorizeView($request, $page);
    $perPage = min(max($request->integer('per_page', 50), 1), 100);
    $paginator = $page->revisions()->with(['createdByUser', 'restoredFrom'])->paginate($perPage);
    $versions = $paginator->getCollection();
    $nextOlder = $paginator->hasMorePages()
      ? $page->revisions()->skip($paginator->currentPage() * $paginator->perPage())->first()
      : null;

    return response()->json([
      'ok' => true,
      'page_id' => $page->id,
      'versions' => $versions->map(fn (PageRevision $revision, int $index): array => [
        'id' => $revision->id,
        'saved_at' => $revision->created_at?->toIso8601String(),
        'label' => $revision->labelText(),
        'source' => $revision->source,
        'event' => $revision->event,
        'page_state' => data_get($revision->snapshot, 'page.status'),
        'summary' => $this->inspector->listSummary($revision, $versions->get($index + 1) ?? $nextOlder),
        'review_url' => route('internal-content-api.pages.versions.show', [$page, $revision], false),
      ])->values(),
      'pagination' => [
        'current_page' => $paginator->currentPage(),
        'last_page' => $paginator->lastPage(),
        'per_page' => $paginator->perPage(),
        'total' => $paginator->total(),
      ],
    ]);
  }

  public function show(PageRevisionScopeRequest $request, Page $page, PageRevision $revision): JsonResponse
  {
    $this->authorizeView($request, $page);
    $this->assertRevision($page, $revision);
    $candidate = $this->candidates->readyFor($page, $revision, $request->restoreScope(), $request->restoreLocaleId());

    $inspection = $this->inspector->inspect($page, $revision);
    if ($request->restoreScope() !== 'full_page') {
      $inspection['translation_removals'] = [];
    }

    return response()->json([
      'ok' => true,
      'page_id' => $page->id,
      'version_id' => $revision->id,
      'inspection' => $inspection,
      'locale_plan' => $request->restoreScope() === 'full_page' ? null : ($candidate ? app(PageLocaleRestorePlanner::class)->reviewCandidate($candidate) : app(PageLocaleRestorePlanner::class)->publicPlan(app(PageLocaleRestorePlanner::class)->plan($page, $revision, $request->restoreScope(), $request->restoreLocaleId()))),
      'candidate' => $candidate ? $this->candidatePayload($candidate) : null,
    ]);
  }

  public function prepare(PageRevisionScopeRequest $request, Page $page, PageRevision $revision): JsonResponse
  {
    $this->authorizeRestore($request, $page);
    $this->assertRevision($page, $revision);
    $inspection = $this->inspector->inspect($page, $revision);
    abort_if($request->restoreScope() === 'full_page' && $inspection['health']['status'] === 'blocked', 409, 'This page version has missing required references.');

    try {
      $candidate = $this->candidates->create($page, $revision, $request->user(), $request->restoreScope(), $request->restoreLocaleId());
    } catch (PageLocaleRestoreException $exception) {
      return response()->json(['ok' => false, 'code' => 'locale_restore_blocked', 'message' => $exception->getMessage(), 'issues' => $exception->issues], 409);
    }

    return response()->json(['ok' => true, 'candidate' => $this->candidatePayload($candidate)], 201);
  }

  public function apply(ApplyPageRevisionCandidateRequest $request, Page $page, PageRevisionCandidate $candidate): JsonResponse
  {
    $this->authorizeRestore($request, $page);
    abort_unless($candidate->page_id === $page->id, 404);

    try {
      $page = $this->candidates->apply($candidate, $request->user(), $request->confirmedTranslationRemovalLocaleIds(), $request->boolean('confirm_locale_restore'));
    } catch (PageLocaleRestoreException $exception) {
      return response()->json(['ok' => false, 'code' => 'locale_restore_blocked', 'message' => $exception->getMessage(), 'issues' => $exception->issues], 409);
    } catch (TranslationRemovalConfirmationRequired $exception) {
      return response()->json([
        'ok' => false,
        'code' => 'translation_removal_confirmation_required',
        'message' => $exception->getMessage(),
        'translation_removals' => $exception->translations,
      ], 409);
    } catch (RuntimeException $exception) {
      return response()->json(['ok' => false, 'code' => 'restore_candidate_stale', 'message' => $exception->getMessage()], 409);
    }

    return response()->json(['ok' => true, 'page_id' => $page->id, 'status' => 'applied']);
  }

  public function discard(Request $request, Page $page, PageRevisionCandidate $candidate): JsonResponse
  {
    $this->authorizeRestore($request, $page);
    abort_unless($candidate->page_id === $page->id, 404);
    $this->candidates->discard($candidate);

    return response()->json(['ok' => true, 'page_id' => $page->id, 'status' => 'discarded']);
  }

  private function authorizeView(Request $request, Page $page): void
  {
    abort_unless($request->user(), 403);
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->revisions->canView($request->user(), $page), 403);
  }

  private function authorizeRestore(Request $request, Page $page): void
  {
    abort_unless($request->user(), 403);
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->revisions->canRestore($request->user(), $page), 403);
  }

  private function assertRevision(Page $page, PageRevision $revision): void
  {
    abort_unless($revision->page_id === $page->id, 404);
  }

  private function candidatePayload(PageRevisionCandidate $candidate): array
  {
    $removals = $candidate->restore_scope === 'full_page' ? $this->revisions->translationRemovals($candidate->page, $candidate->revision) : [];

    return [
      'translation_removals' => $removals,
      'translation_removal_confirmation_required' => $removals !== [],
      'id' => $candidate->id,
      'restore_scope' => $candidate->restore_scope,
      'locale_id' => $candidate->restore_locale_id,
      'locale_plan' => $candidate->restore_scope === 'full_page' ? null : app(PageLocaleRestorePlanner::class)->reviewCandidate($candidate),
      'status' => $candidate->status,
      'source_updated_at' => $candidate->source_updated_at?->toIso8601String(),
      'preview_page_id' => $candidate->candidate_page_id,
      'preview_url' => $candidate->previewUrl(false),
      'apply_url' => route('internal-content-api.pages.version-candidates.apply', [$candidate->page_id, $candidate], false),
      'discard_url' => route('internal-content-api.pages.version-candidates.discard', [$candidate->page_id, $candidate], false),
    ];
  }
}
