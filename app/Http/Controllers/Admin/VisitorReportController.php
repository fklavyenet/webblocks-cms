<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\Site;
use App\Support\Users\AdminAuthorization;
use App\Support\Visitors\VisitorReportsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        return view('admin.reports.visitors.index', [
            'sites' => $sites,
            'locales' => $locales,
            'filters' => $filters,
            'report' => config('cms.visitor_reports.enabled', true) && $tableExists
                ? $this->reports->build($filters)
                : null,
            'isEnabled' => (bool) config('cms.visitor_reports.enabled', true),
            'utmEnabled' => $this->reports->utmTrackingEnabled(),
            'supportsUtmBreakdowns' => $this->reports->supportsUtmBreakdowns(),
            'visitorEventsTableExists' => $tableExists,
        ]);
    }
}
