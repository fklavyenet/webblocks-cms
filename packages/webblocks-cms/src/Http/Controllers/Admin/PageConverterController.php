<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PageConverterAnalyzeRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PageConverter\PageConverterAnalyzer;
use WebBlocks\Cms\Support\PageConverter\PageConverterProfile;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageConverterController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly PageLayoutManager $pageLayouts,
    private readonly PageConverterAnalyzer $analyzer,
  ) {}

  public function index(Request $request): View
  {
    return $this->view($request);
  }

  public function analyze(PageConverterAnalyzeRequest $request): View
  {
    return $this->view($request, $this->analyzer->analyze($request->toInput()));
  }

  private function view(Request $request, mixed $conversionPlan = null): View
  {
    $sites = $this->authorization
      ->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())
      ->with(['enabledLocales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
      ->get();

    $selectedSiteId = (int) old('site_id', $request->input('site_id', $sites->first()?->id));
    $selectedSite = $sites->firstWhere('id', $selectedSiteId) ?? $sites->first();
    $locales = $selectedSite?->enabledLocales ?? collect();

    return view('webblocks-cms::admin.pages.converter', [
      'sites' => $sites,
      'selectedSite' => $selectedSite,
      'locales' => $locales,
      'pageLayoutOptions' => $this->pageLayouts->pageSelectionOptions((string) old('page_layout', 'default')),
      'profiles' => PageConverterProfile::options(),
      'conversionPlan' => $conversionPlan,
    ]);
  }
}
