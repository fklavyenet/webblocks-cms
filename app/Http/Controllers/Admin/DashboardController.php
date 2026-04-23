<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\SlotType;
use App\Support\Users\AdminAuthorization;
use App\Support\Visitors\VisitorReportsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VisitorReportsQuery $visitorReports,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'pages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())->count(),
                'media' => $this->authorization->scopeAssetsForUser(Asset::query(), $request->user())->count(),
                'blocks' => $this->authorization->scopeBlocksForUser(Block::query(), $request->user())->count(),
                'slotTypes' => SlotType::count(),
                'blockTypes' => BlockType::count(),
                'draftPages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())->where('status', 'draft')->count(),
                'publishedPages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())->where('status', 'published')->count(),
            ],
            'recentPages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())
                ->with(['slots.slotType', 'translations'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentAssets' => $this->authorization->scopeAssetsForUser(Asset::query(), $request->user())
                ->latest()
                ->limit(5)
                ->get(),
            'visitorSummary' => $this->visitorReports->dashboardSummary($request->user()),
        ]);
    }
}
