<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

class PageRevisionCandidateManager
{
  public function __construct(private readonly PageRevisionManager $revisionManager) {}

  public function tableExists(): bool
  {
    return Schema::hasTable('wbcms_page_revision_candidates') && Schema::hasColumns('wbcms_page_revision_candidates', ['restore_scope', 'restore_locale_id', 'source_fingerprint', 'revision_fingerprint', 'preview_fingerprint']);
  }

  public function readyFor(Page $page, PageRevision $revision, string $scope = 'full_page', ?int $localeId = null): ?PageRevisionCandidate
  {
    if (! $this->tableExists()) {
      return null;
    }

    return PageRevisionCandidate::query()
      ->where('page_id', $page->id)
      ->where('page_revision_id', $revision->id)
      ->where('restore_scope', $scope)
      ->where('restore_locale_id', $localeId)
      ->where('status', PageRevisionCandidate::STATUS_READY)
      ->with('candidatePage')
      ->latest('id')
      ->first();
  }

  public function create(Page $page, PageRevision $revision, User $actor, string $scope = 'full_page', ?int $localeId = null): PageRevisionCandidate
  {
    if (! $this->tableExists()) {
      throw new RuntimeException('Page version candidates are not ready. Run the latest migrations.');
    }

    return app(RevisionContentUpdates::class)->during(function () use ($page, $revision, $actor, $scope, $localeId): PageRevisionCandidate {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);
      $revision = PageRevision::query()->where('page_id', $page->id)->lockForUpdate()->findOrFail($revision->id);
      $plan = null;
      if ($scope !== 'full_page') {
        app(PageLocaleRestoreManager::class)->lock($page);
        $plan = app(PageLocaleRestorePlanner::class)->plan($page->fresh(), $revision, $scope, $localeId);
        if (! $plan['ok']) {
          throw new PageLocaleRestoreException($plan['issues']);
        }
      } elseif ($localeId !== null) {
        throw new PageLocaleRestoreException([['code' => 'invalid_scope']]);
      }

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
        'layout_id' => $scope === 'full_page' ? data_get($revision->snapshot, 'page.layout_id', $page->layout_id) : $page->layout_id,
        'settings' => ['revision_restore_candidate' => ['state' => 'preparing']],
        'status' => Page::STATUS_DRAFT,
        'created_by_user_id' => $actor->id,
        'updated_by_user_id' => $actor->id,
      ]);
      if (Schema::hasTable('wbcms_page_access_rules') && ($rule = $page->accessRule()->first())) {
        $candidatePage->accessRule()->create(['package_id' => $rule->package_id]);
      }

      $candidate = PageRevisionCandidate::query()->create([
        'page_id' => $page->id,
        'page_revision_id' => $revision->id,
        'restore_scope' => $scope,
        'restore_locale_id' => $localeId,
        'source_fingerprint' => $plan['source_fingerprint'] ?? null,
        'revision_fingerprint' => $plan['revision_fingerprint'] ?? null,
        'candidate_page_id' => $candidatePage->id,
        'created_by_user_id' => $actor->id,
        'status' => PageRevisionCandidate::STATUS_READY,
        'source_updated_at' => $page->updated_at,
      ]);

      $this->revisionManager->applySnapshotToCandidate($candidatePage, $plan['projected_snapshot'] ?? $revision->snapshot ?? [], [
        'state' => 'ready',
        'source_page_id' => $page->id,
        'revision_id' => $revision->id,
        'candidate_id' => $candidate->id,
        'created_at' => now()->toIso8601String(),
      ]);

      if ($plan !== null) {
        $candidate->forceFill(['preview_fingerprint' => SnapshotContent::hash($this->revisionManager->snapshotForInspection($candidatePage->fresh()))])->save();
      }

      return $candidate->fresh(['candidatePage']);
    }, 3);
  }

  public function apply(PageRevisionCandidate $candidate, User $actor, array $confirmedTranslationRemovalLocaleIds = [], bool $confirmLocaleRestore = false): Page
  {
    return app(RevisionContentUpdates::class)->during(function () use ($candidate, $actor, $confirmedTranslationRemovalLocaleIds, $confirmLocaleRestore): Page {
      $candidate = PageRevisionCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
      $this->assertReady($candidate);
      $page = Page::query()->lockForUpdate()->findOrFail($candidate->page_id);
      $revision = PageRevision::query()->where('page_id', $page->id)->lockForUpdate()->findOrFail($candidate->page_revision_id);

      if (! $candidate->source_updated_at || ! $page->updated_at || ! $candidate->source_updated_at->equalTo($page->updated_at)) {
        if ($candidate->restore_scope !== 'full_page') {
          throw new PageLocaleRestoreException([['code' => 'stale']]);
        }
        throw new RuntimeException('The current page changed after this restore candidate was created. Discard it and prepare a new candidate.');
      }

      if (($candidate->restore_scope ?? 'full_page') === 'full_page') {
        $this->revisionManager->restore($page, $revision, $actor, $confirmedTranslationRemovalLocaleIds);
      } else {
        $page = app(PageLocaleRestoreManager::class)->apply($candidate, $page, $revision, $actor, $confirmLocaleRestore);
      }
      $candidatePage = $candidate->candidatePage;
      $candidate->forceFill([
        'status' => PageRevisionCandidate::STATUS_APPLIED,
        'applied_at' => now(),
        'candidate_page_id' => null,
      ])->save();
      $candidatePage?->delete();

      return $page->fresh();
    }, 3);
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
