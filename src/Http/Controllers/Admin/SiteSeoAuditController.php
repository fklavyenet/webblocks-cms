<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Http\Requests\Admin\SiteSeoAuditRequest;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\SiteSeoAuditQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Jobs\JobAutomation;
use WebBlocks\Cms\Support\Jobs\JobProgress;
use WebBlocks\Cms\Support\Jobs\JobRunner;
use WebBlocks\Cms\Support\Jobs\JobSchema;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class SiteSeoAuditController
{
  public function index(SiteSeoAuditRequest $request, Site $site, SiteSeoAuditQuery $query): Response|JsonResponse
  {
    $data = $query->run($site, $request->validated(), app(AdminLocaleResolver::class)->locale());

    if ($this->api($request)) {
      return $this->privateResponse(response()->json(['ok' => true, ...$data]));
    }

    $auditedPageIds = $data['pages']?->getCollection()->pluck('page_id')->filter()->unique();
    $canEditSeo = app(CmsAuthorization::class)->allows($request->user(), 'cms.seo.update', $site->id);
    $workflow = app(PageWorkflowManager::class);
    $editablePageIds = $canEditSeo && $auditedPageIds?->isNotEmpty()
      ? Page::query()->visibleInAdmin()->where('site_id', $site->id)->whereKey($auditedPageIds->all())->get()
        ->filter(fn (Page $page) => ! $page->isRevisionRestoreCandidate()
          && $workflow->canEditContent($request->user(), $page))->modelKeys()
      : [];

    return $this->privateResponse(response()->view('webblocks-cms::admin.sites.seo', [
      'site' => $site,
      'filters' => $request->validated(),
      ...$data,
      'editablePageIds' => $editablePageIds,
      'seoAutomatic' => collect(app(JobAutomation::class)->status()['sources'])->contains(fn ($source) => $source['verified']),
    ]));
  }

  public function store(SiteSeoAuditRequest $request, Site $site, ManageSeoAudit $manage): JsonResponse|RedirectResponse
  {
    $audit = $manage->start($site);

    return $this->result($request, $audit);
  }

  public function transition(SiteSeoAuditRequest $request, Site $site, SeoAudit $seoAudit, string $action, ManageSeoAudit $manage): JsonResponse|RedirectResponse
  {
    $audit = $manage->transition($seoAudit, $action);
    if (JobSchema::ready()) {
      JobRun::query()->where('job_key', 'seo')->where('subject_id', $audit->id)->whereNotNull('active_key')
        ->whereNull('lease_token')->update(['state' => $audit->state === 'paused' ? 'paused' : 'queued']);
    }

    return $this->result($request, $audit);
  }

  public function work(SiteSeoAuditRequest $request, Site $site, SeoAudit $seoAudit, JobRunner $runner): JsonResponse
  {
    abort_unless(JobSchema::ready(), 409);
    $run = $runner->request('seo', $site->id, $seoAudit->id, $request->user()->id);
    $run = $runner->advance($run);
    $seoAudit->refresh();

    $locale = app(AdminLocaleResolver::class)->locale();

    return response()->json([...JobProgress::payload($run), 'state' => $seoAudit->state,
      'processed' => $seoAudit->processed, 'total' => $seoAudit->phase === 'inventory' ? null : $seoAudit->total,
      'message' => trans('webblocks-cms::seo_workspace.states.'.$seoAudit->state, [], $locale),
      'phase_label' => trans('webblocks-cms::seo_workspace.phases.'.$seoAudit->phase, [], $locale),
    ])->header('Cache-Control', 'private, no-store');
  }

  private function result(SiteSeoAuditRequest $request, SeoAudit $audit): JsonResponse|RedirectResponse
  {
    return $this->privateResponse($this->api($request)
      ? response()->json(['ok' => true, 'audit' => ['id' => $audit->id, 'site_id' => $audit->site_id, 'state' => $audit->state, 'phase' => $audit->phase]], 202)
      : redirect()->route('admin.sites.seo-audits.index', ['site' => $audit->site_id, 'audit' => $audit->id, 'tab' => $request->validated('tab') ?? 'findings'])->with('seo_manual_run', in_array($audit->state, ['queued', 'running'], true) ? $audit->id : null));
  }

  private function api(SiteSeoAuditRequest $request): bool
  {
    return str_starts_with((string) $request->route()?->getName(), 'internal-content-api.');
  }

  private function privateResponse($response): mixed
  {
    return $response->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
  }
}
