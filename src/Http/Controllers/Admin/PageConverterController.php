<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PageConversionReviewRequest;
use WebBlocks\Cms\Http\Requests\Admin\PageConverterAnalyzeRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PageConverter\PageConversionDraftCreator;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSerializer;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSigner;
use WebBlocks\Cms\Support\PageConverter\PageConverterAnalyzer;
use WebBlocks\Cms\Support\PageConverter\PageConverterPlan;
use WebBlocks\Cms\Support\PageConverter\PageConverterProfile;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageConverterController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageLayoutManager $pageLayouts,
    private readonly PageConverterAnalyzer $analyzer,
    private readonly PageConversionPlanSerializer $planSerializer,
    private readonly PageConversionPlanSigner $planSigner,
    private readonly PageConversionDraftCreator $draftCreator,
  ) {}

  public function index(Request $request): View
  {
    return $this->view($request);
  }

  public function analyze(PageConverterAnalyzeRequest $request): View
  {
    return $this->view($request, $this->analyzer->analyze($request->toInput()));
  }

  public function createDraft(PageConversionReviewRequest $request): RedirectResponse
  {
    $result = $this->draftCreator->create($request->conversionPlanPayload(), $request->user());

    return redirect()
      ->route('admin.pages.edit', $result->page)
      ->with('status', $result->message())
      ->with('status_action', [
        'label' => 'Edit draft page',
        'url' => route('admin.pages.edit', $result->page),
      ]);
  }

  private function view(Request $request, ?PageConverterPlan $conversionPlan = null): View
  {
    $sites = $this->authorization
      ->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())
      ->with(['enabledLocales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
      ->get();

    $selectedSiteId = (int) old('site_id', $request->input('site_id', $sites->first()?->id));
    $selectedSite = $sites->firstWhere('id', $selectedSiteId) ?? $sites->first();
    $locales = $selectedSite?->enabledLocales ?? collect();
    $planPayload = $conversionPlan ? $this->planSerializer->serialize($conversionPlan) : null;

    return view('webblocks-cms::admin.pages.converter', [
      'sites' => $sites,
      'selectedSite' => $selectedSite,
      'locales' => $locales,
      'pageLayoutOptions' => $this->pageLayouts->pageSelectionOptions((string) old('page_layout', 'default')),
      'profiles' => PageConverterProfile::options(),
      'conversionPlan' => $conversionPlan,
      'planPayload' => $planPayload,
      'planSignature' => $planPayload ? $this->planSigner->sign($planPayload) : null,
    ]);
  }
}
