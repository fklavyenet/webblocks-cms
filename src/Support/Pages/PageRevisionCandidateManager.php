<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;

class PageRevisionCandidateManager
{
  public function __construct(private readonly PageRevisionManager $revisionManager) {}

  public function tableExists(): bool
  {
    return Schema::hasTable('wbcms_page_revision_candidates');
  }

  public function readyFor(Page $page, PageRevision $revision): ?PageRevisionCandidate
  {
    if (! $this->tableExists()) {
      return null;
    }

    return PageRevisionCandidate::query()
      ->where('page_id', $page->id)
      ->where('page_revision_id', $revision->id)
      ->where('status', PageRevisionCandidate::STATUS_READY)
      ->with('candidatePage')
      ->latest('id')
      ->first();
  }

  public function create(Page $page, PageRevision $revision, User $actor): PageRevisionCandidate
  {
    if (! $this->tableExists()) {
      throw new RuntimeException('Page version candidates are not ready. Run the latest migrations.');
    }

    return DB::transaction(function () use ($page, $revision, $actor): PageRevisionCandidate {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);
      $revision = PageRevision::query()->where('page_id', $page->id)->findOrFail($revision->id);

      PageRevisionCandidate::query()
        ->where('page_id', $page->id)
        ->where('status', PageRevisionCandidate::STATUS_READY)
        ->with('candidatePage')
        ->get()
        ->each(fn (PageRevisionCandidate $candidate) => $this->discardRecord($candidate));

      $candidatePage = Page::query()->create([
        'site_id' => $page->site_id,
        'title' => ($page->title ?: 'Page').' version preview',
        'page_type' => $page->page_type,
        'page_type_id' => $page->page_type_id,
        'layout_id' => data_get($revision->snapshot, 'page.layout_id', $page->layout_id),
        'settings' => ['revision_restore_candidate' => ['state' => 'preparing']],
        'status' => Page::STATUS_DRAFT,
        'created_by_user_id' => $actor->id,
        'updated_by_user_id' => $actor->id,
      ]);

      $candidate = PageRevisionCandidate::query()->create([
        'page_id' => $page->id,
        'page_revision_id' => $revision->id,
        'candidate_page_id' => $candidatePage->id,
        'created_by_user_id' => $actor->id,
        'status' => PageRevisionCandidate::STATUS_READY,
        'source_updated_at' => $page->updated_at,
      ]);

      $this->revisionManager->applySnapshotToCandidate($candidatePage, $revision->snapshot ?? [], [
        'state' => 'ready',
        'source_page_id' => $page->id,
        'revision_id' => $revision->id,
        'candidate_id' => $candidate->id,
        'created_at' => now()->toIso8601String(),
      ]);

      return $candidate->fresh(['candidatePage']);
    });
  }

  public function apply(PageRevisionCandidate $candidate, User $actor): Page
  {
    return DB::transaction(function () use ($candidate, $actor): Page {
      $candidate = PageRevisionCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
      $this->assertReady($candidate);
      $page = Page::query()->lockForUpdate()->findOrFail($candidate->page_id);
      $revision = PageRevision::query()->where('page_id', $page->id)->findOrFail($candidate->page_revision_id);

      if (! $candidate->source_updated_at || ! $page->updated_at || ! $candidate->source_updated_at->equalTo($page->updated_at)) {
        throw new RuntimeException('The current page changed after this restore candidate was created. Discard it and prepare a new candidate.');
      }

      $this->revisionManager->restore($page, $revision, $actor);
      $candidatePage = $candidate->candidatePage;
      $candidate->forceFill([
        'status' => PageRevisionCandidate::STATUS_APPLIED,
        'applied_at' => now(),
        'candidate_page_id' => null,
      ])->save();
      $candidatePage?->delete();

      return $page->fresh();
    });
  }

  public function discard(PageRevisionCandidate $candidate): void
  {
    DB::transaction(function () use ($candidate): void {
      $candidate = PageRevisionCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
      $this->assertReady($candidate);
      $this->discardRecord($candidate);
    });
  }

  private function discardRecord(PageRevisionCandidate $candidate): void
  {
    $candidatePage = $candidate->candidatePage;
    $candidate->forceFill([
      'status' => PageRevisionCandidate::STATUS_DISCARDED,
      'discarded_at' => now(),
      'candidate_page_id' => null,
    ])->save();
    $candidatePage?->delete();
  }

  private function assertReady(PageRevisionCandidate $candidate): void
  {
    if ($candidate->status !== PageRevisionCandidate::STATUS_READY || ! $candidate->candidate_page_id) {
      throw new RuntimeException('This restore candidate is no longer active.');
    }
  }
}
