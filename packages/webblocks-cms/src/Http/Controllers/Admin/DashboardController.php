<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Visitors\VisitorReportsQuery;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VisitorReportsQuery $visitorReports,
        private readonly AdminAuthorization $authorization,
        private readonly SystemSettings $systemSettings,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('webblocks-cms::admin.dashboard', [
            'title' => 'Admin Dashboard',
            'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
            'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('Admin Dashboard'),
            'stats' => [
                'pages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())->count(),
                'media' => $this->authorization->scopeMediaForUser(Media::query(), $request->user())->count(),
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
            'recentAssets' => $this->authorization->scopeMediaForUser(Media::query(), $request->user())
                ->latest()
                ->limit(5)
                ->get(),
            'visitorSummary' => $this->visitorReports->dashboardSummary($request->user()),
        ]);
    }
}
