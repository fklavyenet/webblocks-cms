<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
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
    $versions = $page->revisions()->with(['createdByUser', 'restoredFrom'])->get();

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
        'summary' => $this->inspector->listSummary($revision, $versions->get($index + 1)),
        'review_url' => route('internal-content-api.pages.versions.show', [$page, $revision], false),
      ])->values(),
    ]);
  }

  public function show(Request $request, Page $page, PageRevision $revision): JsonResponse
  {
    $this->authorizeView($request, $page);
    $this->assertRevision($page, $revision);
    $candidate = $this->candidates->readyFor($page, $revision);

    return response()->json([
      'ok' => true,
      'page_id' => $page->id,
      'version_id' => $revision->id,
      'inspection' => $this->inspector->inspect($page, $revision),
      'candidate' => $candidate ? $this->candidatePayload($candidate) : null,
    ]);
  }

  public function prepare(Request $request, Page $page, PageRevision $revision): JsonResponse
  {
    $this->authorizeRestore($request, $page);
    $this->assertRevision($page, $revision);
    $inspection = $this->inspector->inspect($page, $revision);
    abort_if($inspection['health']['status'] === 'blocked', 409, 'This page version has missing required references.');

    $candidate = $this->candidates->create($page, $revision, $request->user());

    return response()->json(['ok' => true, 'candidate' => $this->candidatePayload($candidate)], 201);
  }

  public function apply(Request $request, Page $page, PageRevisionCandidate $candidate): JsonResponse
  {
    $this->authorizeRestore($request, $page);
    abort_unless($candidate->page_id === $page->id, 404);

    try {
      $page = $this->candidates->apply($candidate, $request->user());
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
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->revisions->canView($request->user(), $page), 403);
  }

  private function authorizeRestore(Request $request, Page $page): void
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $page);
    abort_unless($this->revisions->canRestore($request->user(), $page), 403);
  }

  private function assertRevision(Page $page, PageRevision $revision): void
  {
    abort_unless($revision->page_id === $page->id, 404);
  }

  private function candidatePayload(PageRevisionCandidate $candidate): array
  {
    return [
      'id' => $candidate->id,
      'status' => $candidate->status,
      'source_updated_at' => $candidate->source_updated_at?->toIso8601String(),
      'preview_page_id' => $candidate->candidate_page_id,
      'preview_url' => $candidate->candidatePage ? route('admin.pages.preview', $candidate->candidatePage, false) : null,
      'apply_url' => route('internal-content-api.pages.version-candidates.apply', [$candidate->page_id, $candidate], false),
      'discard_url' => route('internal-content-api.pages.version-candidates.discard', [$candidate->page_id, $candidate], false),
    ];
  }
}
