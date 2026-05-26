<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Support\Visitors\VisitorReportsQuery;

class VisitorReportController extends Controller
{
  public function __construct(
    private readonly VisitorReportsQuery $reports,
    private readonly AdminAuthorization $authorization,
  ) {}

  public function index(Request $request): View
  {
    $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())->get();
    $locales = Locale::query()->where('is_enabled', true)->orderByDesc('is_default')->orderBy('name')->get();
    $filters = $this->reports->filters($request, $request->user());
    $tableExists = $this->reports->hasEventsTable();

    return view('webblocks-cms::admin.reports.visitors.index', [
      'sites' => $sites,
      'locales' => $locales,
      'filters' => $filters,
      'report' => config('cms.visitor_reports.enabled', true) && $tableExists
        ? $this->reports->build($filters)
        : null,
      'isEnabled' => (bool) config('cms.visitor_reports.enabled', true),
      'utmEnabled' => $this->reports->utmTrackingEnabled(),
      'supportsUtmBreakdowns' => $this->reports->supportsUtmBreakdowns(),
      'supportsBotBreakdowns' => $this->reports->supportsBotBreakdowns(),
      'visitorEventsTableExists' => $tableExists,
      'privacyAwareReportingMessage' => 'Page views, referrer hosts, UTM values, device categories, and bot labels are stored as anonymous aggregates without raw IP addresses, full referrer URLs, query strings, or user-agent strings. Unique visitors, sessions, and average pages per session are shown only when consent-based session tracking is available.',
    ]);
  }
}
